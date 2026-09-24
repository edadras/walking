<?php

namespace App\Models;

use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * App (mobile) user. Authenticates with phone OTP only; never has a password.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUlids, Notifiable, SoftDeletes;

    protected $fillable = [
        'phone',
        'phone_verified_at',
        'display_name',
        'avatar_path',
        'status',
        'status_reason',
        'referral_code',
        'referred_by_id',
        'timezone',
        'timezone_changed_at',
        'locale',
        'leaderboard_visible',
        'last_active_at',
    ];

    protected $hidden = ['id', 'phone', 'remember_token'];

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
            'status' => UserStatus::class,
            'phone_verified_at' => 'datetime',
            'timezone_changed_at' => 'datetime',
            'last_active_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'leaderboard_visible' => 'boolean',
            'level' => 'integer',
            'xp' => 'integer',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class, 'device_user_links')
            ->withPivot(['first_seen_at', 'last_seen_at']);
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    public function walkingSessions(): HasMany
    {
        return $this->hasMany(WalkingSession::class);
    }

    public function dailyActivities(): HasMany
    {
        return $this->hasMany(DailyActivity::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class);
    }

    public function fraudCases(): HasMany
    {
        return $this->hasMany(FraudCase::class);
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_id');
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /** Masked phone for display in admin lists and the user's own profile, e.g. 0912***4567. */
    public function maskedPhone(): string
    {
        $local = '0'.substr($this->phone, 3);

        return substr($local, 0, 4).'***'.substr($local, -4);
    }

    public function publicName(): string
    {
        return $this->display_name ?: 'کاربر '.substr($this->referral_code, 0, 4);
    }
}
