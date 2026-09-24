<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePushTokenRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'provider' => ['required', 'in:fcm,pushe'],
            'token' => ['required', 'string', 'max:512'],
        ];
    }
}
