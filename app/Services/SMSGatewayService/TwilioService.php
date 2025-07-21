<?php

namespace App\Services\SMSGatewayService;

use App\Models\SmsPayload;
use App\Services\CoreService;
use Exception;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class TwilioService extends CoreService
{
    protected function getModelClass(): string
    {
        return SmsPayload::class;
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
            $accountId      = data_get($smsPayload->payload, 'twilio_account_id');
            $authToken      = data_get($smsPayload->payload, 'twilio_auth_token');
            $otpCode        = data_get($otp, 'otpCode');
            $twilioNumber   = data_get($smsPayload->payload, 'twilio_number');

            if (strlen($phone) < 7) {
                throw new Exception('Invalid phone number', 400);
            }

            $client = new Client($accountId, $authToken);
            $client->messages->create("+$phone", [
                'from' => $twilioNumber,
                'body' => "Confirmation code $otpCode"
            ]);

            return ['status' => true, 'message' => 'success'];
        } catch (Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }


    public function whatsapp($phone, $otp, SmsPayload $smsPayload): array
    {
        try {
            $accountId      = data_get($smsPayload->payload, 'twilio_account_id');
            $authToken      = data_get($smsPayload->payload, 'twilio_auth_token');
            $otpCode        = data_get($otp, 'otpCode');
            $twilioNumber   = data_get($smsPayload->payload, 'twilio_number');

            Log::info('accountId:', ['accountId:', $accountId]);
            Log::info('authToken:', ['authToken:', $authToken]);
            if (strlen($phone) < 7) {
                throw new Exception('Invalid phone number', 400);
            }

            $client = new Client($accountId, $authToken);
            if (!str_starts_with($twilioNumber, 'whatsapp:')) {
                $twilioNumber = 'whatsapp:' . $twilioNumber;
            }
            Log::info('twilioNumber:', ['twilioNumber:', $twilioNumber]);


            $client->messages->create(
                "whatsapp:" . $phone,
                [
                    'from' => $twilioNumber,
                    'body' => "Confirmation code $otpCode"
                ]
            );
            LOg::info('whatsapp ile mesaj gonderildi');

            return ['status' => true, 'message' => 'success'];
        } catch (Exception $e) {
            LOg::info('whatsapp ile mesaj error', ['err:', $e]);

            return ['status' => false, 'message' => $e->getMessage()];
        }
    }
}
