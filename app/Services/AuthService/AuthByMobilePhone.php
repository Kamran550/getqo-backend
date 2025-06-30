<?php

namespace App\Services\AuthService;

use App\Helpers\ResponseError;
use App\Http\Resources\UserResource;
use App\Models\Notification;
use App\Models\User;
use App\Services\CoreService;
use App\Services\SMSGatewayService\SMSBaseService;
use App\Services\UserServices\UserWalletService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Throwable;

class AuthByMobilePhone extends CoreService
{
    /**
     * @return string
     */
    protected function getModelClass(): string
    {
        return User::class;
    }

    /**
     * @param array $array
     * @return JsonResponse
     */
    public function authentication(array $array): JsonResponse
    {
        $phone = preg_replace('/\D/', '', data_get($array, 'phone'));

        Log::info('smsserviceden evvel');

        $sms = (new SMSBaseService)->smsGateway($phone);
        Log::info('smsserviceden sonra');

        if (!data_get($sms, 'status')) {

            return $this->onErrorResponse([
                'code'    => ResponseError::ERROR_400,
                'message' => data_get($sms, 'message', '')
            ]);
        }

        return $this->successResponse(__('errors.' . ResponseError::SUCCESS, locale: $this->language), [
            'verifyId'  => data_get($sms, 'verifyId'),
            'phone'     => data_get($sms, 'phone'),
            'message'   => data_get($sms, 'message', '')
        ]);
    }

    /**
     * @param array $array
     * @return JsonResponse
     * @todo REMOVE IN THE FUTURE
     */
    public function confirmOPTCode(array $array): JsonResponse
    {
        if (data_get($array, 'type') !== 'firebase') {
            Log::info('verify:', ['ver:', data_get($array, 'verifyId')]);

            $data = Cache::get('sms-' . data_get($array, 'verifyId'));

            Log::info('menim gonderdiyim:', ['menim gond:', data_get($array, 'verifyId')]);
            Log::info('confirmOPTCode data:', [' confirmOPTCodedata:', $data]);
            Log::info('verify code:', ['verify code:', data_get($array, 'verifyCode')]);
            if (empty($data)) {
                Log::info('confirmOPTCode 1-ci error');
                return $this->onErrorResponse([
                    'code'      => ResponseError::ERROR_404,
                    'message'   => __('errors.' . ResponseError::ERROR_404, locale: $this->language)
                ]);
            }

            Log::info('111111111111111111111111111111111111');
            if (Carbon::parse(data_get($data, 'expiredAt')) < now()) {
                Log::info('expire olub');
                return $this->onErrorResponse([
                    'code'      => ResponseError::ERROR_203,
                    'message'   => __('errors.' . ResponseError::ERROR_203, locale: $this->language)
                ]);
            }

            Log::info("2222222222222222222222222222222222222222");

            if (data_get($data, 'OTPCode') != data_get($array, 'verifyCode')) {
                Log::info(' confirmOPTCode 2 ci error:');
                return $this->onErrorResponse([
                    'code'      => ResponseError::ERROR_201,
                    'message'   => __('errors.' . ResponseError::ERROR_201, locale: $this->language)
                ]);
            }
            Log::info("333333333333333333333333333333333333333333333");

            Log::info('phoneeeeeeeee:', ['phone:', data_get($data, 'phone')]);
            $user = $this->model()->where('phone', data_get($data, 'phone'))->first();
            Log::info('userrrr:', ['userrrr:', $user]);
        } else {

            Log::info('elseye dusdu confirmOPTCode');
            $data['phone']      = data_get($array, 'phone');
            $data['email']      = data_get($array, 'email');
            $data['referral']   = data_get($array, 'referral');
            $data['firstname']  = data_get($array, 'firstname');
            $data['lastname']   = data_get($array, 'lastname');
            $data['password']   = data_get($array, 'password');
            $data['gender']     = data_get($array, 'gender', 'male');
        }

        if (empty($user)) {

            Log::info('4444444444444444444444444444444444444444444');
            try {
                $user = $this->model()
                    ->withTrashed()
                    ->updateOrCreate([
                        'phone'             => data_get($data, 'phone')
                    ], [
                        'phone'             => data_get($data, 'phone'),
                        'email'             => data_get($data, 'email'),
                        'referral'          => data_get($data, 'referral'),
                        'active'            => 1,
                        'phone_verified_at' => now(),
                        'deleted_at'        => null,
                        'firstname'         => data_get($data, 'firstname', 'firstname'),
                        'lastname'          => data_get($data, 'lastname'),
                        'gender'            => data_get($data, 'gender', 'male'),
                        'password'          => bcrypt(data_get($data, 'password', 'password')),
                    ]);

                Log::info('user', ['user:', $user]);
            } catch (Throwable $e) {

                Log::info('user findin catchi');
                $this->error($e);
                Log::info('error bash verdi:', ['error:', $e]);
                return $this->onErrorResponse([
                    'code'    => ResponseError::ERROR_400,
                    'message' => 'Email or phone already exist',
                ]);
            }

            $ids = Notification::pluck('id')->toArray();
            Log::info('ids:', ['ids:', $ids]);
            if ($ids) {
                $user->notifications()->sync($ids);
            } else {
                $user->notifications()->forceDelete();
            }

            Log::info('555555555555555555555555555555');

            $user->emailSubscription()->updateOrCreate([
                'user_id' => $user->id
            ], [
                'active' => true
            ]);
        }

        if (!$user->hasAnyRole(Role::query()->pluck('name')->toArray())) {
            $user->syncRoles('user');
        }

        if (empty($user->wallet?->uuid)) {
            $user = (new UserWalletService)->create($user);
        }

        $token = $user->createToken('api_token')->plainTextToken;

        Cache::forget('sms-' . data_get($array, 'verifyId'));

        return $this->successResponse(__('errors.' . ResponseError::SUCCESS, locale: $this->language), [
            'token' => $token,
            'user'  => UserResource::make($user),
        ]);
    }


    public function confirmOPTCode2(array $array): JsonResponse
    {
        $data = Cache::get('sms-' . data_get($array, 'verifyId'));

        Log::info('menim gonderdiyim:', ['menim gond:', data_get($array, 'verifyCode')]);
        Log::info('confirmOPTCode data:', [' confirmOPTCodedata:', $data]);
        Log::info('verify code:', ['verify code:', data_get($array, 'verifyCode')]);
        if (empty($data)) {
            Log::info('confirmOPTCode 1-ci error');
            return $this->onErrorResponse([
                'code'      => ResponseError::ERROR_404,
                'message'   => __('errors.' . ResponseError::ERROR_404, locale: $this->language)
            ]);
        }

        Log::info('111111111111111111111111111111111111');
        if (Carbon::parse(data_get($data, 'expiredAt')) < now()) {
            Log::info('expire olub');
            return $this->onErrorResponse([
                'code'      => ResponseError::ERROR_203,
                'message'   => __('errors.' . ResponseError::ERROR_203, locale: $this->language)
            ]);
        }

        Log::info("2222222222222222222222222222222222222222");

        if (data_get($data, 'OTPCode') != data_get($array, 'verifyCode')) {
            Log::info(' confirmOPTCode 2 ci error:');
            return $this->onErrorResponse([
                'code'      => ResponseError::ERROR_201,
                'message'   => __('errors.' . ResponseError::ERROR_201, locale: $this->language)
            ]);
        }
        Log::info("333333333333333333333333333333333333333333333");

        Log::info('phoneeeeeeeee:', ['phone:', data_get($data, 'phone')]);

        Cache::forget('sms-' . data_get($array, 'verifyId'));
        Log::info('api token 1');
        Log::info('api token 2');

        return $this->successResponse(__('errors.' . ResponseError::SUCCESS, locale: $this->language));
    }


    public function forgetPasswordVerify(array $data): JsonResponse
    {
        $user = User::withTrashed()->where('phone', str_replace('+', '', data_get($data, 'phone')))->first();

        if (empty($user)) {
            return $this->onErrorResponse(['code' => ResponseError::ERROR_404]);
        }

        if (!empty($user->deleted_at)) {
            $user->update([
                'deleted_at' => null
            ]);
        }

        $token = $user->createToken('api_token')->plainTextToken;

        return $this->successResponse(__('errors.' . ResponseError::SUCCESS, locale: $this->language), [
            'token' => $token,
            'user'  => UserResource::make($user),
        ]);
    }
}
