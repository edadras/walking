<?php

namespace App\Filament\Admin\Resources\Announcements\Pages;

use App\Filament\Admin\Concerns\AuditsRecordChanges;
use App\Filament\Admin\Resources\Announcements\AnnouncementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAnnouncement extends CreateRecord
{
    use AuditsRecordChanges;

    protected static string $resource = AnnouncementResource::class;
}
