<?php

namespace App\Services\SMSGatewayService;

use App\Models\Settings;
use App\Models\SmsGateway;
use App\Models\SmsPayload;
use App\Services\CoreService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;


class SMSBaseService extends CoreService
{
    /**
     * @return string
     */
    protected function getModelClass(): string
    {
        return SmsGateway::class;
    }

    /**
     * @param $phone
     * @return array
     */
    public function smsGateway($phone): array
    {
        $otp = $this->setOTP();
        Log::info('ooottp:', ['otp:', $otp]);

        $smsPayload = SmsPayload::where('type', SmsPayload::SMILESMS)->first();


        Log::info('sms:', ['pay:', $smsPayload]);
        $result = ['status' => false, 'message' => 'sms is not configured!'];

        if ($smsPayload?->type === SmsPayload::FIREBASE) {

            $result = (new TwilioService)->sendSms($phone, $otp, $smsPayload);
        } else if ($smsPayload?->type === SmsPayload::SMILESMS) {
            Log::info('smile sms ile ');
            Log::info('phone:', ['phone:', $phone]);
            Log::info('smile sms ile 222222222222222222222222');
            $result = (new SmileSmsService)->sendSms2($phone, $otp, $smsPayload);
            Log::info('res:', ['res:', $result]);
        }


        if (data_get($result, 'status')) {


            $this->setOTPToCache($phone, $otp);

            return [
                'status' => true,
                'verifyId' => data_get($otp, 'verifyId'),
                'phone' => Str::mask($phone, '*', -12, 8),
                'message' => data_get($result, 'message', ''),
            ];
        }

        return ['status' => false, 'message' => data_get($result, 'message')];
    }
    public function smsGateway2($phone, $token): array
    {
        $otp = $this->setOTP();
        Log::info('ooottp:', ['otp:', $otp]);

        $smsPayload = SmsPayload::where('type', SmsPayload::SMILESMS)->first();

        Log::info('sms:', ['pay:', $smsPayload]);
        $result = ['status' => false, 'message' => 'sms is not configured!'];

        if ($smsPayload?->type === SmsPayload::FIREBASE) {

            $result = (new TwilioService)->sendSms($phone, $otp, $smsPayload);
        } else if ($smsPayload?->type === SmsPayload::SMILESMS) {
            Log::info('smile sms ile ');
            Log::info('phone:', ['phone:', $phone]);
            Log::info('smile sms ile 222222222222222222222222');
            $result = (new SmileSmsService)->sendSms3($phone, $token, $smsPayload);
            Log::info('res:', ['res:', $result]);
        }


        if (data_get($result, 'status')) {


            $this->setOTPToCache($phone, $otp);

            return [
                'status' => true,
                'verifyId' => data_get($otp, 'verifyId'),
                'phone' => Str::mask($phone, '*', -12, 8),
                'message' => data_get($result, 'message', ''),
            ];
        }

        return ['status' => false, 'message' => data_get($result, 'message')];
    }


    public function resendWhatsapp($phone): array
    {
        $otp = $this->setOTP();

        $smsPayload = SmsPayload::where('type', SmsPayload::WHATSAPP)->first();

        $result = ['status' => false, 'message' => 'sms is not configured!'];

        if ($smsPayload?->type === SmsPayload::WHATSAPP) {
            $result = (new TwilioService)->whatsapp($phone, $otp, $smsPayload);
        }
        if (data_get($result, 'status')) {


            $this->setOTPToCache($phone, $otp);

            return [
                'status' => true,
                'verifyId' => data_get($otp, 'verifyId'),
                'phone' => Str::mask($phone, '*', -12, 8),
                'message' => data_get($result, 'message', ''),
            ];
        }

        return ['status' => false, 'message' => data_get($result, 'message')];
    }


    public function setOTP(): array
    {
        // return ['verifyId' => Str::uuid(), 'otpCode' => rand(100000, 999999)];
        return ['verifyId' => Str::uuid(), 'otpCode' => 222222];
    }

    public function setOTPToCache($phone, $otp)
    {
        $verifyId  = data_get($otp, 'verifyId');
        $expiredAt = Settings::where('key', 'otp_expire_time')->first()?->value;
        Log::info('setOTPToCache:', ['setOTPToCache otpcode:', data_get($otp, 'otpCode')]);
        Cache::put("sms-$verifyId", [
            'phone'     => $phone,
            'verifyId'  => $verifyId,
            'OTPCode'   => data_get($otp, 'otpCode'),
            'expiredAt' => now()->addMinutes($expiredAt >= 1 ? $expiredAt : 10),
        ], 1800);
    }
}
