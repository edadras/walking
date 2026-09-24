<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Ledger row. Amount, type, owner and source are immutable after insert; the
 * only allowed change is the pending → completed/reversed transition (plus the
 * balance snapshot taken at that moment). Rows are never deleted.
 */
class PointTransaction extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    private const MUTABLE = ['status', 'balance_before', 'balance_after', 'completed_at', 'reversed_at', 'meta'];

    protected $guarded = ['id', 'public_id'];

    protected $hidden = ['id', 'user_id'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'status' => TransactionStatus::class,
            'amount' => 'integer',
            'balance_before' => 'integer',
            'balance_after' => 'integer',
            'rial_rate' => 'integer',
            'meta' => 'json',
            'available_at' => 'datetime',
            'completed_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (PointTransaction $t) {
            $illegal = array_diff(array_keys($t->getDirty()), self::MUTABLE);
            if ($illegal !== []) {
                throw new LogicException('Ledger fields are immutable: '.implode(', ', $illegal));
            }
            if ($t->getOriginal('status') !== TransactionStatus::Pending && $t->isDirty('status')) {
                throw new LogicException('Only pending transactions can change status.');
            }
        });
        static::deleting(fn () => throw new LogicException('Ledger rows cannot be deleted.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
