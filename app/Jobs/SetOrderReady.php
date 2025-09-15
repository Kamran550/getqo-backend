<?php

namespace App\Jobs;

use App\Events\OrderReady;
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

class SetOrderReady implements ShouldQueue
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

        \Log::info('SetOrderReady job started', [
            'order_id' => $this->order->id,
            'scheduled_ready_time' => $this->order->ready_at,
            'current_time' => now()
        ]);

        $order = Order::find($this->order->id);

        if (!$order || $order->status !== Order::STATUS_COOKING) {

            \Log::info('SetOrderReady: Order status changed, skipping', [
                'order_id' => $order->id,
                'current_status' => $order->status,
                'expected_status' => Order::STATUS_COOKING
            ]);

            return; // Order might have been cancelled or manually changed
        }

        DB::beginTransaction();
        try {
            $order->fill(['status' => Order::STATUS_READY]);
            $order->save(); // save() triggers updated event
            $order->refresh();

            // event(new OrderReady($order));
            Log::info("'111111111111111111111111111'");
            // Trigger courier search if not already started
            // if (!$order->courier_search_started) {
            //     event(new OrderReady($order));
            //     $this->startCourierSearch($order);
            // }

            DB::commit();
            \Log::info('SetOrderReady job completed successfully', [
                'order_id' => $order->id,
                'order_status' => $order->status
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Failed to set order as ready', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }


    private function startCourierSearch(Order $order)
    {
        // Burada mövcud AttachDeliveryMan funksiyasını çağırın
        // Bu sizin hal-hazırda olan observer pattern-i ilə işləyir
        Log::info("'bura gelir ve error atir sora'");
        AttachDeliveryMan::dispatchAfterResponse($order, $this->language());

        // Mark that courier search has started
        $order->update(['courier_search_started' => true]);

        \Log::info('Courier search initiated from SetOrderReady', [
            'order_id' => $order->id,
            'search_started_at' => now()
        ]);
    }

    public function language(): string
    {
        return request(
            'lang',
            data_get(Language::where('default', 1)->first(['locale', 'default']), 'locale')
        );
    }
}
