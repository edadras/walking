<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Digits;

class VerifyOtpRequest extends RequestOtpRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['code' => Digits::toLatin((string) $this->input('code'))]);

        // The device timezone is only a hint for new accounts. Legacy aliases ("GMT", "Etc/UTC")
        // and garbage are dropped so they can never block sign-in; the server then uses Asia/Tehran.
        $tz = $this->input('timezone');
        if ($tz !== null && ! (is_string($tz) && in_array($tz, \DateTimeZone::listIdentifiers(), true))) {
            $this->merge(['timezone' => null]);
        }
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
