<?php

namespace App\Http\Controllers\API\v1\Auth;

use App\Events\Mails\SendEmailVerification;
use App\Helpers\ResponseError;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AfterVerifyRequest;
use App\Http\Requests\Auth\PhoneVerifyRequest;
use App\Http\Requests\Auth\ReSendVerifyRequest;
use App\Http\Resources\UserResource;
use App\Models\Notification;
use App\Models\PushNotification;
use App\Models\User;
use App\Services\AuthService\AuthByMobilePhone;
use App\Services\UserServices\UserWalletService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class VerifyAuthController extends Controller
{
    use ApiResponse, \App\Traits\Notification;

    public function verifyPhone(PhoneVerifyRequest $request): JsonResponse
    {
        Log::info('all body:', ['all:', $request->all()]);
        return (new AuthByMobilePhone)->confirmOPTCode($request->all());
    }

    public function resendVerify(ReSendVerifyRequest $request): JsonResponse
    {
        $user = User::where('email', $request->input('email'))
            ->whereNotNull('verify_token')
            ->whereNull('email_verified_at')
            ->first();

        if (!$user) {
            return $this->onErrorResponse([
                'code'    => ResponseError::ERROR_404,
                'message' => __('errors.' . ResponseError::USER_NOT_FOUND, locale: $this->language)
            ]);
        }

        event((new SendEmailVerification($user)));

        return $this->successResponse(ResponseError::ERROR_216);
    }

    public function verifyEmail(?string $verifyToken): JsonResponse
    {
        $user = User::withTrashed()->where('verify_token', $verifyToken)
            ->whereNull('email_verified_at')
            ->first();

        if (empty($user)) {
            return $this->onErrorResponse([
                'code'    => ResponseError::ERROR_404,
                'message' => __('errors.' . ResponseError::ERROR_404, locale: $this->language)
            ]);
        }

        try {
            $user->update([
                'email_verified_at' => now(),
                'deleted_at'        => null,
            ]);

            return $this->successResponse(__('errors.' . ResponseError::SUCCESS, locale: $this->language), [
                'email' => $user->email
            ]);
        } catch (Throwable $e) {
            $this->error($e);
            return $this->onErrorResponse(['code' => ResponseError::ERROR_501]);
        }
    }

    public function afterVerifyEmail(AfterVerifyRequest $request): JsonResponse
    {


        // $user = User::where('email', $request->input('email'))
        //     //            ->where('verify_token',  $request->input('verify_token'))
        //     ->first();



        $phone = preg_replace('/\D/', '', $request->input('phone'));

        $user = User::where('phone', $phone)
            //            ->where('verify_token',  $request->input('verify_token'))
            ->first();
        Log::info('User query result:', ['user' => $user]);

        if (empty($user)) {
            Log::info('empty user');
            return $this->onErrorResponse([
                'code'      => ResponseError::ERROR_404,
                'message'   => __('errors.' . ResponseError::ERROR_404, locale: $this->language)
            ]);
        }

        Log::info('111111111111111111111111111111111111');

        // $user->update([
        //     'email' => $request->input('email'),
        //     'firstname' => $request->input('firstname', $user->email),
        //     'lastname'  => $request->input('lastname', $user->lastname),
        //     'referral'  => $request->input('referral', $user->referral),
        //     'gender'    => $request->input('gender', 'male'),
        //     'password'  => bcrypt($request->input('password', 'password')),
        // ]);


        $user->update([
            'email' => $request->input('email'),
            'firstname' => $request->input('firstname'),
            'lastname'  => $request->input('lastname'),
            'referral'  => $request->input('referral'),
            'gender'    => $request->input('gender'),
            'password'  => bcrypt($request->input('password', 'password')),
        ]);


        Log::info('22222222222222222222222222222222222222222222');

        $referral = User::where('my_referral', $request->input('referral', $user->referral))
            ->first();

        Log::info('referal:', ['ref' => $referral]);

        if (!empty($referral) && !empty($referral->firebase_token)) {

            Log::info('333333333333333333333333333333333333333333');
            $this->sendNotification(
                is_array($referral->firebase_token) ? $referral->firebase_token : [$referral->firebase_token],
                "Congratulations! By your referral registered new user. $user->name_or_email",
                $referral->id,
                [
                    'id'   => $referral->id,
                    'type' => PushNotification::NEW_USER_BY_REFERRAL
                ],
                [$referral->id]
            );
        }

        Log::info('44444444444444444444444444444444444444444444444444444');
        $id = Notification::where('type', Notification::PUSH)->select(['id', 'type'])->first()?->id;

        if ($id) {
            $user->notifications()->sync([$id]);
        } else {
            $user->notifications()->forceDelete();
        }

        $user->emailSubscription()->updateOrCreate([
            'user_id' => $user->id
        ], [
            'active' => true
        ]);

        if (empty($user->wallet?->uuid)) {
            $user = (new UserWalletService)->create($user);
        }

        $token = $user->createToken('api_token')->plainTextToken;

        return $this->successResponse(
            __('errors.' . ResponseError::USER_SUCCESSFULLY_REGISTERED, locale: $this->language),
            ['token' => $token, 'user'  => UserResource::make($user)]
        );
    }
}
