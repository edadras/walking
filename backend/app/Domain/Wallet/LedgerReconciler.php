<?php

namespace App\Domain\Wallet;

use App\Domain\Ops\OpsAlerter;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\CashoutRequest;
use App\Models\PointTransaction;
use App\Models\ReconciliationRun;
use Illuminate\Support\Facades\DB;

/**
 * Independent check that the cached wallet balances and the payout records agree
 * with the append-only ledger. Read-only: it reports, a human fixes (through an
 * audited adjustment), so a bug can never "correct" money on its own.
 *
 *   wallet.available_balance == Σ amount (completed)      per user
 *   wallet.pending_balance   == Σ amount (pending)        per user
 *   no user has a negative ledger balance
 *   every cash-out has its debit; closed ones have exactly one matching refund; paid ones a reference and two admins
 */
class LedgerReconciler
{
    private const MAX_LISTED = 200;

    public function __construct(private readonly OpsAlerter $alerts) {}

    public function run(): ReconciliationRun
    {
        $started = now();
        $issues = [];
        $add = function (string $kind, array $data) use (&$issues) {
            $issues[] = ['kind' => $kind, ...$data];
        };

        $ledger = PointTransaction::query()
            ->selectRaw('user_id, SUM(CASE WHEN status = ? THEN amount ELSE 0 END) AS completed, SUM(CASE WHEN status = ? THEN amount ELSE 0 END) AS pending',
                [TransactionStatus::Completed->value, TransactionStatus::Pending->value])
            ->groupBy('user_id');
        $rows = DB::table('wallets')
            ->leftJoinSub($ledger, 'l', 'l.user_id', '=', 'wallets.user_id')
            ->select('wallets.user_id', 'wallets.available_balance', 'wallets.pending_balance', 'l.completed', 'l.pending')
            ->get();
        foreach ($rows as $r) {
            if ((int) $r->available_balance !== (int) $r->completed) {
                $add('available_mismatch', ['user_id' => $r->user_id, 'wallet' => (int) $r->available_balance, 'ledger' => (int) $r->completed]);
            }
            if ((int) $r->pending_balance !== (int) $r->pending) {
                $add('pending_mismatch', ['user_id' => $r->user_id, 'wallet' => (int) $r->pending_balance, 'ledger' => (int) $r->pending]);
            }
            if ((int) $r->completed < 0) {
                $add('negative_balance', ['user_id' => $r->user_id, 'ledger' => (int) $r->completed]);
            }
        }
        $orphans = PointTransaction::query()->whereNotIn('user_id', DB::table('wallets')->select('user_id'))->distinct()->pluck('user_id');
        foreach ($orphans as $userId) {
            $add('ledger_without_wallet', ['user_id' => $userId]);
        }

        $cashouts = CashoutRequest::query()->with(['debitTransaction', 'refundTransaction'])->get();
        foreach ($cashouts as $c) {
            $d = $c->debitTransaction;
            if ($d === null || $d->type !== TransactionType::Cashout || (int) $d->amount !== -$c->points || $d->user_id !== $c->user_id) {
                $add('cashout_debit_missing', ['cashout' => $c->public_id]);
            }
            $closed = in_array($c->status, [CashoutRequest::REJECTED, CashoutRequest::CANCELLED], true);
            $refunds = PointTransaction::query()->where('idempotency_key', 'refund:cashout:'.$c->public_id)->where('user_id', $c->user_id)->get();
            if ($closed && ($refunds->count() !== 1 || (int) $refunds->first()->amount !== $c->points)) {
                $add('cashout_refund_missing', ['cashout' => $c->public_id]);
            }
            if (! $closed && $refunds->isNotEmpty()) {
                $add('cashout_refunded_but_open', ['cashout' => $c->public_id, 'status' => $c->status]);
            }
            if ($c->status === CashoutRequest::PAID && ($c->bank_reference === null || $c->paid_by === null || $c->paid_by === $c->approved_by)) {
                $add('cashout_paid_without_controls', ['cashout' => $c->public_id]);
            }
        }

        $totals = [
            'points_available' => (int) DB::table('wallets')->sum('available_balance'),
            'points_pending' => (int) DB::table('wallets')->sum('pending_balance'),
            'cashout_paid_rial' => (int) $cashouts->where('status', CashoutRequest::PAID)->sum('amount_rial'),
            'cashout_open_rial' => (int) $cashouts->whereIn('status', CashoutRequest::OPEN)->sum('amount_rial'),
        ];

        $run = ReconciliationRun::query()->create([
            'status' => $issues === [] ? 'ok' : 'issues',
            'wallets_checked' => $rows->count(),
            'cashouts_checked' => $cashouts->count(),
            'issue_count' => count($issues),
            'issues' => array_slice($issues, 0, self::MAX_LISTED),
            'totals' => $totals,
            'started_at' => $started,
            'finished_at' => now(),
        ]);

        if ($issues !== []) {
            $kinds = collect($issues)->countBy('kind')->map(fn ($n, $k) => "$k: $n")->join('، ');
            $this->alerts->alert('ledger-reconcile', 'مغایرت در دفتر کل امتیاز', count($issues).' مورد ('.$kinds.'). جزئیات: پنل › مغایرت‌گیری دفتر کل.', 'wallet.view', quietMinutes: 720);
        }

        return $run;
    }
}
