<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Pages\Dashboard;
use App\Filament\Support\InitialsAvatarProvider;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->authGuard('admin')
            ->login()
            ->profile(isSimple: false)
            // TOTP for every admin (enforced unless explicitly disabled for local dev/tests).
            ->multiFactorAuthentication([AppAuthentication::make()->recoverable()->brandName('Gamyar Admin')], isRequired: fn () => (bool) config('walk.security.admin_mfa_required'))
            ->brandName('گام‌یار — مدیریت')
            ->font('Vazirmatn', url: '/fonts/vazirmatn/font.css', provider: LocalFontProvider::class)
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->colors([
                'primary' => Color::hex('#1A7F4B'),
                'warning' => Color::hex('#E8A400'),
                'danger' => Color::hex('#C3362B'),
                'gray' => Color::Zinc,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->navigationItems([
                NavigationItem::make('صف‌ها (Horizon)')->url('/horizon', shouldOpenInNewTab: true)->icon('heroicon-o-queue-list')->group('تنظیمات')
                    ->visible(fn () => auth('admin')->user()?->hasAbility('*') ?? false),
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            ->navigationGroups(['کاربران', 'فعالیت', 'ضد تقلب', 'امتیاز و کیف پول', 'برداشت نقدی', 'تعامل', 'اسپانسرها', 'تبلیغات', 'فروشگاه', 'پشتیبانی', 'گزارش‌ها', 'امنیت', 'محتوا', 'تنظیمات'])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
