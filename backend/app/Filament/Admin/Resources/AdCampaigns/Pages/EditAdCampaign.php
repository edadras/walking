<?php

namespace App\Filament\Admin\Resources\AdCampaigns\Pages;

use App\Filament\Admin\Concerns\AuditsRecordChanges;
use App\Filament\Admin\Resources\AdCampaigns\AdCampaignResource;
use Filament\Resources\Pages\EditRecord;

class EditAdCampaign extends EditRecord
{
    use AuditsRecordChanges;

    protected static string $resource = AdCampaignResource::class;
}
