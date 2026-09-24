<?php

namespace App\Domain\Device\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Device\RequestSignature;
use App\Exceptions\ApiException;
use App\Models\Device;
use Illuminate\Support\Facades\DB;

/**
 * Replaces a device's Keystore key. The request itself is signed with the
 * CURRENT key (proving possession), and carries a proof signed with the NEW
 * key over a statement binding it to this device and moment:
 *
 *   "gamyar-key-rotation\n{device_public_id}\n{X-Timestamp}\n{sha256hex(new public key DER)}"
 *
 * so a leaked old key alone can't install an attacker's key without also
 * holding the new private key, and the proof can't be replayed on another device.
 */
class RotateDeviceKey
{
    public function __construct(private readonly AuditLogger $audit) {}

    public static function statement(string $devicePublicId, string $timestamp, string $newKeyDerBase64): string
    {
        return "gamyar-key-rotation\n{$devicePublicId}\n{$timestamp}\n".hash('sha256', (string) base64_decode($newKeyDerBase64, true));
    }

    public function handle(Device $device, string $newPublicKey, string $proof, string $timestamp): Device
    {
        $pem = RequestSignature::normalizePublicKey($newPublicKey);
        if ($pem === null) {
            throw ApiException::unprocessable('invalid_public_key', 'کلید نامعتبر است.');
        }
        if (! RequestSignature::verify($pem, self::statement($device->public_id, $timestamp, $newPublicKey), $proof)) {
            throw ApiException::unprocessable('invalid_proof', 'امضای کلید جدید معتبر نیست.');
        }
        $fingerprint = RequestSignature::fingerprint($pem);
        if (Device::query()->where('public_key_fingerprint', $fingerprint)->exists()) {
            throw ApiException::conflict('key_in_use', 'این کلید قبلاً ثبت شده است.');
        }

        return DB::transaction(function () use ($device, $pem, $fingerprint) {
            $old = $device->public_key_fingerprint;
            $device->forceFill([
                'public_key' => $pem,
                'public_key_fingerprint' => $fingerprint,
                'key_version' => $device->key_version + 1,
                'key_rotated_at' => now(),
                'key_rotation_required' => false,
                'key_attested' => false,
            ])->save();
            $this->audit->log('device.key_rotated', $device, ['fingerprint' => $old], ['fingerprint' => $fingerprint, 'key_version' => $device->key_version]);

            return $device;
        });
    }
}
