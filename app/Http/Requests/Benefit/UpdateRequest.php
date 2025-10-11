<?php

namespace App\Http\Requests\Benefit;

use App\Http\Requests\BaseRequest;
use Illuminate\Support\Facades\Cache;

class UpdateRequest extends BaseRequest
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
            'default'   => 'required|in:0,1',
            'payload'   => 'required|array',
            'payload.*' => ['required']
        ];
    }

}
