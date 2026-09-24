<?php

namespace App\Models;

use App\Enums\SponsorRole;
use App\Models\Concerns\HasTotp;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

class SponsorUser extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasName
{
    use HasTotp;

    protected $fillable = ['sponsor_id', 'name', 'email', 'phone', 'password', 'role', 'is_active', 'last_login_at'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /** Mirrors the column defaults so freshly created models are complete. */
    protected $attributes = ['is_active' => true, 'role' => 'owner'];

    protected function casts(): array
    {
        return [
            'role' => SponsorRole::class,
            'is_active' => 'boolean',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'last_login_at' => 'datetime',
        ];
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'sponsor' && $this->is_active && $this->sponsor !== null && $this->sponsor->deleted_at === null;
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    /** Role ability AND an approved sponsor (pending sponsors only see their status). */
    public function hasAbility(string $ability): bool
    {
        return $this->is_active && $this->sponsor?->isApproved() && $this->role->can($ability);
    }
}
