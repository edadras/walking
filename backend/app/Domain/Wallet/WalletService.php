<?php

namespace App\Domain\Wallet;

use App\Domain\Audit\AuditLogger;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Admin;
use App\Models\PointTransaction;
use App\Models\User;
use App\Models\Wallet;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * The only code allowed to change point balances (docs/phase-0/05-flows.md §5.5).
 *
 * Every operation:
 *  - runs in one DB transaction that locks the user's wallet row (FOR UPDATE),
 *  - writes exactly one ledger row explaining the change,
 *  - is idempotent on (user, idempotency key): repeating it returns the first result.
 */
class WalletService
{
    public function __construct(private readonly ConversionRate $rates, private readonly AuditLogger $audit) {}

    /** Credit that becomes spendable only after review (rewards). */
    public function hold(User $user, int $points, TransactionType $type, string $key, string $description, ?Model $source = null, ?CarbonInterface $availableAt = null, array $meta = []): PointTransaction
    {
        $this->assertPositive($points);

        return $this->atomic($user, $key, function (Wallet $wallet) use ($user, $points, $type, $key, $description, $source, $availableAt, $meta) {
            $wallet->pending_balance += $points;
            $wallet->save();

            return $this->record($user, $type, $points, TransactionStatus::Pending, $key, $description, $source, meta: $meta, availableAt: $availableAt ?? now());
        });
    }

    /** Moves a pending credit into the available balance. */
    public function release(PointTransaction $transaction): PointTransaction
    {
        return $this->transition($transaction, function (Wallet $wallet, PointTransaction $t) {
            $wallet->pending_balance -= $t->amount;
            $before = $wallet->available_balance;
            $wallet->available_balance += $t->amount;
            $wallet->lifetime_earned += $t->amount;
            $wallet->save();

            $t->fill(['status' => TransactionStatus::Completed, 'balance_before' => $before, 'balance_after' => $wallet->available_balance, 'completed_at' => now()])->save();
        });
    }

    /** Cancels a pending credit (e.g. fraud confirmed). The amount never becomes spendable. */
    public function reverse(PointTransaction $transaction, string $reason): PointTransaction
    {
        return $this->transition($transaction, function (Wallet $wallet, PointTransaction $t) use ($reason) {
            $wallet->pending_balance -= $t->amount;
            $wallet->save();

            $t->fill(['status' => TransactionStatus::Reversed, 'reversed_at' => now(), 'meta' => [...($t->meta ?? []), 'reversal_reason' => $reason]])->save();
        });
    }

    /** Immediately spendable credit (refunds, approved sponsor rewards). */
    public function credit(User $user, int $points, TransactionType $type, string $key, string $description, ?Model $source = null, array $meta = []): PointTransaction
    {
        $this->assertPositive($points);

        return $this->atomic($user, $key, function (Wallet $wallet) use ($user, $points, $type, $key, $description, $source, $meta) {
            $before = $wallet->available_balance;
            $wallet->available_balance += $points;
            if ($type !== TransactionType::Refund) {
                $wallet->lifetime_earned += $points;
            } else {
                $wallet->lifetime_spent = max(0, $wallet->lifetime_spent - $points);
            }
            $wallet->save();

            return $this->record($user, $type, $points, TransactionStatus::Completed, $key, $description, $source, $before, $wallet->available_balance, $meta);
        });
    }

    /** Spend points. Throws InsufficientPoints; the balance can never go negative. */
    public function debit(User $user, int $points, TransactionType $type, string $key, string $description, ?Model $source = null, array $meta = []): PointTransaction
    {
        $this->assertPositive($points);

        return $this->atomic($user, $key, function (Wallet $wallet) use ($user, $points, $type, $key, $description, $source, $meta) {
            if ($wallet->available_balance < $points) {
                throw new InsufficientPoints($wallet->available_balance, $points);
            }
            $before = $wallet->available_balance;
            $wallet->available_balance -= $points;
            $type === TransactionType::Expiration ? $wallet->lifetime_expired += $points : $wallet->lifetime_spent += $points;
            $wallet->save();

            return $this->record($user, $type, -$points, TransactionStatus::Completed, $key, $description, $source, $before, $wallet->available_balance, $meta);
        });
    }

    /** Manual correction by an admin: reason is mandatory and the change is audited. */
    public function adjust(User $user, int $delta, string $reason, Admin $admin): PointTransaction
    {
        if ($delta === 0 || trim($reason) === '') {
            throw new InvalidArgumentException('Adjustment needs a non-zero amount and a reason.');
        }
        $key = 'adjust:'.$admin->id.':'.now()->format('Uu');

        $transaction = $this->atomic($user, $key, function (Wallet $wallet) use ($user, $delta, $reason, $admin, $key) {
            if ($delta < 0 && $wallet->available_balance < -$delta) {
                throw new InsufficientPoints($wallet->available_balance, -$delta);
            }
            $before = $wallet->available_balance;
            $wallet->available_balance += $delta;
            $delta > 0 ? $wallet->lifetime_earned += $delta : $wallet->lifetime_spent += -$delta;
            $wallet->save();

            return $this->record($user, TransactionType::Adjustment, $delta, TransactionStatus::Completed, $key, 'اصلاح توسط پشتیبانی', null, $before, $wallet->available_balance, extra: [
                'performed_by_type' => 'admin',
                'performed_by_id' => $admin->id,
                'reason' => $reason,
            ]);
        });

        $this->audit->log('wallet.adjusted', $user, ['available_balance' => $transaction->balance_before], ['available_balance' => $transaction->balance_after], ['delta' => $delta, 'reason' => $reason, 'transaction' => $transaction->public_id], $admin);

        return $transaction;
    }

    public function walletFor(User $user): Wallet
    {
        return Wallet::query()->firstOrCreate(['user_id' => $user->id]);
    }

    /**
     * @param  callable(Wallet): PointTransaction  $apply
     */
    private function atomic(User $user, string $key, callable $apply): PointTransaction
    {
        return DB::transaction(function () use ($user, $key, $apply) {
            // Lock first; create only if missing. (INSERT IGNORE on an existing row takes a
            // shared lock that deadlocks with a following FOR UPDATE under concurrency.)
            $wallet = Wallet::query()->whereKey($user->id)->lockForUpdate()->first();
            if ($wallet === null) {
                Wallet::query()->insertOrIgnore(['user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
                $wallet = Wallet::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            }

            // Checked under the wallet lock, so two concurrent identical requests can't both pass.
            $existing = PointTransaction::query()->where('user_id', $user->id)->where('idempotency_key', $key)->first();
            if ($existing !== null) {
                return $existing;
            }

            return $apply($wallet);
        }, attempts: 3); // retried on deadlock
    }

    /** @param callable(Wallet, PointTransaction): void $apply */
    private function transition(PointTransaction $transaction, callable $apply): PointTransaction
    {
        return DB::transaction(function () use ($transaction, $apply) {
            $wallet = Wallet::query()->whereKey($transaction->user_id)->lockForUpdate()->firstOrFail();
            $t = PointTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($t->status !== TransactionStatus::Pending) {
                return $t; // already released/reversed: idempotent no-op
            }
            if ($t->amount <= 0) {
                throw new LogicException('Only pending credits can be released or reversed.');
            }
            $apply($wallet, $t);

            return $t;
        }, attempts: 3);
    }

    private function record(User $user, TransactionType $type, int $amount, TransactionStatus $status, string $key, string $description, ?Model $source, ?int $before = null, ?int $after = null, array $meta = [], ?CarbonInterface $availableAt = null, array $extra = []): PointTransaction
    {
        return PointTransaction::query()->create([
            ...$extra,
            'user_id' => $user->id,
            'type' => $type,
            'amount' => $amount,
            'status' => $status,
            'balance_before' => $before,
            'balance_after' => $after,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'idempotency_key' => $key,
            'description' => $description,
            'rial_rate' => $this->rates->current(),
            'meta' => $meta ?: null,
            'available_at' => $availableAt,
            'completed_at' => $status === TransactionStatus::Completed ? now() : null,
        ]);
    }

    private function assertPositive(int $points): void
    {
        if ($points <= 0) {
            throw new InvalidArgumentException('Points must be positive.');
        }
    }
}
