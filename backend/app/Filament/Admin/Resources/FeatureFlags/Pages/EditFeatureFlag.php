<?php

namespace App\Filament\Admin\Resources\FeatureFlags\Pages;

use App\Domain\Settings\FeatureFlags;
use App\Filament\Admin\Concerns\AuditsRecordChanges;
use App\Filament\Admin\Resources\FeatureFlags\FeatureFlagResource;
use Filament\Resources\Pages\EditRecord;

class EditFeatureFlag extends EditRecord
{
    use AuditsRecordChanges;

    protected static string $resource = FeatureFlagResource::class;

    protected function afterSave(): void
    {
        app(FeatureFlags::class)->flush();
    }
}
