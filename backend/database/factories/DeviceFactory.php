<?php

namespace Database\Factories;

use App\Domain\Device\RequestSignature;
use App\Enums\DeviceStatus;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Device> */
class DeviceFactory extends Factory
{
    public function definition(): array
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $pem = openssl_pkey_get_details($key)['key'];

        return [
            'install_id' => (string) Str::uuid(),
            'platform' => 'android',
            'os_version' => '14',
            'app_version' => '1.0.0',
            'model' => 'Pixel 8',
            'manufacturer' => 'Google',
            'public_key' => $pem,
            'public_key_fingerprint' => RequestSignature::fingerprint($pem),
            'status' => DeviceStatus::Active,
            'trust_score' => 50,
        ];
    }
}
