<?php

namespace App\Services\BenefitsService;

use App\Helpers\ResponseError;
use App\Models\Benefit;
use App\Services\CoreService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class BenefitsService extends CoreService
{
    protected function getModelClass(): string
    {
        return Benefit::class;
    }

    public function create(array $data): \Illuminate\Contracts\Validation\Validator|\Illuminate\Validation\Validator|array
    {
        Log::info('data:', ['data:', $data]);
        $prepareValidate = $this->prepareValidate($data);

        Log::info('validateden kecdi');
        if (!data_get($prepareValidate, 'status')) {
            Log::info('ife girdi');
            return $prepareValidate;
        }


        Log::info('5555555555555555555555555555555');
        try {
            Log::info('666666666666666666666666666666');

            $payload = $this->model()->create($data);

            return [
                'status'    => true,
                'code'      => ResponseError::NO_ERROR,
                'data'      => $payload,
            ];
        } catch (Throwable $e) {
            LOg::info('error:', ['err:', $e]);
            $this->error($e);
        }

        return [
            'status'  => false,
            'code'    => ResponseError::ERROR_501,
            'message' => __('errors.' . ResponseError::ERROR_501, locale: $this->language)
        ];
    }

    public function update(string $benefitType, array $data): array
    {
        try {
            $data['type'] = $benefitType;
            Log::info('type:', ['type:', $benefitType]);

            $prepareValidate = $this->prepareValidate($data);

            if (!data_get($prepareValidate, 'status')) {
                return $prepareValidate;
            }

            $payload = Benefit::where('type', $benefitType)->firstOrFail();
            Log::info('updateddd payload:', ['p:', $payload]);

            // if ((int)data_get($data, 'default') === 1) {
            //     Benefit::where('default', 1)
            //         ->where('type', '!=', $payload?->type)
            //         ->get()
            //         ->map(function (Benefit $payload) {
            //             $payload->update([
            //                 'default' => 0
            //             ]);
            //         });
            // }
            Log::info('updateddd data:', ['d:', $data]);

            $payload->update($data);

            return [
                'status'    => true,
                'code'      => ResponseError::NO_ERROR,
                'data'      => $payload,
            ];
        } catch (Throwable $e) {
            $this->error($e);
        }

        return [
            'status'  => false,
            'code'    => ResponseError::ERROR_502,
            'message' => __('errors.' . ResponseError::ERROR_502, locale: $this->language)
        ];
    }

    public function delete(?array $ids = []): array
    {
        $payloads = Benefit::whereIn('type', is_array($ids) ? $ids : [])->get();

        foreach ($payloads as $payload) {
            $payload->delete();
        }

        return [
            'status' => true,
            'code'   => ResponseError::NO_ERROR,
        ];
    }


    public function prepareValidate($data): array
    {
        if (data_get($data, 'type') === Benefit::FREE_DELIVERY_COUNT) {

            $validator = $this->freeDeliveryCount($data);

            if ($validator->fails()) {
                return [
                    'status' => false,
                    'code'   => ResponseError::ERROR_422,
                    'params' => $validator->errors()->toArray(),
                ];
            }

            return ['status' => true];
        } else if (data_get($data, 'type') === Benefit::FREE_DELIVERY_DISTANCE) {
            $validator = $this->freeDeliveryDistance($data);

            if ($validator->fails()) {
                return [
                    'status' => false,
                    'code'   => ResponseError::ERROR_422,
                    'params' => $validator->errors()->toArray(),
                ];
            }

            return ['status' => true];
        }


        return [
            'status'    => false,
            'code'      => ResponseError::ERROR_404,
            'message'   => 'Validation error',
        ];
    }

    public function freeDeliveryCount(array $data): \Illuminate\Contracts\Validation\Validator|\Illuminate\Validation\Validator
    {

        Log::info('3 cu validate', ['arr:', $data]);
        return Validator::make($data, [
            'payload.count' => ['required', 'integer', 'min:1'],
            'payload.day' => ['required', 'integer', 'min:1'],
            'payload.target_type' => ['required', 'in:shop,restaurant,all'],
        ]);
    }

    public function freeDeliveryDistance(array $data): \Illuminate\Contracts\Validation\Validator|\Illuminate\Validation\Validator
    {

        Log::info('3 cu validate', ['arr:', $data]);
        return Validator::make($data, [
            'payload.km' => ['required', 'numeric'],
        ]);
    }






    /**
     * @param array $data
     * @return \Illuminate\Contracts\Validation\Validator|\Illuminate\Validation\Validator
     */
    public function firebase(array $data): \Illuminate\Contracts\Validation\Validator|\Illuminate\Validation\Validator
    {
        return Validator::make($data, [
            'payload.api_key'           => ['required', 'string'],
            'payload.ios_api_key'       => ['required', 'string'],
            'payload.android_api_key'   => ['required', 'string'],
            'payload.server_key'        => ['required', 'string'],
            'payload.vapid_key'         => ['required', 'string'],
            'payload.auth_domain'       => ['required', 'string'],
            'payload.project_id'        => ['required', 'string'],
            'payload.storage_bucket'    => ['required', 'string'],
            'payload.message_sender_id' => ['required', 'string'],
            'payload.app_id'            => ['required', 'string'],
            'payload.measurement_id'    => ['required', 'string'],
        ]);
    }

    /**
     * @param array $data
     * @return \Illuminate\Contracts\Validation\Validator|\Illuminate\Validation\Validator
     */
    public function twilio(array $data): \Illuminate\Contracts\Validation\Validator|\Illuminate\Validation\Validator
    {
        return Validator::make($data, [
            'payload.twilio_account_id' => ['required', 'string'],
            'payload.twilio_auth_token' => ['required', 'string'],
            'payload.twilio_number'     => ['required', 'string'],
        ]);
    }
}
