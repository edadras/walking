<?php

namespace App\Domain\Fraud;

use App\Domain\Audit\AuditLogger;
use App\Domain\Reward\RewardEngine;
use App\Domain\User\UserModeration;
use App\Domain\Wallet\WalletService;
use App\Enums\FraudCaseStatus;
use App\Enums\SessionStatus;
use App\Enums\TransactionStatus;
use App\Enums\UserStatus;
use App\Models\Admin;
use App\Models\FraudCase;
use App\Models\PointTransaction;
use App\Models\Reward;
use App\Models\WalkingSession;
use Illuminate\Support\Facades\DB;

/** Analyst decisions on fraud cases. Every decision is audited. */
class FraudCaseService
{
    public function __construct(
        private readonly FraudEngine $engine,
        private readonly RewardEngine $rewards,
        private readonly WalletService $wallet,
        private readonly UserModeration $moderation,
        private readonly AuditLogger $audit,
    ) {}

    /** Legit: the session counts with its computed verified steps and gets rewarded. */
    public function approve(FraudCase $case, Admin $admin, ?string $note = null): void
    {
        $this->decide($case, FraudCaseStatus::Approved, $admin, $note, function (?WalkingSession $session) {
            if ($session === null) {
                return;
            }
            $session->forceFill(['status' => $session->verified_steps < $session->raw_steps ? SessionStatus::PartiallyVerified : SessionStatus::Verified])->save();
            $this->engine->refreshDaily($session);
            DB::afterCommit(fn () => $this->rewards->forSession($session->fresh()));
        });
    }

    /** Fraud: the session counts for nothing and any pending reward from it is reversed. */
    public function reject(FraudCase $case, Admin $admin, ?string $note = null): void
    {
        $this->decide($case, FraudCaseStatus::Rejected, $admin, $note, function (?WalkingSession $session) use ($note) {
            if ($session === null) {
                return;
            }
            $session->forceFill(['status' => SessionStatus::Rejected, 'verified_steps' => 0])->save();
            $this->reversePendingRewards($session, $note ?? 'رد توسط تحلیلگر');
            $this->engine->refreshDaily($session);
        });
    }

    public function markSafe(FraudCase $case, Admin $admin, ?string $note = null): void
    {
        $this->approve($case, $admin, $note);
        $case->forceFill(['status' => FraudCaseStatus::Safe])->save();
    }

    public function flag(FraudCase $case, Admin $admin, ?string $note = null): void
    {
        $this->decide($case, FraudCaseStatus::Flagged, $admin, $note, fn () => null, closes: false);
    }

    /** Ban: reject this case, reverse every pending reward of the user, block the account. */
    public function ban(FraudCase $case, Admin $admin, string $note): void
    {
        $this->reject($case, $admin, $note);
        $case->forceFill(['status' => FraudCaseStatus::Banned])->save();

        PointTransaction::query()->where('user_id', $case->user_id)->where('status', TransactionStatus::Pending)->where('amount', '>', 0)
            ->each(fn (PointTransaction $t) => $this->wallet->reverse($t, 'مسدودسازی حساب به دلیل تقلب'));

        $this->moderation->setStatus($case->user, UserStatus::Banned, $note, $admin);
    }

    private function decide(FraudCase $case, FraudCaseStatus $status, Admin $admin, ?string $note, callable $effect, bool $closes = true): void
    {
        DB::transaction(function () use ($case, $status, $admin, $note, $effect, $closes) {
            $locked = FraudCase::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
            $old = $locked->status;
            $locked->forceFill([
                'status' => $status,
                'decided_by' => $closes ? $admin->id : null,
                'decided_at' => $closes ? now() : null,
                'decision_note' => $note,
            ])->save();

            $subject = $locked->subject_type === (new WalkingSession)->getMorphClass()
                ? WalkingSession::query()->find($locked->subject_id)
                : null;
            $effect($subject);

            $this->audit->log('fraud_case.'.$status->value, $locked, ['status' => $old->value], ['status' => $status->value], ['note' => $note], $admin);
        });
        $case->refresh();
    }

    private function reversePendingRewards(WalkingSession $session, string $reason): void
    {
        Reward::query()->where('source_type', $session->getMorphClass())->where('source_id', $session->id)->with('transaction')->get()
            ->each(function (Reward $reward) use ($reason) {
                if ($reward->transaction?->status === TransactionStatus::Pending) {
                    $this->wallet->reverse($reward->transaction, $reason);
                }
                $reward->forceFill(['status' => 'reversed'])->save();
            });
    }
}
