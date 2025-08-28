<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Jobs\AutoReadyOrderJob;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OrderAutoStatusController extends Controller
{
    public function autoStatus(Request $request)
{
    $token    = $request->input('token');
    $expected = config('services.function_token');

    \Log::info('AUTO-STATUS HIT', [
        'got_token_len' => strlen((string) $token),
        'expected_len'  => strlen((string) $expected),
    ]);

    if (empty($expected) || $token !== $expected) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $v = Validator::make($request->all(), [
        'order_id' => ['required'],
        'duration' => ['nullable','integer','min:1','max:86400'], // <-- test üçün min:1
    ]);
    if ($v->fails()) {
        return response()->json(['message' => 'Validation error', 'errors' => $v->errors()], 422);
    }

    $orderId  = $request->input('order_id');
    $duration = (int) $request->input('duration', 900);

    /** @var Order|null $order */
    $order = Order::find($orderId);
    if (!$order) {
        return response()->json(['message' => 'Order not found'], 404);
    }

    AutoReadyOrderJob::dispatch($order->id)->delay(now()->addSeconds($duration));
    Log::info('AutoReadyOrderJob queued', ['order_id' => $order->id, 'delay' => $duration]);

    return response()->json([
        'message'  => 'Auto ready scheduled',
        'order_id' => $order->id,
        'delay'    => $duration,
    ]);
}

}
