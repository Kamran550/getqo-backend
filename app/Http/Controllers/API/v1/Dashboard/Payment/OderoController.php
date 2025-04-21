<?php

namespace App\Http\Controllers\API\v1\Dashboard\Payment;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentPayload;
use App\Models\PaymentProcess;
use App\Models\Transaction;
use App\Services\OderoService;
use App\Services\PaymentService\OderoService as PaymentServiceOderoService;
use App\Traits\ApiResponse;
use App\Traits\OnResponse;
use Carbon\Carbon;
use Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redirect;
use Throwable;

class OderoController extends Controller
{
    use OnResponse, ApiResponse;
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

        // if (isset($response['data']['tokenExpireDate'])) {
        //     $expireAt = Carbon::parse($response['data']['tokenExpireDate']);

        //     if (now()->greaterThan($expireAt)) {
        //         return response()->json([
        //             'message' => 'Ödəniş linkinin müddəti bitmişdir.',
        //             'status' => false
        //         ], 400);
        //     }
        // }

        return response()->json($response);
    }


    public function handle(Request $request)
    {
        Log::info('Odero callback received', $request->all());

        $token = $request->input('token');
        if (!$token) {
            Log::info('token not provided');
            return response()->json(['message' => 'Token not provided'], 400);
        }

        $payment = $this->odero->getPaymentStatus($token);
        LOg::info('status pay:', ['st pay' => $payment]);

        if ($payment['data']['paymentStatus'] === 'SUCCESS') {
            Log::info("Payment successful", $payment['data']);
        } else {
            Log::warning("Payment not successful", $payment['data']);
        }

        return redirect('http://localhost:3000/');
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



    public function orderProcessTransaction(Request $request): JsonResponse
    {
        Log::info('oderoya request daxil oldu');
        Log::info("all req:", ['req all:', $request->all()]);
        try {
            $result = $this->odero->orderProcessTransaction($request->all());
            return $this->successResponse('success', $result);
        } catch (Throwable $e) {
            $this->error($e);
            return $this->onErrorResponse([
                'message' => $e->getMessage(),
                'param'   => $e->getFile() . $e->getLine()
            ]);
        }
    }


    public function paymentWebHook(Request $request)
    {
        Log::info('webhook odero');
        // $token = $request->input('data.object.id');
        $token = $request->input('token');
        Log::info('tok:', ['token:', $token]);
        $payment = Payment::where('tag', 'odero')->first();

        /** @var PaymentProcess $paymentProcess */
        $paymentProcess = PaymentProcess::where('id', $token)->first();
        Log::info('WEBHOOK paymentProcess:', ['WEBHOOK paymentProcess', $paymentProcess]);

        if (@$paymentProcess?->data['type'] === 'mobile') {
            Log::info('mobile if');

            $status = match ($request->input('data.object.status')) {
                'succeeded', 'paid' => Transaction::STATUS_PAID,
                'payment_failed', 'canceled' => Transaction::STATUS_CANCELED,
                default => 'progress',
            };

            $this->odero->afterHook($token, $status);

            return $this->successResponse();
        }


        $url = "https://sandbox-api-gateway.oderopay.com.tr/payment/v1/checkout-payments/{$token}";

        $rndKey = uniqid();
        $signature = $this->odero->generateSignature($url, $rndKey);

        $keys = $this->odero->getPaymentKeys();
        $odero_pk = $keys['odero_pk'];

        $response = Http::withHeaders([
            'x-api-key' => $odero_pk,
            'x-rnd-key' => $rndKey,
            'x-auth-version' => 'V1',
            'x-signature' => $signature,
        ])->get($url);

        Log::info('WEBHOOK response:', ['WEBHOOK response', $response]);



        // $status = match (data_get($response, 'data.0.payment_status')) {
        //     'succeeded', 'paid'    => Transaction::STATUS_PAID,
        //     'payment_failed', 'canceled' => Transaction::STATUS_CANCELED,
        //     default => 'progress',
        // };

        // $status = $response['data']['paymentStatus'];
        $paymentStatus = $response['data']['paymentStatus'] ?? null;

        // Odero statuslarını sənin Transaction statuslarınla uyğunlaşdır
        $status = match ($paymentStatus) {
            'SUCCESS' => Transaction::STATUS_PAID,
            'FAILED' => Transaction::STATUS_CANCELED,
            default => Transaction::STATUS_PROGRESS
        };

        LOg::info('WEBHOOK status', ['WEBHOOK status', $status]);
        $to = config('app.front_url');

        try {
            $this->odero->afterHook($token, $status);
            // return $this->successResponse();

            Log::info('hook bitdi ve redirect olur:', ['to:', $to]);

            return Redirect::to($to);
        } catch (Throwable $e) {
            LOg::info('hook bitdikden sonra odero controllerde catche dusur');
            return $this->onErrorResponse([
                'code' => $e->getCode(),
                'message' => $e->getMessage() . $e->getFile() . $e->getLine()
            ]);
        }
    }
}
