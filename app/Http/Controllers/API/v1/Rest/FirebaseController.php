<?php

namespace App\Http\Controllers\API\v1\Rest;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;

class FirebaseController extends Controller
{
    public function updateOrderReadyStatus(Request $request)
    {
        if ($request->bearerToken() !== config('services.firebase_function.token')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $order = Order::find($request->order_id);

        if (!$order || $order->status !== Order::STATUS_COOKING) {
            return response()->json(['message' => 'Invalid order'], 400);
        }

        $order->update(['status' => Order::STATUS_READY]);

        return response()->json(['message' => 'Order updated to ready']);
    }
}
