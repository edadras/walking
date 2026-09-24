<?php

namespace App\Filament\Admin\Resources\FeatureFlags\Pages;

use App\Filament\Admin\Resources\FeatureFlags\FeatureFlagResource;
use Filament\Resources\Pages\ListRecords;

class ListFeatureFlags extends ListRecords
{
    protected static string $resource = FeatureFlagResource::class;
}
