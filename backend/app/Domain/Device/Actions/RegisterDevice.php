<?php

namespace App\Domain\Device\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Device\Integrity\IntegrityVerifier;
use App\Domain\Device\TrustScore;
use App\Domain\Settings\Settings;
use App\Enums\DeviceStatus;
use App\Enums\IntegrityVerdict;
use App\Exceptions\ApiException;
use App\Models\Device;
use App\Support\Ip;
use Illuminate\Support\Facades\DB;

class RegisterDevice
{
    public function __construct(
        private readonly IntegrityVerifier $integrity,
        private readonly Settings $settings,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{install_id:string, platform:string, os_version?:?string, app_version?:?string, model?:?string,
     *               manufacturer?:?string, integrity_token?:?string, emulator_suspected?:bool, root_suspected?:bool}  $data
     * @param  string  $publicKeyPem  already normalised and proven (the request was signed with it)
     */
    public function handle(array $data, string $publicKeyPem, string $fingerprint, ?string $ip): Device
    {
        $existing = Device::query()->where('install_id', $data['install_id'])->first();

        if ($existing !== null) {
            if (! hash_equals($existing->public_key_fingerprint, $fingerprint)) {
                // Same install id presenting a different key: either a tampered client or a cloned install.
                $this->audit->log('device.key_conflict', $existing, meta: ['fingerprint' => $fingerprint]);

                throw ApiException::conflict('device_key_conflict', 'این دستگاه قبلاً با کلید دیگری ثبت شده است. لطفاً برنامه را دوباره نصب کنید.');
            }
            if ($existing->status !== DeviceStatus::Active) {
                throw ApiException::forbidden('device_blocked', 'دسترسی این دستگاه محدود شده است. با پشتیبانی تماس بگیرید.');
            }

            $this->refreshMetadata($existing, $data, $ip);

            return $existing;
        }

        $integrity = $this->integrity->verify(
            $data['integrity_token'] ?? null,
            self::integrityRequestHash($data['install_id'], $fingerprint),
        );

        if ($this->settings->bool('security.require_integrity') && ! self::integrityAcceptable($integrity->verdict, $data['store'] ?? null, $data['installer'] ?? null)) {
            throw ApiException::forbidden('integrity_required', 'امکان استفاده از برنامه روی این دستگاه وجود ندارد.');
        }

        return DB::transaction(function () use ($data, $publicKeyPem, $fingerprint, $integrity, $ip) {
            $device = new Device([
                'install_id' => $data['install_id'],
                'platform' => $data['platform'],
                'os_version' => $data['os_version'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'model' => $data['model'] ?? null,
                'manufacturer' => $data['manufacturer'] ?? null,
                'store' => $data['store'] ?? null,
                'installer' => $data['installer'] ?? null,
                'public_key' => $publicKeyPem,
                'public_key_fingerprint' => $fingerprint,
                'integrity_verdict' => $integrity->verdict,
                'integrity_checked_at' => now(),
                'emulator_suspected' => (bool) ($data['emulator_suspected'] ?? false),
                'root_suspected' => (bool) ($data['root_suspected'] ?? false),
                'status' => DeviceStatus::Active,
                'last_seen_at' => now(),
                'last_ip_hash' => Ip::hash($ip),
            ]);
            $device->trust_score = TrustScore::for($device);
            $device->save();

            $this->audit->log('device.registered', $device, new: [
                'platform' => $device->platform,
                'integrity' => $integrity->verdict->value,
                'integrity_reason' => $integrity->reason,
                'trust_score' => $device->trust_score,
            ]);

            return $device;
        });
    }

    /**
     * With `security.require_integrity` on, Play installs must pass Play Integrity.
     * Bazaar, Myket and sideloaded installs often have no Play services at all, so
     * they are only refused on an explicit failing verdict. Claiming a non-Play
     * store doesn't buy trust: those devices stay "unavailable" and the Fraud
     * Engine caps their daily rewarded steps (DeviceIntegrityRule).
     */
    public static function integrityAcceptable(IntegrityVerdict $verdict, ?string $store, ?string $installer): bool
    {
        $fromPlay = $store === 'play' || $installer === 'com.android.vending';

        return $fromPlay
            ? in_array($verdict, [IntegrityVerdict::Strong, IntegrityVerdict::Device], true)
            : $verdict !== IntegrityVerdict::None;
    }

    /** The nonce the client must pass to Play Integrity: binds the token to this install and key. */
    public static function integrityRequestHash(string $installId, string $fingerprint): string
    {
        return hash('sha256', $installId.'|'.$fingerprint);
    }

    private function refreshMetadata(Device $device, array $data, ?string $ip): void
    {
        $device->fill([
            'os_version' => $data['os_version'] ?? $device->os_version,
            'app_version' => $data['app_version'] ?? $device->app_version,
            'last_seen_at' => now(),
            'last_ip_hash' => Ip::hash($ip),
        ]);
        // Client-side risk flags can only ever be raised, never cleared, by the client.
        $device->emulator_suspected = $device->emulator_suspected || (bool) ($data['emulator_suspected'] ?? false);
        $device->root_suspected = $device->root_suspected || (bool) ($data['root_suspected'] ?? false);
        $device->trust_score = TrustScore::for($device);
        $device->save();
    }
}
