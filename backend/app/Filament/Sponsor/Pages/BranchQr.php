<?php

namespace App\Filament\Sponsor\Pages;

use App\Domain\Sponsor\QrToken;
use App\Enums\LocationStatus;
use App\Models\Location;
use App\Models\SponsorUser;
use BackedEnum;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Open on a tablet/monitor at the counter. The code rotates every window
 * (default 30 s) and is only valid for the selected branch.
 */
class BranchQr extends Page
{
    protected string $view = 'filament.sponsor.branch-qr';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|UnitEnum|null $navigationGroup = 'شعبه';

    protected static ?string $navigationLabel = 'QR شعبه';

    protected static ?string $title = 'QR چرخشی شعبه';

    protected static ?string $slug = 'branch-qr';

    public ?string $locationId = null;

    public static function canAccess(): bool
    {
        $user = auth('sponsor')->user();

        return $user instanceof SponsorUser && $user->hasAbility('qr.display');
    }

    /** @return Collection<int, Location> */
    public function getLocations(): Collection
    {
        return Location::query()->where('sponsor_id', auth('sponsor')->user()->sponsor_id)->where('status', LocationStatus::Approved)->orderBy('name')->get();
    }

    public function mount(): void
    {
        $this->locationId = $this->getLocations()->first()?->public_id;
    }

    public function getLocation(): ?Location
    {
        return $this->locationId ? $this->getLocations()->firstWhere('public_id', $this->locationId) : null;
    }

    /** @return array{svg: string, seconds_left: int, window: int}|null */
    public function getCode(): ?array
    {
        $location = $this->getLocation();
        if ($location === null) {
            return null;
        }
        $qr = app(QrToken::class);
        $svg = (new QRCode(new QROptions(['outputInterface' => QRMarkupSVG::class, 'outputBase64' => false, 'eccLevel' => EccLevel::M, 'addQuietzone' => true])))
            ->render($qr->issue($location));

        return ['svg' => preg_replace('/^<\?xml[^>]*>\s*/', '', $svg), 'seconds_left' => $qr->secondsLeft(), 'window' => $qr->windowSeconds()];
    }
}
