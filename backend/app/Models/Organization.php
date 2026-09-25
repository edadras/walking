<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasPublicId;

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return ['seats' => 'integer', 'seat_price_rial' => 'integer', 'paid_until' => 'date', 'departments' => 'array'];
    }

    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function panelUsers(): HasMany
    {
        return $this->hasMany(OrganizationUser::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(OrganizationInvoice::class);
    }

    public function challenges(): HasMany
    {
        return $this->hasMany(Challenge::class);
    }

    /** Active and paid up (the subscription covers today). */
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->paid_until !== null && $this->paid_until->endOfDay()->isFuture();
    }
}
