<?php

namespace App\Providers;

use App\Domain\Auth\Sms\KavenegarSmsSender;
use App\Domain\Auth\Sms\LogSmsSender;
use App\Domain\Auth\Sms\SmsSender;
use App\Domain\Device\Integrity\IntegrityVerifier;
use App\Domain\Device\Integrity\NullIntegrityVerifier;
use App\Domain\Device\Integrity\PlayIntegrityVerifier;
use App\Domain\Notification\FcmPushSender;
use App\Domain\Notification\LogPushSender;
use App\Domain\Notification\PushePushSender;
use App\Domain\Notification\PushSender;
use App\Domain\Notification\RoutingPushSender;
use App\Domain\Settings\FeatureFlags;
use App\Domain\Settings\Settings;
use App\Domain\Store\PaymentGateway;
use App\Domain\Store\ZarinpalGateway;
use App\Models\AdCampaign;
use App\Models\Admin;
use App\Models\AdView;
use App\Models\Campaign;
use App\Models\Coupon;
use App\Models\DailyActivity;
use App\Models\Device;
use App\Models\FraudCase;
use App\Models\Location;
use App\Models\Order;
use App\Models\PersonalAccessToken;
use App\Models\PointTransaction;
use App\Models\Product;
use App\Models\Sponsor;
use App\Models\SponsorUser;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\UserCoupon;
use App\Models\Visit;
use App\Models\WalkingSession;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped: one resolution per request/job so admin changes apply immediately.
        $this->app->scoped(Settings::class);
        $this->app->scoped(FeatureFlags::class);

        $this->app->singleton(SmsSender::class, function ($app) {
            return match (config('walk.sms.driver')) {
                'kavenegar' => new KavenegarSmsSender(config('walk.sms.kavenegar.api_key'), config('walk.sms.kavenegar.template')),
                'log' => $app->isProduction()
                    ? throw new RuntimeException('SMS_DRIVER=log is not allowed in production.')
                    : new LogSmsSender,
                default => throw new RuntimeException('Unknown SMS driver.'),
            };
        });

        $this->app->singleton(PushSender::class, function () {
            $senders = [];
            $credentials = config('walk.push.fcm_credentials');
            if ($credentials) {
                $senders['fcm'] = new FcmPushSender(json_decode((string) file_get_contents($credentials), true));
            }
            if (config('walk.push.pushe_token') && config('walk.push.pushe_app_id')) {
                $senders['pushe'] = new PushePushSender(config('walk.push.pushe_token'), config('walk.push.pushe_app_id'));
            }

            // Unconfigured providers are logged, so nothing is silently lost in development.
            return new RoutingPushSender($senders, new LogPushSender);
        });

        // No gateway configured → PaymentGateway stays unbound and rial checkout is refused.
        if (config('walk.payments.zarinpal.merchant_id')) {
            $this->app->singleton(PaymentGateway::class, fn () => new ZarinpalGateway(config('walk.payments.zarinpal.merchant_id'), config('walk.payments.zarinpal.sandbox')));
        }

        $this->app->singleton(IntegrityVerifier::class, function () {
            if (config('walk.integrity.driver') === 'google' && config('walk.integrity.credentials')) {
                $credentials = json_decode((string) file_get_contents(config('walk.integrity.credentials')), true);

                return new PlayIntegrityVerifier($credentials, config('walk.integrity.package_name'));
            }

            return new NullIntegrityVerifier;
        });
    }

    public function boot(): void
    {
        // Listeners in app/Listeners are auto-discovered (WalkingSessionSubmitted → ScoreWalkingSession,
        // WalkingSessionScored → IssueSessionReward); don't register them again here.

        // Short, stable names in polymorphic columns (ledger sources, fraud subjects).
        Relation::morphMap([
            'user' => User::class,
            'admin' => Admin::class,
            'device' => Device::class,
            'walking_session' => WalkingSession::class,
            'daily_activity' => DailyActivity::class,
            'fraud_case' => FraudCase::class,
            'point_transaction' => PointTransaction::class,
            'sponsor' => Sponsor::class,
            'sponsor_user' => SponsorUser::class,
            'location' => Location::class,
            'campaign' => Campaign::class,
            'visit' => Visit::class,
            'coupon' => Coupon::class,
            'user_coupon' => UserCoupon::class,
            'ad_campaign' => AdCampaign::class,
            'ad_view' => AdView::class,
            'order' => Order::class,
            'product' => Product::class,
            'support_ticket' => SupportTicket::class,
        ]);

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Storage stays UTC; panels display Iran time.
        FilamentTimezone::set(config('walk.panel_timezone'));

        Model::shouldBeStrict(! $this->app->isProduction());

        RateLimiter::for('public', fn (Request $r) => Limit::perMinute(60)->by('ip:'.$r->ip()));
        RateLimiter::for('device-register', fn (Request $r) => Limit::perHour(20)->by('ip:'.$r->ip()));
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by('u:'.($r->user()?->id ?? $r->ip())));
        RateLimiter::for('sessions', fn (Request $r) => Limit::perMinute(30)->by('sess:'.($r->user()?->id ?? $r->ip())));
        RateLimiter::for('visits', fn (Request $r) => Limit::perMinute(60)->by('visit:'.($r->user()?->id ?? $r->ip())));
        RateLimiter::for('purchase', fn (Request $r) => Limit::perMinute(10)->by('buy:'.($r->user()?->id ?? $r->ip())));
        RateLimiter::for('support', fn (Request $r) => Limit::perHour(30)->by('sup:'.($r->user()?->id ?? $r->ip())));
        RateLimiter::for('ads', fn (Request $r) => Limit::perMinute(60)->by('ads:'.($r->user()?->id ?? $r->ip())));
        RateLimiter::for('map-tiles', fn (Request $r) => Limit::perMinute(600)->by('tile:'.($r->user()?->id ?? $r->ip())));
        RateLimiter::for('client-errors', fn (Request $r) => [Limit::perMinute(10)->by('ce:'.$r->ip()), Limit::perDay(300)->by('ced:'.$r->ip())]);
        RateLimiter::for('analytics', fn (Request $r) => Limit::perMinute(20)->by('an:'.($r->user()?->id ?? $r->ip())));
    }
}
