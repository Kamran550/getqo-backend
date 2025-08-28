<?php

namespace App\Jobs;

use App\Models\Order;
use App\Traits\Notification;
use App\Traits\Loggable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;

class AutoReadyOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Notification, Loggable;

    public int $timeout = 120;

    public function __construct(public int $orderId) {}

    public function handle(): void
    {
        /** @var Order|null $order */
        $order = Order::with(['shop','user'])->find($this->orderId);

        if (!$order) {
            Log::warning('AutoReadyOrderJob: order not found', ['order_id' => $this->orderId]);
            return;
        }

        // yalnız COOKING-dirsə davam et
        if ($order->status !== Order::STATUS_COOKING) {
            Log::info('AutoReadyOrderJob: guard skip (status mismatch)', [
                'order_id' => $order->id,
                'status'   => $order->status,
            ]);
            return;
        }

        // READY-ə keçir
        $order->update(['status' => Order::STATUS_READY]);

        Log::info('AutoReadyOrderJob: set READY', [
            'order_id' => $order->id,
            'shop_id'  => $order->shop_id,
        ]);

        // (optional) user-ə xəbər ver
        try {
            if ($order->user_id) {
                $this->sendNotification(
                    receivers: [$order->user_id],
                    title: __('Order is ready'),
                    message: __('Order #:id is ready for delivery', ['id' => $order->id]),
                    data: [
                        'type'     => 'order_ready',
                        'order_id' => (string)$order->id,
                        'role'     => 'user',
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::warning('AutoReadyOrderJob: user push failed', ['order_id' => $order->id, 'e' => $e->getMessage()]);
        }

        // DELIVERY-disə və hələ deliveryman yoxdursa — driver-lərə push üçün mövcud job-u işə sal
        try {
            if ($order->delivery_type === Order::DELIVERY && empty($order->deliveryman)) {
                // dil lazım deyilsə null verə bilərsən
                \App\Jobs\AttachDeliveryMan::dispatch($order, app()->getLocale())
                    ->onQueue('default');
                Log::info('AutoReadyOrderJob: AttachDeliveryMan dispatched', ['order_id' => $order->id]);
            }
        } catch (\Throwable $e) {
            Log::error('AutoReadyOrderJob: attach driver dispatch failed', ['order_id' => $order->id, 'e' => $e->getMessage()]);
        }
    }
}
