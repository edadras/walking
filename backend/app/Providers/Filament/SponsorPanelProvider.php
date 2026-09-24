<?php

namespace App\Providers\Filament;

use App\Filament\Sponsor\Pages\Register;
use App\Filament\Sponsor\Widgets\AdsChart;
use App\Filament\Sponsor\Widgets\CouponsChart;
use App\Filament\Sponsor\Widgets\SponsorOverview;
use App\Filament\Sponsor\Widgets\VisitsChart;
use App\Filament\Support\InitialsAvatarProvider;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Separate panel for sponsor staff (own guard, own session cookie scope).
 * Every resource is scoped to the signed-in user's sponsor (SponsorScoped).
 */
class SponsorPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('sponsor')
            ->path('sponsor')
            ->authGuard('sponsor')
            ->login()
            ->registration(Register::class)
            ->brandName('گام‌یار — پنل اسپانسر')
            ->font('Vazirmatn', url: '/fonts/vazirmatn/font.css', provider: LocalFontProvider::class)
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->colors([
                'primary' => Color::hex('#1A7F4B'),
                'warning' => Color::hex('#E8A400'),
                'danger' => Color::hex('#C3362B'),
                'gray' => Color::Stone,
            ])
            ->navigationGroups(['شعبه', 'کمپین‌ها', 'گزارش', 'تیم'])
            ->discoverResources(in: app_path('Filament/Sponsor/Resources'), for: 'App\Filament\Sponsor\Resources')
            ->discoverPages(in: app_path('Filament/Sponsor/Pages'), for: 'App\Filament\Sponsor\Pages')
            ->pages([Dashboard::class])
            ->widgets([SponsorOverview::class, VisitsChart::class, CouponsChart::class, AdsChart::class])
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
