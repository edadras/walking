<?php

namespace App\Filament\Admin\Resources\Campaigns\Pages;

use App\Filament\Admin\Concerns\AuditsRecordChanges;
use App\Filament\Admin\Resources\Campaigns\CampaignResource;
use Filament\Resources\Pages\EditRecord;

class EditCampaign extends EditRecord
{
    use AuditsRecordChanges;

    protected static string $resource = CampaignResource::class;
}
