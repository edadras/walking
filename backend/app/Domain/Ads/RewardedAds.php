<?php

namespace App\Domain\Ads;

use App\Domain\Settings\Settings;
use App\Domain\Wallet\WalletService;
use App\Enums\AdViewStatus;
use App\Enums\TransactionType;
use App\Exceptions\ApiException;
use App\Models\AdCampaign;
use App\Models\AdPlacement;
use App\Models\AdProvider;
use App\Models\AdView;
use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Rewarded ads never pay on a client claim. A view is rewarded only when
 *  (a) internal ads: the server's own clock says min_view_seconds passed
 *      between start and completion of THIS view token, or
 *  (b) external networks: a server-to-server callback signed with the
 *      provider's secret confirms the view.
 * Daily cap per user, reward cap per view and an atomic campaign budget apply.
 */
class RewardedAds
{
    public function __construct(
        private readonly AdServer $server,
        private readonly Settings $settings,
        private readonly WalletService $wallet,
    ) {}

    public function start(User $user, ?Device $device, AdPlacement $placement): AdView
    {
        if ($placement->format->value !== 'rewarded' || ! $this->server->enabledFor($user, $placement)) {
            throw ApiException::forbidden('feature_disabled', 'تبلیغ جایزه‌دار در حال حاضر فعال نیست.');
        }
        if ($this->rewardedToday($user) >= $this->settings->int('ads.rewarded_daily_cap')) {
            throw ApiException::conflict('daily_cap', 'سقف تبلیغ جایزه‌دار امروز تمام شده است.');
        }
        $ad = $this->server->pick($user, $placement);
        if ($ad === null) {
            throw ApiException::conflict('no_fill', 'فعلاً تبلیغی برای نمایش نیست.');
        }

        // One open view at a time.
        AdView::query()->where('user_id', $user->id)->where('status', AdViewStatus::Started)->update(['status' => AdViewStatus::Expired]);

        return AdView::query()->create([
            'user_id' => $user->id,
            'device_id' => $device?->id,
            'ad_id' => $ad->id,
            'ad_campaign_id' => $ad->ad_campaign_id,
            'ad_provider_id' => $placement->ad_provider_id,
            'status' => AdViewStatus::Started,
            'local_date' => $this->server->today($user),
            'started_at' => now(),
        ])->setRelation('ad', $ad);
    }

    /** (a) Internal ad: completion reported by the app, timed by the server. */
    public function completeInternal(User $user, AdView $view): AdView
    {
        if ($view->user_id !== $user->id) {
            throw ApiException::forbidden('forbidden', 'دسترسی ندارید.');
        }
        if ($view->provider !== null && ! $view->provider->isInternal()) {
            throw ApiException::unprocessable('s2s_required', 'پاداش این تبلیغ پس از تأیید شبکه تبلیغاتی ثبت می‌شود.');
        }
        $elapsed = $view->started_at->diffInSeconds(now(), true);
        if ($elapsed < $view->ad->min_view_seconds) {
            throw ApiException::unprocessable('too_early', 'تبلیغ هنوز کامل نمایش داده نشده است.');
        }

        return $this->reward($view, null);
    }

    /** (b) Verified S2S callback from an external network. */
    public function completeFromProvider(AdProvider $provider, string $viewId, string $transactionId): AdView
    {
        $view = AdView::query()->where('public_id', $viewId)->where('ad_provider_id', $provider->id)->firstOrFail();

        return $this->reward($view, $transactionId);
    }

    private function reward(AdView $view, ?string $transactionId): AdView
    {
        return DB::transaction(function () use ($view, $transactionId) {
            // Serialises this user's rewards so the daily cap can't be raced.
            $user = User::query()->whereKey($view->user_id)->lockForUpdate()->firstOrFail();
            $locked = AdView::query()->whereKey($view->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== AdViewStatus::Started) {
                return $locked;
            }
            $maxAge = $this->settings->int('ads.rewarded_max_age_minutes') * 60;
            $reason = match (true) {
                $locked->started_at->diffInSeconds(now(), true) > $maxAge => 'expired',
                $this->rewardedToday($user) >= $this->settings->int('ads.rewarded_daily_cap') => 'daily_cap',
                default => null,
            };

            $campaign = AdCampaign::query()->whereKey($locked->ad_campaign_id)->lockForUpdate()->firstOrFail();
            $points = min($campaign->reward_points, $this->settings->int('ads.rewarded_max_points'));
            if ($reason === null && ! $this->consume($campaign, $points)) {
                $reason = 'budget_exhausted';
            }
            if ($reason !== null) {
                $locked->forceFill(['status' => $reason === 'expired' ? AdViewStatus::Expired : AdViewStatus::Rejected, 'rejection_reason' => $reason, 'completed_at' => now(), 'provider_transaction_id' => $transactionId])->save();

                return $locked;
            }

            $tx = $points > 0
                ? $this->wallet->hold($user, $points, TransactionType::AdReward, 'adview:'.$locked->id, 'پاداش تماشای تبلیغ', $locked, now()->addHours($this->settings->int('reward.hold_hours')))
                : null;
            $locked->forceFill(['status' => AdViewStatus::Rewarded, 'completed_at' => now(), 'points_awarded' => $points, 'point_transaction_id' => $tx?->id, 'provider_transaction_id' => $transactionId])->save();

            return $locked;
        });
    }

    private function consume(AdCampaign $campaign, int $points): bool
    {
        $ok = DB::table('ad_campaigns')->where('id', $campaign->id)->whereRaw('points_spent + ? <= point_budget', [$points])
            ->update(['points_spent' => DB::raw('points_spent + '.$points), 'rewards_count' => DB::raw('rewards_count + 1')]);
        if ($ok === 0) {
            return false;
        }
        if ($campaign->sponsor_id === null) {
            return true;
        }

        return DB::table('sponsors')->where('id', $campaign->sponsor_id)->whereRaw('points_spent + ? <= point_budget', [$points])
            ->update(['points_spent' => DB::raw('points_spent + '.$points)]) === 1;
    }

    private function rewardedToday(User $user): int
    {
        return AdView::query()->where('user_id', $user->id)->where('local_date', $this->server->today($user))->where('status', AdViewStatus::Rewarded)->count();
    }
}
