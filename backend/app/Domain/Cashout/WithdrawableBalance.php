<?php

namespace App\Domain\Cashout;

use App\Domain\Settings\Settings;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\PointTransaction;
use App\Models\User;

/**
 * Which points may leave the platform as money.
 *
 * Only points from eligible sources (walking and activity rewards, sponsor rewards)
 * that have been spendable for `cashout.maturity_days` count; referral, ad and
 * manual-credit points can be spent in the store but never withdrawn. Spending is
 * assumed to consume withdrawable points first, which is the conservative choice:
 *
 *   withdrawable = clamp( min(available, Σ matured eligible credits − Σ outflows + Σ refunds), 0 )
 */
class WithdrawableBalance
{
    public const DEFAULT_TYPES = 'walking_reward,goal_bonus,streak_bonus,challenge_reward,quest_reward,achievement_reward,sponsor_reward,coupon_reward';

    public function __construct(private readonly Settings $settings) {}

    /** @return list<string> */
    public function eligibleTypes(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) ($this->settings->get('cashout.eligible_types') ?: self::DEFAULT_TYPES)))));
    }

    /** @return array{withdrawable: int, available: int, immature: int} */
    public function of(User $user): array
    {
        $available = (int) ($user->wallet()->value('available_balance') ?? 0);
        $cutoff = now()->subDays($this->settings->int('cashout.maturity_days'));
        $done = fn () => PointTransaction::query()->where('user_id', $user->id)->where('status', TransactionStatus::Completed);

        $eligible = $done()->where('amount', '>', 0)->whereIn('type', $this->eligibleTypes());
        $matured = (int) (clone $eligible)->whereRaw('COALESCE(completed_at, created_at) <= ?', [$cutoff])->sum('amount');
        $recent = (int) (clone $eligible)->whereRaw('COALESCE(completed_at, created_at) > ?', [$cutoff])->sum('amount');
        $outflow = (int) -$done()->where('amount', '<', 0)->sum('amount');
        $refunds = (int) $done()->where('type', TransactionType::Refund)->sum('amount');

        $withdrawable = max(0, min($available, $matured - $outflow + $refunds));

        return ['withdrawable' => $withdrawable, 'available' => $available, 'immature' => $recent];
    }
}
