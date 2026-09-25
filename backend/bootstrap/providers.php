<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\OrgPanelProvider;
use App\Providers\Filament\SponsorPanelProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    SponsorPanelProvider::class,
    OrgPanelProvider::class,
    HorizonServiceProvider::class,
];
