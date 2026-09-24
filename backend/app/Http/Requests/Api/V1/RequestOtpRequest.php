<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RequestOtpRequest extends FormRequest
{
    public function rules(): array
    {
        return ['phone' => ['required', 'string', 'max:20']];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if (! $validator->errors()->has('phone') && $this->normalizedPhone() === null) {
                $validator->errors()->add('phone', 'شماره موبایل معتبر نیست.');
            }
        }];
    }

    public function normalizedPhone(): ?string
    {
        return Phone::normalize((string) $this->input('phone'));
    }
}
