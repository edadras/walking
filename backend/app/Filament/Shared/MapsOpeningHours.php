<?php

namespace App\Filament\Shared;

/** For location create/edit pages: the form edits hours as text, the model stores JSON. */
trait MapsOpeningHours
{
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['opening_hours_text'] = SponsorForms::hoursToText($data['opening_hours'] ?? null);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->mapHours($data);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->mapHours($data);
    }

    private function mapHours(array $data): array
    {
        $data['opening_hours'] = SponsorForms::textToHours($data['opening_hours_text'] ?? null);
        unset($data['opening_hours_text']);

        return $data;
    }
}
