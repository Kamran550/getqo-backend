<?php

namespace App\Services\PaymentService;

use App\Services\PaymentService\BaseService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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


    public function generateSignature($url, $rndKey, $body = '')
    {
        $string = $url . $this->apiKey . $this->secretKey . $rndKey . $body;
        return base64_encode(hash('sha256', $string, true));
    }

    public function initPayment(array $payload)
    {
        $url = $this->baseUrl . '/payment/v1/checkout-payments/init';
        $rndKey = uniqid(); // random string
        Log::info('rndKey:', ['rndKey:', $rndKey]);
        Log::info('api:',['api:',$this->apiKey]);

        Log::info('base:', ['base url:', $this->baseUrl]);

        Log::info('url:', ['url:', $url]);
        $body = json_encode($payload);
        Log::info('body:', ['body:', $body]);

        $signature = $this->generateSignature($url, $rndKey, $body);
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
        $signature = $this->generateSignature($url, $rndKey);

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
        $signature = $this->generateSignature($url, $rndKey, $body);

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'x-rnd-key' => $rndKey,
            'x-auth-version' => 'V1',
            'x-signature' => $signature,
        ])->post($url, $payload);

        return $response->json();
    }
}
