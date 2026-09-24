<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Digits;

class VerifyOtpRequest extends RequestOtpRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['code' => Digits::toLatin((string) $this->input('code'))]);
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'code' => ['required', 'digits_between:4,8'],
            'timezone' => ['nullable', 'timezone:all'],
            'referral_code' => ['nullable', 'string', 'max:12', 'alpha_num'],
        ];
    }
}
