<?php

namespace App\Models;

use App\Enums\AdminRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable implements FilamentUser, HasName
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'is_active', 'last_login_at'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    protected function casts(): array
    {
        return [
            'role' => AdminRole::class,
            'is_active' => 'boolean',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'last_login_at' => 'datetime',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && $this->is_active;
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function hasAbility(string $ability): bool
    {
        return $this->is_active && $this->role->can($ability);
    }
}
