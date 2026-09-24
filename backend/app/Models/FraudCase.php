<?php

namespace App\Models;

use App\Enums\FraudCaseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FraudCase extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => FraudCaseStatus::class, 'decided_at' => 'datetime', 'risk_score' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'decided_by');
    }
}
