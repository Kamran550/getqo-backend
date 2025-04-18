<?php

namespace App\Services\SMSGatewayService;

use App\Models\SmsPayload;
use App\Services\CoreService;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class SmileSMSService
{
    protected string $user;
    protected string $pass;
    protected string $numberId;
    private $url;
    protected $messageTemplate;


    public function __construct()
    {
        $this->user = env('SMILE_SMS_USER', '2609');
        $this->pass = env('SMILE_SMS_PASS');
        $this->numberId = env('SMILE_SMS_NUMBER_ID');
        $this->url = env('SMILE_SMS_URL');
        $this->messageTemplate = 'GetQo Otp dogrulama kodunuz: :otp';
    }

    public function buildMessage($otpCode)
    {
        return str_replace(':otp', $otpCode, $this->messageTemplate);
    }




    /**
     * @param $phone
     * @param $otp
     * @param SmsPayload $smsPayload
     * @return array|bool[]
     */

    public function sendSms($phone, $otp, SmsPayload $smsPayload): array
    {
        try {
            $smileSmsUser     = data_get($smsPayload->payload, 'smilesms_user');
            $smileSmsPass     = data_get($smsPayload->payload, 'smilesms_pass');
            $smileSmsNumberId     = data_get($smsPayload->payload, 'smilesms_number_id');
            $otpCode = data_get($otp, 'otpCode');



            if (strlen($phone) < 7) {
                throw new Exception('Invalid phone number', 400);
            }


            Log::info('otp:', ['otp:', $otpCode]);
            // $msgBody = $this->buildMessage($otpCode);
            // $url = $this->url . http_build_query([
            //     'username'  => $this->user,
            //     'password'  => $this->pass,
            //     'numberId'  => $this->numberId,
            //     'msisdn'    => $phone,
            //     'msgBody'   => $msgBody,
            // ]);

            // $response = Http::get($url);
            // logger()->info('Göndərilən URL:', ['url' => $url]);

            // $responseBody = $response->body();

            // Log::info('response body:', ['respinse body', $response->body()]);
            return ['status' => true, 'message' => 'SMS sent successfully'];

            // if (strpos($responseBody, 'Ok:') !== false) {
            //     preg_match('/Ok:\s*(\d+);/', $responseBody, $matches);
            //     $successCode = $matches[1] ?? null;
            //     logger()->info("Success: " . $successCode);
            //     return ['status' => true, 'message' => 'SMS sent successfully'];
            // } elseif (strpos($responseBody, 'Error:') !== false) {
            //     preg_match('/Error:\s*(\d+);/', $responseBody, $matches);
            //     $errorCode = $matches[1] ?? null;
            //     logger()->info("Error: " . $errorCode);
            //     return ['status' => false, 'message' => 'SMS failed'];
            // } else {
            //     logger()->info("Response format is not recognized.");
            //     return ['status' => false, 'message' => 'SMS failed'];
            // }



            return ['status' => true, 'message' => 'success'];
        } catch (Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }




    public function sendSms2($phone, $otp): array
    {
        try {
            // Telefon nömrəsinin düzgünlüyünü yoxlayırıq
            if (strlen($phone) < 7) {
                throw new Exception('Invalid phone number', 400);
            }


            // Parametrləri URL formatında hazırlayırıq
            $phone = urlencode($phone); // Telefon nömrəsini düzgün kodlayırıq
            Log::info('Phone2:', ['phone2:', $phone]);
            $otpCode        = data_get($otp, 'otpCode');

            Log::info('salaamm');
            Log::info($otpCode);
            $password = env('SMILE_SMS_PASS'); // Şifrəni kodlamadan birbaşa istifadə edirik
            Log::info('password');
            // URL-i birləşdiririk
            $url = env('SMILE_SMS_URL') . http_build_query([
                'username'  => env('SMILE_SMS_USER'),
                'password'  => $password,   // Şifrəni kodlamadan birbaşa göndəririk
                'numberId'  => env('SMILE_SMS_NUMBER_ID'),
                'msisdn'    => $phone,      // Telefon nömrəsini düzgün kodlayırıq
                'msgBody'   => urlencode($otpCode),    // SMS mətnini düzgün kodlayırıq
            ]);


            Log::info('password2');

            // Loga yazırıq
            logger()->info('Göndərilən URL:', ['url' => $url]);

            // Sorgunu göndəririk
            $response = Http::get($url);
            $responseBody = $response->body(); // Cavabın bədənini alırıq


            Log::info('response body:', ['respinse body', $response]);

            // SMS-in uğurla göndərildiyini yoxlayaq
            if (strpos($responseBody, 'Ok:') !== false) {
                preg_match('/Ok:\s*(\d+);/', $responseBody, $matches);
                $successCode = $matches[1] ?? null;
                logger()->info("Success: " . $successCode);
                return ['status' => true, 'message' => 'SMS sent successfully'];
            } elseif (strpos($responseBody, 'Error:') !== false) {
                preg_match('/Error:\s*(\d+);/', $responseBody, $matches);
                $errorCode = $matches[1] ?? null;
                logger()->info("Error: " . $errorCode);
                return ['status' => false, 'message' => 'SMS failed'];
            } else {
                return ['status' => false, 'message' => 'Unknown error'];
            }
        } catch (Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }


    public function sendSms3($phone, $otp): array
    {
        try {
            if (strlen($phone) < 7) {
                throw new Exception('Invalid phone number', 400);
            }


            // Parametrləri URL formatında hazırlayırıq
            $phone = urlencode($phone); // Telefon nömrəsini düzgün kodlayırıq
            Log::info('Phone2:', ['phone2:', $phone]);
            $otpCode = data_get($otp, 'otpCode');

            Log::info('otp:', ['otp:', $otpCode]);
            $msgBody = $this->buildMessage($otpCode);
            $url = $this->url . http_build_query([
                'username'  => $this->user,
                'password'  => $this->pass,
                'numberId'  => $this->numberId,
                'msisdn'    => $phone,
                'msgBody'   => $msgBody,
            ]);

            $response = Http::get($url);
            // logger()->info('Göndərilən URL:', ['url' => $url]);

            $responseBody = $response->body();

            // Log::info('response body:', ['respinse body', $response->body()]);
            return ['status' => true, 'message' => 'SMS sent successfully'];

            if (strpos($responseBody, 'Ok:') !== false) {
                preg_match('/Ok:\s*(\d+);/', $responseBody, $matches);
                $successCode = $matches[1] ?? null;
                logger()->info("Success: " . $successCode);
                return ['status' => true, 'message' => 'SMS sent successfully'];
            } elseif (strpos($responseBody, 'Error:') !== false) {
                preg_match('/Error:\s*(\d+);/', $responseBody, $matches);
                $errorCode = $matches[1] ?? null;
                logger()->info("Error: " . $errorCode);
                return ['status' => false, 'message' => 'SMS failed'];
            } else {
                logger()->info("Response format is not recognized.");
                return ['status' => false, 'message' => 'SMS failed'];
            }
        } catch (Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }
}
