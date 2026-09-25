<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashoutRequest extends Model
{
    use HasPublicId;

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const PAID = 'paid';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    public const OPEN = [self::PENDING, self::APPROVED];

    public const LABELS = [
        self::PENDING => 'در انتظار بررسی',
        self::APPROVED => 'تأییدشده، در صف واریز',
        self::PAID => 'واریز شد',
        self::REJECTED => 'رد شد',
        self::CANCELLED => 'لغو شد',
    ];

    protected $guarded = ['id', 'public_id'];

    protected $hidden = ['id'];

    protected function casts(): array
    {
        return ['points' => 'integer', 'amount_rial' => 'integer', 'rial_per_point' => 'integer', 'approved_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class)->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'paid_by');
    }

    public function debitTransaction(): BelongsTo
    {
        return $this->belongsTo(PointTransaction::class, 'debit_transaction_id');
    }

    public function refundTransaction(): BelongsTo
    {
        return $this->belongsTo(PointTransaction::class, 'refund_transaction_id');
    }
}
