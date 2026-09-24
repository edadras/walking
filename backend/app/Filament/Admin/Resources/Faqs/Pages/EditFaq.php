<?php

namespace App\Filament\Admin\Resources\Faqs\Pages;

use App\Filament\Admin\Concerns\AuditsRecordChanges;
use App\Filament\Admin\Resources\Faqs\FaqResource;
use Filament\Resources\Pages\EditRecord;

class EditFaq extends EditRecord
{
    use AuditsRecordChanges;

    protected static string $resource = FaqResource::class;
}
