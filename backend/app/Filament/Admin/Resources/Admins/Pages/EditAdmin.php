<?php

namespace App\Filament\Admin\Resources\Admins\Pages;

use App\Filament\Admin\Concerns\AuditsRecordChanges;
use App\Filament\Admin\Resources\Admins\AdminResource;
use Filament\Resources\Pages\EditRecord;

class EditAdmin extends EditRecord
{
    use AuditsRecordChanges;

    protected static string $resource = AdminResource::class;
}
