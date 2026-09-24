<?php

namespace App\Filament\Sponsor\Resources\Coupons;

use App\Enums\CouponStatus;
use App\Filament\Shared\SponsorForms;
use App\Filament\Sponsor\Concerns\RecordsSponsorAction;
use App\Filament\Sponsor\Concerns\SponsorScoped;
use App\Models\Coupon;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class CouponResource extends Resource
{
    use SponsorScoped;

    protected static ?string $model = Coupon::class;

    protected static string $ability = 'coupons.manage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'کمپین‌ها';

    protected static ?string $modelLabel = 'کوپن';

    protected static ?string $pluralModelLabel = 'کوپن‌ها';

    public static function canEdit(Model $record): bool
    {
        return static::canView($record) && in_array($record->status, [CouponStatus::Draft, CouponStatus::Rejected], true);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components(SponsorForms::coupon());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('عنوان')->searchable(),
                TextColumn::make('discount')->label('تخفیف')->state(fn (Coupon $r) => $r->discountLabel()),
                TextColumn::make('claimed_count')->label('صادرشده')->numeric(),
                TextColumn::make('redeemed_count')->label('استفاده‌شده')->numeric(),
                TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (CouponStatus $state) => $state->label())
                    ->description(fn (Coupon $r) => $r->status === CouponStatus::Rejected ? $r->rejection_reason : null),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('submit')->label('ارسال برای تأیید')->icon('heroicon-o-paper-airplane')
                    ->visible(fn (Coupon $r) => static::canEdit($r))
                    ->requiresConfirmation()
                    ->action(fn (Coupon $r) => RecordsSponsorAction::transition($r, ['status' => CouponStatus::PendingApproval, 'rejection_reason' => null], 'coupon.submitted')),
                Action::make('pause')->label('توقف')->icon('heroicon-o-pause')->color('warning')
                    ->visible(fn (Coupon $r) => static::canView($r) && $r->status === CouponStatus::Active)
                    ->requiresConfirmation()
                    ->action(fn (Coupon $r) => RecordsSponsorAction::transition($r, ['status' => CouponStatus::Paused], 'coupon.paused')),
                Action::make('resume')->label('ادامه')->icon('heroicon-o-play')->color('success')
                    ->visible(fn (Coupon $r) => static::canView($r) && $r->status === CouponStatus::Paused)
                    ->action(fn (Coupon $r) => RecordsSponsorAction::transition($r, ['status' => CouponStatus::Active], 'coupon.resumed')),
            ]);
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
