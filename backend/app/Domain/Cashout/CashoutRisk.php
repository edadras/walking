<?php

namespace App\Domain\Cashout;

use App\Enums\FraudCaseStatus;
use App\Models\BankAccount;
use App\Models\CashoutRequest;
use App\Models\FraudCase;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Support\Facades\DB;

/**
 * Explainable 0–100 risk score for a payout, shown to finance next to each request.
 * It never blocks by itself: it orders the review queue and says what to look at.
 */
class CashoutRisk
{
    public const HIGH = 60;

    public const MEDIUM = 30;

    /** @return array{score: int, signals: list<array{code: string, label: string, points: int}>} */
    public function assess(User $user): array
    {
        $signals = [];
        $add = function (string $code, string $label, int $points) use (&$signals) {
            $signals[] = compact('code', 'label', 'points');
        };

        $ageDays = (int) $user->created_at->diffInDays(now());
        if ($ageDays < 60) {
            $add('young_account', "عمر حساب {$ageDays} روز", 15);
        }

        // Other accounts on the same phones: the classic multi-account payout farm.
        $deviceIds = DB::table('device_user_links')->where('user_id', $user->id)->pluck('device_id');
        $siblings = DB::table('device_user_links')->whereIn('device_id', $deviceIds)->where('user_id', '!=', $user->id)->distinct()->pluck('user_id');
        if ($siblings->isNotEmpty()) {
            $add('shared_device', 'دستگاه مشترک با '.$siblings->count().' حساب دیگر', 25);
            $paying = UserIdentity::query()->whereIn('user_id', $siblings)->count() + CashoutRequest::query()->whereIn('user_id', $siblings)->distinct('user_id')->count('user_id');
            if ($paying > 0) {
                $add('shared_device_payout', 'حساب‌های هم‌دستگاه هم برداشت یا احراز هویت دارند', 15);
            }
        }
        $ipHashes = DB::table('devices')->whereIn('id', $deviceIds)->whereNotNull('last_ip_hash')->pluck('last_ip_hash');
        $ipSiblings = $ipHashes->isEmpty() ? 0 : DB::table('device_user_links')
            ->join('devices', 'devices.id', '=', 'device_user_links.device_id')
            ->whereIn('devices.last_ip_hash', $ipHashes)->where('device_user_links.user_id', '!=', $user->id)->distinct()->count('device_user_links.user_id');
        if ($ipSiblings >= 3) {
            $add('shared_ip', "IP مشترک با {$ipSiblings} حساب دیگر", 10);
        }

        $weak = DB::table('devices')->whereIn('id', $deviceIds)
            ->where(fn ($q) => $q->where('emulator_suspected', true)->orWhere('root_suspected', true)->orWhere('trust_score', '<', 50))->exists();
        if ($weak) {
            $add('weak_device', 'شبیه‌ساز/روت یا اعتماد پایین دستگاه', 15);
        }

        // Sudden jump in verified steps right before cashing out.
        $recent = (float) DB::table('daily_activities')->where('user_id', $user->id)->where('local_date', '>=', now()->subDays(7)->toDateString())->avg('verified_steps');
        $before = (float) DB::table('daily_activities')->where('user_id', $user->id)
            ->whereBetween('local_date', [now()->subDays(35)->toDateString(), now()->subDays(8)->toDateString()])->avg('verified_steps');
        if ($recent > 8000 && ($before === 0.0 || $recent / $before > 2.5)) {
            $add('step_spike', 'جهش قدم هفته اخیر ('.number_format($recent).' در روز در برابر '.number_format($before).')', 20);
        }

        if (FraudCase::query()->where('user_id', $user->id)->where('created_at', '>=', now()->subDays(90))->where('status', '!=', FraudCaseStatus::Safe)->exists()) {
            $add('fraud_history', 'پرونده تقلب در ۹۰ روز اخیر', 20);
        }
        $rejected = BankAccount::withTrashed()->where('user_id', $user->id)->where('status', BankAccount::REJECTED)->count()
            + CashoutRequest::query()->where('user_id', $user->id)->where('status', CashoutRequest::REJECTED)->count();
        if ($rejected > 0) {
            $add('previous_rejections', "{$rejected} رد قبلی (حساب یا برداشت)", 10);
        }

        return ['score' => min(100, array_sum(array_column($signals, 'points'))), 'signals' => $signals];
    }

    public static function level(?int $score): string
    {
        return match (true) {
            $score === null => 'unknown',
            $score >= self::HIGH => 'high',
            $score >= self::MEDIUM => 'medium',
            default => 'low',
        };
    }
}
