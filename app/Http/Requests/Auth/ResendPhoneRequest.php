<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;
use Arr;
use Illuminate\Validation\Rule;

class ResendPhoneRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     * @return array
     */
    public function rules(): array
    {
        return [
            'phone'     => [
                'numeric',
                Rule::unique('users', 'phone')->whereNotNull('phone_verified_at')
            ],
        ];
    }
    public function messages(): array
    {
        return [
            'phone.required' => 'Phone number is required.',
            'phone.unique'   => 'Phone is already taken...',
        ];
    }
}
