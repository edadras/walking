<?php

namespace App\Models;

use App\Enums\DeviceStatus;
use App\Enums\IntegrityVerdict;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * One app installation. Identified by a server-issued public_id and bound to an
 * EC P-256 key generated inside the Android Keystore.
 */
class Device extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'install_id',
        'platform',
        'os_version',
        'app_version',
        'model',
        'manufacturer',
        'store',
        'installer',
        'public_key',
        'public_key_fingerprint',
        'key_attested',
        'integrity_verdict',
        'integrity_checked_at',
        'emulator_suspected',
        'root_suspected',
        'trust_score',
        'status',
        'push_provider',
        'push_token',
        'last_seen_at',
        'last_ip_hash',
    ];

    protected $hidden = ['id', 'public_key', 'push_token', 'last_ip_hash'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected function casts(): array
    {
        return [
            'status' => DeviceStatus::class,
            'integrity_verdict' => IntegrityVerdict::class,
            'integrity_checked_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'key_attested' => 'boolean',
            'key_version' => 'integer',
            'key_rotated_at' => 'datetime',
            'key_rotation_required' => 'boolean',
            'emulator_suspected' => 'boolean',
            'root_suspected' => 'boolean',
            'trust_score' => 'integer',
            'last_sequence' => 'integer',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'device_user_links')
            ->withPivot(['first_seen_at', 'last_seen_at']);
    }

    public function isActive(): bool
    {
        return $this->status === DeviceStatus::Active;
    }
}
