<?php

namespace App\Filament\Admin\Resources\Announcements\Pages;

use App\Filament\Admin\Concerns\AuditsRecordChanges;
use App\Filament\Admin\Resources\Announcements\AnnouncementResource;
use Filament\Resources\Pages\EditRecord;

class EditAnnouncement extends EditRecord
{
    use AuditsRecordChanges;

    protected static string $resource = AnnouncementResource::class;
}
