<?php

namespace App\Filament\Sponsor\Pages;

use App\Domain\Sponsor\CouponService;
use App\Enums\LocationStatus;
use App\Exceptions\ApiException;
use App\Models\Location;
use App\Models\SponsorUser;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class RedeemCoupon extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'شعبه';

    protected static ?string $navigationLabel = 'ثبت استفاده کوپن';

    protected static ?string $title = 'ثبت استفاده کوپن';

    protected static ?string $slug = 'redeem';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth('sponsor')->user();

        return $user instanceof SponsorUser && $user->hasAbility('coupons.redeem');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            TextInput::make('code')->label('کد کوپن مشتری')->required()->maxLength(20)->autofocus()
                ->extraInputAttributes(['dir' => 'ltr', 'style' => 'letter-spacing: .2em; text-transform: uppercase']),
            Select::make('location_id')->label('شعبه')
                ->options(fn () => Location::query()->where('sponsor_id', auth('sponsor')->user()->sponsor_id)->where('status', LocationStatus::Approved)->pluck('name', 'id')),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('redeem')
                ->footer([Actions::make([Action::make('redeem')->label('ثبت استفاده')->submit('redeem')])]),
        ]);
    }

    public function redeem(): void
    {
        $state = $this->form->getState();
        /** @var SponsorUser $cashier */
        $cashier = auth('sponsor')->user();
        $location = isset($state['location_id']) ? Location::query()->where('sponsor_id', $cashier->sponsor_id)->find($state['location_id']) : null;

        try {
            $userCoupon = app(CouponService::class)->redeem($cashier, $state['code'], $location);
            Notification::make()->title('کوپن ثبت شد: '.$userCoupon->coupon->title)->body($userCoupon->coupon->discountLabel())->success()->persistent()->send();
            $this->form->fill();
        } catch (ApiException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }
}
