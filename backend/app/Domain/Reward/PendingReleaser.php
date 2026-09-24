<?php

namespace App\Domain\Reward;

use App\Domain\Wallet\WalletService;
use App\Enums\FraudCaseStatus;
use App\Enums\TransactionStatus;
use App\Enums\UserStatus;
use App\Models\FraudCase;
use App\Models\PointTransaction;
use App\Models\User;
use App\Models\WalkingSession;

/**
 * Moves reward holds to spendable once their hold window has passed — unless
 * the account is under review or no longer active (then they keep waiting).
 */
class PendingReleaser
{
    public function __construct(private readonly WalletService $wallet) {}

    public function run(int $limit = 1000): int
    {
        $released = 0;
        PointTransaction::query()
            ->where('status', TransactionStatus::Pending)
            ->where('amount', '>', 0)
            ->where('available_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->groupBy('user_id')
            ->each(function ($transactions, $userId) use (&$released) {
                $user = User::query()->find($userId);
                $blocked = $user === null
                    || $user->status !== UserStatus::Active
                    || FraudCase::query()->where('user_id', $userId)->whereIn('status', [FraudCaseStatus::Open, FraudCaseStatus::Flagged])->exists();
                if ($blocked) {
                    return;
                }
                foreach ($transactions as $t) {
                    if ($this->sourceStillValid($t)) {
                        $this->wallet->release($t);
                        $released++;
                    }
                }
            });

        return $released;
    }

    private function sourceStillValid(PointTransaction $t): bool
    {
        if ($t->source_type !== (new WalkingSession)->getMorphClass()) {
            return true;
        }
        $session = WalkingSession::query()->find($t->source_id);

        return $session !== null && $session->verified_steps > 0;
    }
}
