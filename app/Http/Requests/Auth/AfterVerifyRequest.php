<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class AfterVerifyRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     * @return array
     */
    public function rules(): array
    {
        return [
            'password'  => 'string',
            'email' => 'nullable|email|unique:users,email',
            'firstname' => 'string|min:2|max:100',
            'gender'    => 'in:male,female',
        ];
    }
}
