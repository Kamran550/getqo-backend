<?php

namespace App\Jobs;

use App\Events\CourierSearchStarted;
use App\Models\Language;
use App\Models\Order;
use DB;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StartCourierSearch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        \Log::info('StartCourierSearch job started', [
            'order_id' => $this->order->id,
            'current_time' => now(),
            'order_ready_at' => $this->order->ready_at
        ]);
        Log::info('77777order:', ['order:', $this->order->status]);

        $order = Order::find($this->order->id);


        Log::info('3333order:', ['order:', $order->status]);

        // Only start courier search if order is still being prepared
        if (!$order || !in_array($order->status, [Order::STATUS_COOKING, Order::STATUS_READY])) {
            \Log::info('StartCourierSearch: Order status not suitable for courier search', [
                'order_id' => $order->id,
                'current_status' => $order->status,
                'expected_statuses' => ['preparing', 'ready']
            ]);

            return;
        }
        DB::beginTransaction();
        try {
            // Call existing AttachDeliveryMan functionality
            Log::info("basladiqe");
            AttachDeliveryMan::dispatchAfterResponse($order, $this->language());


            Log::info('444444order:', ['order:', $order->status]);
            // Mark that courier search has started
            $order->update(['courier_search_started' => true]);

            Log::info('55555555order:', ['order:', $order->status]);

            // Dispatch event for real-time notifications
            event(new CourierSearchStarted($order));

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Failed to start courier search', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function language(): string
    {
        return request(
            'lang',
            data_get(Language::where('default', 1)->first(['locale', 'default']), 'locale')
        );
    }
}
