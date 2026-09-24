<?php

namespace App\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/** Inline SVG initials: avoids sending staff names to a third-party avatar service. */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $name = trim((string) Filament::getNameForDefaultAvatar($record));
        $initial = htmlspecialchars(mb_substr($name, 0, 1) ?: '?', ENT_XML1);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#E7F3EC"/>'
            .'<text x="32" y="41" font-family="Vazirmatn, sans-serif" font-size="28" font-weight="700" text-anchor="middle" fill="#0F5E36">'.$initial.'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
