<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationInvoice extends Model
{
    use HasPublicId;

    public const LABELS = ['pending' => 'در انتظار پرداخت', 'paid' => 'پرداخت شد', 'failed' => 'ناموفق'];

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return ['amount_rial' => 'integer', 'seats' => 'integer', 'months' => 'integer', 'period_from' => 'date', 'period_to' => 'date', 'paid_at' => 'datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function number(): string
    {
        return 'B-'.strtoupper(substr($this->public_id, -6));
    }
}
