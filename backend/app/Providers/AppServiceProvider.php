<?php

namespace App\Providers;

use App\Domain\Auth\Sms\KavenegarSmsSender;
use App\Domain\Auth\Sms\LogSmsSender;
use App\Domain\Auth\Sms\SmsSender;
use App\Domain\Device\Integrity\IntegrityVerifier;
use App\Domain\Device\Integrity\NullIntegrityVerifier;
use App\Domain\Device\Integrity\PlayIntegrityVerifier;
use App\Domain\Settings\FeatureFlags;
use App\Domain\Settings\Settings;
use App\Models\PersonalAccessToken;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
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
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Storage stays UTC; panels display Iran time.
        FilamentTimezone::set(config('walk.panel_timezone'));

        Model::shouldBeStrict(! $this->app->isProduction());

        RateLimiter::for('public', fn (Request $r) => Limit::perMinute(60)->by('ip:'.$r->ip()));
        RateLimiter::for('device-register', fn (Request $r) => Limit::perHour(20)->by('ip:'.$r->ip()));
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by('u:'.($r->user()?->id ?? $r->ip())));
        RateLimiter::for('sessions', fn (Request $r) => Limit::perMinute(30)->by('sess:'.($r->user()?->id ?? $r->ip())));
        RateLimiter::for('analytics', fn (Request $r) => Limit::perMinute(20)->by('an:'.($r->user()?->id ?? $r->ip())));
    }
}
