<?php

namespace App\Services\PaymentService;

use App\Models\Payment;
use App\Models\PaymentPayload;
use App\Models\PaymentProcess;
use App\Services\PaymentService\BaseService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use App\Models\Payout;
use Session;
use Str;

class OderoService extends BaseService
{
    protected $apiKey;
    protected $secretKey;
    protected $baseUrl;


    public function __construct()
    {
        $this->apiKey = env('ODERO_API_KEY');
        $this->secretKey = env('ODERO_SECRET_KEY');
        $this->baseUrl = env('ODERO_BASE_URL');
    }

    public function getApiKey()
    {
        return $this->apiKey;
    }


    public function generateSignature($url, $rndKey, $body = '', $odero_sk)
    {

        $string = $url . $this->apiKey . $this->secretKey . $rndKey . $body;
        return base64_encode(hash('sha256', $string, true));
    }

    public function initPayment(array $payload)
    {
        $url = $this->baseUrl . '/payment/v1/checkout-payments/init';
        $rndKey = uniqid(); // random string
        Log::info('api:', ['key:', $this->apiKey]);
        Log::info('api:', ['secret:', $this->secretKey]);
        Log::info('url:', ['url:', $url]);

        Log::info('rndKey', ['rndKey:', $rndKey]);
        $body = json_encode($payload);

        $payment = Payment::where('tag', Payment::TAG_ODERO)->first();
        Log::info('payment:', ['pay:', $payment]);

        $paymentPayload = PaymentPayload::where('payment_id', $payment?->id)->first();

        $odero_sk = data_get($paymentPayload?->paylaod, 'odero_sk');

        $signature = $this->generateSignature($url, $rndKey, $body, $odero_sk);
        Log::info('signature:', ['sign:', $signature]);
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'x-rnd-key' => $rndKey,
            'x-auth-version' => 'V1',
            'x-signature' => $signature,
        ])->post($url, $payload);

        Log::info('response body:', ['res body:', $response->json()]);




        return $response->json();
    }


    public function getPaymentStatus(string $token)
    {
        $url = $this->baseUrl . "/payment/v1/checkout-payments/{$token}";
        $rndKey = uniqid();
        $signature = $this->generateSignature($url, $rndKey, 'test', 'test');

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'x-rnd-key' => $rndKey,
            'x-auth-version' => 'V1',
            'x-signature' => $signature,
        ])->get($url);

        return $response->json();
    }

    public function refund(array $payload)
    {
        $url = $this->baseUrl . '/payment/v1/refund-transactions';
        $rndKey = uniqid();
        $body = json_encode($payload);

        $payment = Payment::where('tag', Payment::TAG_ODERO)->first();
        Log::info('payment:', ['pay:', $payment]);

        $paymentPayload = PaymentPayload::where('payment_id', $payment?->id)->first();
        $odero_sk = data_get($paymentPayload?->paylaod, 'odero_sk');


        $signature = $this->generateSignature($url, $rndKey, $body, $odero_sk);

        Log::info('payload:', ['payload' => $payload]);
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'x-rnd-key' => $rndKey,
            'x-auth-version' => 'V1',
            'x-signature' => $signature,
        ])->post($url, $payload);

        return $response->json();
    }


    public function orderProcessTransaction(array $data, array $types = ['card']): Model|PaymentProcess
    {
        $payment = Payment::where('tag', Payment::TAG_ODERO)->first();
        Log::info('payment:', ['pay:', $payment]);

        $paymentPayload = PaymentPayload::where('payment_id', $payment?->id)->first();

        Log::info('payment:', ['pay:', $payment]);

        $payload        = $paymentPayload?->payload;
        $odero_pk = data_get($payload, 'odero_pk');
        $odero_sk = data_get($payload, 'odero_sk');

        Log::info('odero_pk:', ['odero_pk:', $odero_pk]);
        Log::info('odero_sk:', ['odero_sk:', $odero_sk]);

        [$key, $before] = $this->getPayload($data, $payload);

        Log::info('key:', ['key:' => $key]);
        Log::info('before:', ['before:' => $before]);

        $modelId         = data_get($before, 'model_id');

        Log::info('modelId:', ['modelId:' => $modelId]);

        $totalPrice = round((float)data_get($before, 'total_price') * 100, 2);

        $this->childrenProcess($modelId, data_get($before, 'model_type'));


        Log::info('pricem:', ['pricem:', data_get($before, 'total_price')]);
        // Init üçün ODERO strukturu



        $web = $this->web($data, $types, $before, $totalPrice, $modelId, $payment, $odero_pk, $odero_sk);
        Log::info('web:', ['web:', $web]);
        return $web;
    }



    public function initPayment2(array $payload, string $odero_pk, string $odero_sk)
    {
        $url = $this->baseUrl . '/payment/v1/checkout-payments/init';
        $rndKey = uniqid(); // random string

        $body = json_encode($payload);


        $signature = $this->generateSignature($url, $rndKey, $body, $odero_sk);

        Log::info('signature:', ['sign:' => $signature]);

        Log::info('payload', ["payload:", $payload]);
        $response = Http::withHeaders([
            'x-api-key' => $odero_pk,
            'x-rnd-key' => $rndKey,
            'x-auth-version' => 'V1',
            'x-signature' => $signature,
        ])->post($url, $payload);

        Log::info('response body:', ['res body:' => $response->json()]);

        return $response->json();
    }


    private function web(array $data, array $types, array $before, float $totalPrice, int $modelId, Payment $payment, string $odero_pk, string $odero_sk): Model|PaymentProcess
    {


        $initPayload = [
            'price'           => data_get($before, 'total_price'),
            'paidPrice'       => data_get($before, 'total_price'),
            'installment'     => 1,
            'conversationId'  => "azetestconvid",
            'currency'        => "TRY",
            // 'currency'        => Str::upper(data_get($before, 'currency')),
            'paymentPhase'    => 'AUTH',
            'paymentGroup'    => 'LISTING_OR_SUBSCRIPTION',
            'callbackUrl'     => "http://localhost:8000/api/v1/webhook/odero/payment",
            'items' => [
                [
                    'name'  => 'Test product',
                    'price' => data_get($before, 'total_price')
                ]
            ]
        ];




        $oderoResponse = $this->initPayment2($initPayload, $odero_pk, $odero_sk);


        Log::info('oderoResponse', ['oderoResponse:', $oderoResponse]);

        if (!isset($oderoResponse['data']['token'])) {
            Log::error('ODEROPay init uğursuz oldu');
            throw new \Exception('ODEROPay init uğursuz oldu.');
        }

        return PaymentProcess::create([
            'user_id'    => auth('sanctum')->id(),
            'model_id'   => $modelId,
            'model_type' => data_get($before, 'model_type'),
            'id'         => $oderoResponse['data']['token'],
            'data'       => array_merge([
                'url'        => $oderoResponse['data']['pageUrl'],
                'payment_id' => $payment->id,
                'split'      => $data['split'] ?? 1
            ], $before)
        ]);
    }
}
