<?php

namespace App\Filament\Admin\Resources\Coupons;

use App\Enums\CouponStatus;
use App\Filament\Admin\Concerns\ModerationActions;
use App\Filament\Admin\Concerns\RequiresAbility;
use App\Filament\Shared\SponsorForms;
use App\Models\Coupon;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class CouponResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = Coupon::class;

    protected static ?string $viewAbility = 'sponsors.manage';

    protected static ?string $manageAbility = 'sponsors.manage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'اسپانسرها';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'کوپن';

    protected static ?string $pluralModelLabel = 'کوپن‌ها';

    public static function getNavigationBadge(): ?string
    {
        $n = Coupon::query()->where('status', CouponStatus::PendingApproval)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            ...SponsorForms::coupon(withSponsor: true),
            Select::make('status')->label('وضعیت')->required()->default(CouponStatus::Active->value)
                ->options(collect(CouponStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('عنوان')->searchable(),
                TextColumn::make('sponsor.name')->label('اسپانسر')->placeholder('پلتفرم'),
                TextColumn::make('discount')->label('تخفیف')->state(fn (Coupon $r) => $r->discountLabel()),
                TextColumn::make('point_cost')->label('قیمت')->numeric(),
                TextColumn::make('claimed_count')->label('دریافت')->numeric(),
                TextColumn::make('redeemed_count')->label('استفاده')->numeric(),
                TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (CouponStatus $state) => $state->label())
                    ->color(fn (CouponStatus $state) => match ($state) {
                        CouponStatus::Active => 'success', CouponStatus::PendingApproval => 'warning', default => 'gray'
                    }),
            ])
            ->filters([SelectFilter::make('status')->label('وضعیت')->options(collect(CouponStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]))])
            ->recordActions([EditAction::make(), ...ModerationActions::make(fn (Coupon $r) => $r->status === CouponStatus::PendingApproval)]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}
