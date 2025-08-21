<?php

namespace App\Traits;

use App\Helpers\ResponseError;
use App\Models\Order;
use App\Models\PushNotification;
use App\Models\Settings;
use App\Models\User;
use App\Services\PushNotificationService\PushNotificationService;
use Cache;
use Google\Client;
use Illuminate\Support\Facades\Http;
use Log;
use Throwable;

/**
 * App\Traits\Notification
 *
 * @property string $language
 */
trait Notification
{
    public function sendNotification(
        array   $receivers = [],
        ?string $message = '',
        ?string $title = null,
        mixed   $data = [],
        array   $userIds = [],
        ?string $firebaseTitle = '',
    ): void {
        // dispatch sənin sistemində şərhə alınıb; hazırda sync göndəririk.
        if (empty($receivers)) {
            return;
        }

        // ----- BİLDİRİŞ TİPİNİ MÜƏYYƏN ET -----
        // type bir neçə formada gələ bilər: data['type'], data['order']['type'] və s.
        $notifType = (string) (
            data_get($data, 'type') ??
            data_get($data, 'order.type') ??
            ''
        );

        // Hansı tiplərdə bizim səs çalsın? Buraya ehtiyacına görə əlavə edə bilərsən.
        $useCustomSound = in_array($notifType, [
            PushNotification::NEW_ORDER,
            PushNotification::NEW_PARCEL_ORDER ?? 'NEW_PARCEL_ORDER', // varsa
            PushNotification::NEW_IN_TABLE ?? 'NEW_IN_TABLE',         // səndə masada yeni müştəri
            PushNotification::CALL_WAITER ?? 'CALL_WAITER',           // ofisiant çağırma və s.
        ], true);

        // Log məqsədli (istəsən saxla)
        Log::error(is_array($userIds) && count($userIds) > 0, [
            'type'  => $notifType ?: data_get($data, 'type'),
            'title' => $title,
            'body'  => $message,
            'data'  => $data,
            'sound' => $useCustomSound ? 'custom:new_order' : 'default',
        ]);

        // DB-yə də eyni səs statusunu yazmaq istəyirsənsə (opsional)
        if (is_array($userIds) && count($userIds) > 0) {
            (new PushNotificationService)->storeMany([
                'type'  => $notifType ?: data_get($data, 'type'),
                'title' => $title,
                'body'  => $message,
                'data'  => $data,
                'sound' => $useCustomSound ? 'custom:new_order' : 'default',
            ], $userIds);
        }

        // FCM HTTP v1 endpoint
        $url   = "https://fcm.googleapis.com/v1/projects/{$this->projectId()}/messages:send";
        $token = $this->updateToken();

        $headers = [
            'Authorization' => "Bearer $token",
            'Content-Type'  => 'application/json'
        ];

        // ----- ANDROID/APNS BLOKLARINI TYPE-ə GÖRƏ QUR -----
        $androidBlock = $useCustomSound
            ? [
                'priority'     => 'HIGH',
                'notification' => [
                    'channel_id' => 'new_orders', // Flutter-də yaradılan kanal
                    'sound'      => 'new_order',  // res/raw/new_order.mp3
                ],
              ]
            : [
                'notification' => [
                    'sound' => 'default',
                ]
              ];

        $apnsBlock = $useCustomSound
            ? [
                'headers' => [
                    'apns-push-type' => 'alert',
                    'apns-priority'  => '10',
                ],
                'payload' => [
                    'aps' => [
                        'sound'              => 'new_order.caf',     // ios/Runner/new_order.caf
                        'interruption-level' => 'time-sensitive',    // (ops.) Focus rejimlərində daha görünən
                        'badge'              => 1,
                    ],
                ],
              ]
            : [
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                    ]
                ]
              ];

        foreach ($receivers as $receiver) {
            try {
                // Closure-da dəyişənləri istifadə etmək üçün use-lə ötürürük
                dispatch(function () use ($receiver, $message, $title, $data, $firebaseTitle, $headers, $url, $androidBlock, $apnsBlock) {

                    if (empty($receiver)) {
                        return;
                    }

                    $payload = [
                        'message' => [
                            'token' => $receiver,

                            // Sistem banner mətni
                            'notification' => [
                                'title' => $firebaseTitle ?? $title,
                                'body'  => $message,
                            ],

                            // App-in özündə emal üçün data (istəyə görə artır)
                            'data' => [
                                'id'     => (string) (data_get($data, 'id') ?? ''),
                                'status' => (string) (data_get($data, 'status') ?? ''),
                                'type'   => (string) (data_get($data, 'type') ?? data_get($data, 'order.type') ?? ''),
                            ],

                            // *** ƏSAS DƏYİŞİKLİK: platform blokları ***
                            'android' => $androidBlock,
                            'apns'    => $apnsBlock,
                        ]
                    ];

                    $resp = Http::withHeaders($headers)->post($url, $payload);

                    Log::error($resp->status(), [$receiver]);

                })->afterResponse();

            } catch (Throwable $e) {
                Log::error('catch ' . $e->getMessage());
            }
        }
    }

    public function sendAllNotification(?string $title = null, mixed $data = [], ?string $firebaseTitle = ''): void
    {
        dispatch(function () use ($title, $data, $firebaseTitle) {

            User::select([
                'id',
                'deleted_at',
                'active',
                'email_verified_at',
                'phone_verified_at',
                'firebase_token',
            ])
                ->where('active', 1)
                ->where(fn($q) => $q->whereNotNull('email_verified_at')->orWhereNotNull('phone_verified_at'))
                ->whereNotNull('firebase_token')
                ->orderBy('id')
                ->chunk(100, function ($users) use ($title, $data, $firebaseTitle) {

                    $firebaseTokens = $users?->pluck('firebase_token', 'id')?->toArray();

                    $receives = [];

                    foreach ($firebaseTokens as $firebaseToken) {
                        if (empty($firebaseToken)) {
                            continue;
                        }
                        $receives[] = array_filter($firebaseToken, fn($item) => !empty($item));
                    }

                    $receives = array_merge(...$receives);

                    $this->sendNotification(
                        $receives,
                        $title,
                        data_get($data, 'id'),
                        $data,
                        array_keys(is_array($firebaseTokens) ? $firebaseTokens : []),
                        $firebaseTitle
                    );
                });
        })->afterResponse();
    }

    private function updateToken(): string
    {
        $googleClient = new Client;
        $googleClient->setAuthConfig(storage_path('app/google-service-account.json'));
        $googleClient->addScope('https://www.googleapis.com/auth/firebase.messaging');

        $token = $googleClient->fetchAccessTokenWithAssertion()['access_token'];

        return Cache::remember('firebase_auth_token', 300, fn() => $token);
    }

    public function newOrderNotification(Order $order): void
    {
        $adminFirebaseTokens = User::with(['roles' => fn($q) => $q->where('name', 'admin')])
            ->whereHas('roles', fn($q) => $q->where('name', 'admin'))
            ->whereNotNull('firebase_token')
            ->pluck('firebase_token', 'id')
            ->toArray();

        $sellersFirebaseTokens = User::with([
            'shop' => fn($q) => $q->where('id', $order->shop_id)
        ])
            ->whereHas('shop', fn($q) => $q->where('id', $order->shop_id))
            ->whereNotNull('firebase_token')
            ->pluck('firebase_token', 'id')
            ->toArray();

        $aTokens = [];
        $sTokens = [];

        foreach ($adminFirebaseTokens as $adminToken) {
            $aTokens = array_merge($aTokens, is_array($adminToken) ? array_values($adminToken) : [$adminToken]);
        }

        foreach ($sellersFirebaseTokens as $sellerToken) {
            $sTokens = array_merge($sTokens, is_array($sellerToken) ? array_values($sellerToken) : [$sellerToken]);
        }

        // *** DƏYİŞİKLİK: Burada 'data' daxilində TYPE-ı MÜTLƏQ ötürürük ki, yuxarıdakı səs məntiqi işə düşsün
        $data = [
            'id'            => $order->id,
            'status'        => $order->status,
            'delivery_type' => $order->delivery_type,
            'type'          => PushNotification::NEW_ORDER, // ⬅️ TYPE əlavə edildi
        ];

        $this->sendNotification(
            array_values(array_unique(array_merge($aTokens, $sTokens))),
            __('errors.' . ResponseError::NEW_ORDER, ['id' => $order->id], $this->language),
            $order->id,
            $data,
            array_merge(array_keys($adminFirebaseTokens), array_keys($sellersFirebaseTokens))
        );
    }

    private function projectId()
    {
        return Settings::where('key', 'project_id')->value('value');
    }
}
