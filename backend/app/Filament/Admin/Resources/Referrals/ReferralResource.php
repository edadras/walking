<?php

namespace App\Filament\Admin\Resources\Referrals;

use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\Referral;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ReferralResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = Referral::class;

    protected static ?string $viewAbility = 'users.view';

    protected static ?string $manageAbility = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static string|UnitEnum|null $navigationGroup = 'تعامل';

    protected static ?string $modelLabel = 'دعوت';

    protected static ?string $pluralModelLabel = 'دعوت‌ها';

    public static function table(Table $table): Table
    {
        $labels = ['pending' => 'در انتظار', 'qualified' => 'واجد شرایط', 'rewarded' => 'پاداش داده شد', 'rejected' => 'رد'];

        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['referrer:id,display_name,referral_code', 'referee:id,display_name,referral_code']))
            ->columns([
                TextColumn::make('referrer.display_name')->label('دعوت‌کننده')->placeholder('—'),
                TextColumn::make('referee.display_name')->label('دعوت‌شده')->placeholder('—'),
                TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn ($state) => $labels[$state] ?? $state),
                TextColumn::make('rejection_reason')->label('دلیل رد')->placeholder('—'),
                TextColumn::make('created_at')->label('ثبت')->since(),
            ])
            ->filters([SelectFilter::make('status')->options($labels)]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListReferrals::route('/')];
    }
}
