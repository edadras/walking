<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SponsorTopUp extends Model
{
    use HasPublicId;

    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const LABELS = [self::PENDING => 'در انتظار پرداخت', self::PAID => 'پرداخت شد', self::FAILED => 'ناموفق'];

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return ['amount_rial' => 'integer', 'points' => 'integer', 'price_rial_per_point' => 'integer', 'paid_at' => 'datetime'];
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    public function sponsorUser(): BelongsTo
    {
        return $this->belongsTo(SponsorUser::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function number(): string
    {
        return 'T-'.strtoupper(substr($this->public_id, -6));
    }
}
