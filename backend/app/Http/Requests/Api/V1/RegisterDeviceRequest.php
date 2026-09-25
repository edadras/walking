<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class RegisterDeviceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'install_id' => ['required', 'uuid'],
            'platform' => ['required', 'in:android,ios'],
            'os_version' => ['nullable', 'string', 'max:32'],
            'app_version' => ['nullable', 'string', 'max:32', 'regex:/^\d+\.\d+\.\d+/'],
            'model' => ['nullable', 'string', 'max:64'],
            'manufacturer' => ['nullable', 'string', 'max:64'],
            'public_key' => ['required', 'string', 'max:2048'],
            'integrity_token' => ['nullable', 'string', 'max:8192'],
            'emulator_suspected' => ['sometimes', 'boolean'],
            'root_suspected' => ['sometimes', 'boolean'],
            'store' => ['nullable', 'in:play,bazaar,myket,direct'],
            'installer' => ['nullable', 'string', 'max:128', 'regex:/^[A-Za-z0-9_.]+$/'],
        ];
    }
}
