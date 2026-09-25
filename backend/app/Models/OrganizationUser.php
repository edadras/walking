<?php

namespace App\Models;

use App\Models\Concerns\HasTotp;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

/** HR / wellness staff of a company. `viewer` sees reports only; `admin` also manages members, challenges and billing. */
class OrganizationUser extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasName
{
    use HasTotp;

    protected $fillable = ['organization_id', 'name', 'email', 'password', 'role', 'is_active', 'last_login_at'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected $attributes = ['is_active' => true, 'role' => 'admin'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'last_login_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'org' && $this->is_active && $this->organization?->status === 'active';
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
