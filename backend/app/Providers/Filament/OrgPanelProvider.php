<?php

namespace App\Providers\Filament;

use App\Filament\Org\Widgets\OrgOverview;
use App\Filament\Org\Widgets\OrgWeeklyChart;
use App\Filament\Support\InitialsAvatarProvider;
use Filament\Auth\MultiFactor\App\AppAuthentication;
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

/** Company (B2B) panel: HR sees aggregates, manages members, company challenges and the seat subscription. */
class OrgPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('org')
            ->path('org')
            ->authGuard('org')
            ->login()
            ->profile(isSimple: false)
            ->multiFactorAuthentication([AppAuthentication::make()->recoverable()->brandName('Gamyar Business')])
            ->brandName('گام‌یار — پنل سازمان')
            ->font('Vazirmatn', url: '/fonts/vazirmatn/font.css', provider: LocalFontProvider::class)
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->colors([
                'primary' => Color::hex('#1A7F4B'),
                'warning' => Color::hex('#E8A400'),
                'danger' => Color::hex('#C3362B'),
                'gray' => Color::Stone,
            ])
            ->discoverResources(in: app_path('Filament/Org/Resources'), for: 'App\Filament\Org\Resources')
            ->discoverPages(in: app_path('Filament/Org/Pages'), for: 'App\Filament\Org\Pages')
            ->pages([Dashboard::class])
            ->widgets([OrgOverview::class, OrgWeeklyChart::class])
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
