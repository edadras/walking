<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Support\PiiHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use HasPublicId, SoftDeletes;

    public const PENDING = 'pending';

    public const VERIFIED = 'verified';

    public const REJECTED = 'rejected';

    protected $guarded = ['id', 'public_id'];

    protected $hidden = ['id', 'iban', 'iban_hash'];

    protected function casts(): array
    {
        return ['iban' => 'encrypted', 'reviewed_at' => 'datetime'];
    }

    public static function hashIban(string $iban): string
    {
        return PiiHash::make('iban', $iban);
    }

    /** IR•• •••• … 1234 for lists; the full number is shown only on explicit reveal. */
    public function masked(): string
    {
        return 'IR•• •••• •••• •••• •••• '.$this->iban_last4;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
