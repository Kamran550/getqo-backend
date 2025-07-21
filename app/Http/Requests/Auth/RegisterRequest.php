<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;
use Arr;
use Illuminate\Validation\Rule;

class RegisterRequest extends BaseRequest
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
            'password'  => 'string',
            'email'     => [
                'email',
                Rule::unique('users', 'email')->whereNotNull('email_verified_at')
            ],
            'firstname' => 'string|min:2|max:100',
            'referral'  => 'string|exists:users,my_referral|max:255',
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
