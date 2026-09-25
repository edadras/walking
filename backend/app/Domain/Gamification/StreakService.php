<?php

namespace App\Domain\Gamification;

use App\Domain\Settings\Settings;
use App\Domain\Wallet\WalletService;
use App\Enums\TransactionType;
use App\Exceptions\ApiException;
use App\Models\DailyActivity;
use App\Models\StreakFreeze;
use App\Models\User;
use App\Models\UserStreak;
use App\Notifications\UserNotification;
use App\Support\Jalali;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Consecutive days on which the goal was reached with VERIFIED steps, in the
 * user's own timezone. Recomputed from daily rows (never incremented blindly),
 * so late offline syncs and fraud reversals correct it automatically.
 */
class StreakService
{
    public function __construct(private readonly Settings $settings, private readonly WalletService $wallet) {}

    public function refresh(User $user): UserStreak
    {
        $today = CarbonImmutable::now($user->timezone)->startOfDay();
        $reached = DailyActivity::query()
            ->where('user_id', $user->id)
            ->whereNotNull('goal_reached_at')
            ->where('local_date', '>=', $today->subDays(400)->toDateString())
            ->orderByDesc('local_date')
            ->pluck('local_date')
            ->map(fn ($d) => $d->toDateString())
            ->all();

        $set = array_flip($reached);
        $covered = $this->coveredDates($user);
        // A streak survives until the end of today even if today's goal isn't met yet.
        $cursor = isset($set[$today->toDateString()]) ? $today : $today->subDay();
        $current = 0;
        while (true) {
            $date = $cursor->toDateString();
            if (isset($set[$date])) {
                $current++;
            } elseif (! isset($covered[$date]) && ! $this->tryFreeze($user, $cursor, $today, $set, $covered)) {
                break;
            }
            // A frozen day keeps the chain alive but doesn't add to it.
            $cursor = $cursor->subDay();
        }

        $longest = 0;
        $run = 0;
        $previous = null;
        foreach (array_reverse($reached) as $date) {
            $run = $previous !== null && CarbonImmutable::parse($previous)->addDay()->toDateString() === $date ? $run + 1 : 1;
            $longest = max($longest, $run);
            $previous = $date;
        }

        $streak = UserStreak::query()->firstOrNew(['user_id' => $user->id]);
        $streak->fill([
            'current_days' => $current,
            'longest_days' => max($longest, $streak->longest_days ?? 0),
            'last_goal_date' => $reached[0] ?? null,
        ])->save();

        return $streak;
    }

    /**
     * Spends an owned freeze on a missed day, only for the last two days (a freeze
     * can't be bought later to repair an old streak) and only if the chain actually
     * continues before it.
     *
     * @param  array<string, int>  $set
     * @param  array<string, bool>  $covered
     */
    private function tryFreeze(User $user, CarbonImmutable $day, CarbonImmutable $today, array $set, array &$covered): bool
    {
        $before = $day->subDay()->toDateString();
        if ($day->lt($today->subDays(2)) || ! $day->lt($today) || (! isset($set[$before]) && ! isset($covered[$before]))) {
            return false;
        }

        $used = DB::transaction(function () use ($user, $day) {
            $freeze = StreakFreeze::query()->where('user_id', $user->id)->whereNull('used_at')->orderBy('id')->lockForUpdate()->first();
            if ($freeze === null) {
                return false;
            }
            $freeze->forceFill(['covered_date' => $day->toDateString(), 'used_at' => now()])->save();

            return true;
        });
        if ($used) {
            $covered[$day->toDateString()] = true;
            $user->notify(new UserNotification('streak_warning', 'زنجیره‌ات حفظ شد', 'یک محافظ زنجیره برای روز '.Jalali::format($day).' استفاده شد.', ['type' => 'streak']));
        }

        return $used;
    }

    /** @return array<string, bool> */
    private function coveredDates(User $user): array
    {
        return StreakFreeze::query()->where('user_id', $user->id)->whereNotNull('covered_date')
            ->pluck('covered_date')->mapWithKeys(fn ($d) => [$d->toDateString() => true])->all();
    }

    public function ownedFreezes(User $user): int
    {
        return StreakFreeze::query()->where('user_id', $user->id)->whereNull('used_at')->count();
    }

    /** Shared by /home and /progress. */
    public function summary(User $user): array
    {
        $streak = UserStreak::query()->find($user->id);
        $owned = $this->ownedFreezes($user);
        $max = $this->settings->int('streak.freeze_max_owned');

        return [
            'current' => $streak?->current_days ?? 0,
            'longest' => $streak?->longest_days ?? 0,
            'week' => $this->weekDots($user),
            'freezes' => ['owned' => $owned, 'max' => $max, 'price' => $this->settings->int('streak.freeze_price'), 'can_buy' => $owned < $max],
        ];
    }

    /** Buys one freeze with points (ledger-backed, idempotent on the request key). */
    public function buyFreeze(User $user, string $idempotencyKey): StreakFreeze
    {
        return DB::transaction(function () use ($user, $idempotencyKey) {
            User::query()->whereKey($user->id)->lockForUpdate()->first(); // serialise a user's purchases
            if ($existing = StreakFreeze::query()->where('user_id', $user->id)
                ->whereHas('transaction', fn ($q) => $q->where('idempotency_key', 'streak-freeze:'.$user->id.':'.$idempotencyKey))->first()) {
                return $existing;
            }
            if ($this->ownedFreezes($user) >= $this->settings->int('streak.freeze_max_owned')) {
                throw ApiException::conflict('freeze_limit', 'بیشتر از این نمی‌توانی محافظ ذخیره کنی.');
            }
            $freeze = StreakFreeze::query()->create(['user_id' => $user->id]);
            $tx = $this->wallet->debit($user, $this->settings->int('streak.freeze_price'), TransactionType::StreakFreeze,
                'streak-freeze:'.$user->id.':'.$idempotencyKey, 'خرید محافظ زنجیره', $freeze);
            $freeze->forceFill(['point_transaction_id' => $tx->id])->save();

            return $freeze;
        });
    }

    /** Days of the current Iranian week (Sat→Fri) with the goal reached, for the home streak row. */
    public function weekDots(User $user): array
    {
        $today = CarbonImmutable::now($user->timezone)->startOfDay();
        $start = $today->subDays(($today->dayOfWeek + 1) % 7);
        $reached = DailyActivity::query()
            ->where('user_id', $user->id)
            ->whereBetween('local_date', [$start->toDateString(), $start->addDays(6)->toDateString()])
            ->whereNotNull('goal_reached_at')
            ->pluck('local_date')
            ->map(fn ($d) => $d->toDateString())
            ->all();

        $covered = $this->coveredDates($user);
        $dots = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $start->addDays($i);
            $dots[] = ['date' => $d->toDateString(), 'reached' => in_array($d->toDateString(), $reached, true), 'frozen' => isset($covered[$d->toDateString()]), 'future' => $d->gt($today)];
        }

        return $dots;
    }
}
