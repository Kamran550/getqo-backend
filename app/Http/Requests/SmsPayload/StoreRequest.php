<?php

namespace App\Http\Requests\SmsPayload;

use App\Http\Requests\BaseRequest;
use App\Models\SmsPayload;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class StoreRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        if (!Cache::get('app.license') || data_get(Cache::get('app.license'), 'active') != 1) {
            abort(403);
        }
        return [
            'type' => [
                'required',
                'string',
                Rule::in(SmsPayload::TYPES),
                Rule::unique('sms_payloads', 'type')->whereNull('deleted_at')
            ],
            'default' => 'nullable|in:0,1',
            'payload' => 'required|array',
            'payload.*' => ['required']
        ];
    }

}
