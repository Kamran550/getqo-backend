<?php

namespace App\Http\Controllers\API\v1\Dashboard\Payment;

use App\Http\Controllers\Controller;
use App\Services\OderoService;
use App\Services\PaymentService\OderoService as PaymentServiceOderoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OderoController extends Controller
{
    protected $odero;

    public function __construct(PaymentServiceOderoService $oderoService)
    {
        $this->odero = $oderoService;
    }

    public function init(Request $request)
    {
        Log::info('salam odero');
        $payload = $request->all();
        $response = $this->odero->initPayment($payload);
        return response()->json($response);
    }


    public function status($token)
    {
        $response = $this->odero->getPaymentStatus($token);
        return response()->json($response);
    }

    public function refund(Request $request)
    {
        $response = $this->odero->refund($request->all());
        return response()->json($response);
    }
}
