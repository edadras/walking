<?php

namespace App\Domain\Referral;

use App\Domain\Settings\FeatureFlags;
use App\Domain\Settings\Settings;
use App\Domain\Wallet\WalletService;
use App\Enums\TransactionType;
use App\Enums\UserStatus;
use App\Models\DailyActivity;
use App\Models\Referral;
use App\Models\User;
use App\Notifications\UserNotification;
use Illuminate\Support\Facades\DB;

/**
 * Referral rewards are paid only after the new user proves to be a real walker
 * (N verified steps within the window) and never when both accounts share a
 * device — the classic self-referral pattern.
 */
class ReferralService
{
    public function __construct(private readonly Settings $settings, private readonly FeatureFlags $flags, private readonly WalletService $wallet) {}

    public function register(User $referee): ?Referral
    {
        if ($referee->referred_by_id === null || ! $this->flags->enabled('referral', $referee->id)) {
            return null;
        }

        return Referral::query()->firstOrCreate(['referee_id' => $referee->id], ['referrer_id' => $referee->referred_by_id, 'status' => 'pending']);
    }

    public function evaluate(User $referee): void
    {
        $referral = Referral::query()->where('referee_id', $referee->id)->where('status', 'pending')->first();
        if ($referral === null) {
            return;
        }

        if ($referral->created_at->lt(now()->subDays($this->settings->int('referral.window_days')))) {
            $referral->forceFill(['status' => 'rejected', 'rejection_reason' => 'expired'])->save();

            return;
        }

        $verified = (int) DailyActivity::query()->where('user_id', $referee->id)->sum('verified_steps');
        if ($verified < $this->settings->int('referral.qualify_steps')) {
            return;
        }

        $sharedDevice = DB::table('device_user_links as a')
            ->join('device_user_links as b', 'a.device_id', '=', 'b.device_id')
            ->where('a.user_id', $referral->referrer_id)->where('b.user_id', $referee->id)->exists();
        $referrer = $referral->referrer;
        if ($sharedDevice || $referrer === null || $referrer->status !== UserStatus::Active) {
            $referral->forceFill(['status' => 'rejected', 'rejection_reason' => $sharedDevice ? 'shared_device' : 'referrer_inactive'])->save();

            return;
        }

        DB::transaction(function () use ($referral, $referrer, $referee) {
            $locked = Referral::query()->whereKey($referral->id)->lockForUpdate()->first();
            if ($locked->status !== 'pending') {
                return;
            }
            $locked->forceFill(['status' => 'rewarded', 'qualified_at' => now(), 'rewarded_at' => now()])->save();
            $this->wallet->hold($referrer, $this->settings->int('referral.referrer_points'), TransactionType::ReferralReward, 'referral:'.$locked->id.':referrer', 'پاداش دعوت از دوست', $locked);
            $this->wallet->hold($referee, $this->settings->int('referral.referee_points'), TransactionType::ReferralReward, 'referral:'.$locked->id.':referee', 'پاداش عضویت با دعوت', $locked);
        });

        $referrer->notify(new UserNotification('reward_received', 'پاداش دعوت', 'دوستی که دعوت کردی فعال شد و پاداش دعوت به کیف پولت اضافه شد.', ['type' => 'referral']));
    }

    /** @return array<string, mixed> */
    public function summary(User $user): array
    {
        $rows = Referral::query()->where('referrer_id', $user->id)->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');

        return [
            'code' => $user->referral_code,
            'share_url' => url('/r/'.$user->referral_code),
            'qualify_steps' => $this->settings->int('referral.qualify_steps'),
            'referrer_points' => $this->settings->int('referral.referrer_points'),
            'referee_points' => $this->settings->int('referral.referee_points'),
            'invited' => (int) $rows->sum(),
            'pending' => (int) ($rows['pending'] ?? 0),
            'rewarded' => (int) ($rows['rewarded'] ?? 0),
            'points_earned' => (int) ($rows['rewarded'] ?? 0) * $this->settings->int('referral.referrer_points'),
        ];
    }
}
