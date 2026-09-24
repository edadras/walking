<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cached balances. Only App\Domain\Wallet\WalletService writes to this table,
 * always in the same DB transaction as the ledger row that explains the change.
 */
class Wallet extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'available_balance' => 'integer',
            'pending_balance' => 'integer',
            'lifetime_earned' => 'integer',
            'lifetime_spent' => 'integer',
            'lifetime_expired' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
