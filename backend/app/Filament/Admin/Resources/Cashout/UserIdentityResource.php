<?php

namespace App\Filament\Admin\Resources\Cashout;

use App\Domain\Audit\AuditLogger;
use App\Domain\Cashout\CashoutService;
use App\Domain\Cashout\IranianId;
use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\UserIdentity;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Payout identity review. There is no automated Shahkar/civil-registry inquiry wired in, so
 * support compares the submitted name, national code and birth date with the documents/bank
 * inquiry before verifying. The full national code is only shown through an audited action.
 */
class UserIdentityResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = UserIdentity::class;

    protected static ?string $viewAbility = 'kyc.review';

    protected static ?string $manageAbility = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'برداشت نقدی';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'هویت کاربر';

    protected static ?string $pluralModelLabel = 'احراز هویت';

    protected static ?string $slug = 'cashout/identities';

    public static function getNavigationBadge(): ?string
    {
        $n = UserIdentity::query()->where('status', UserIdentity::PENDING)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at', 'desc')
            ->columns([
                TextColumn::make('user.phone')->label('موبایل (تأییدشده با پیامک)')->searchable(),
                TextColumn::make('first_name')->label('نام')->searchable(),
                TextColumn::make('last_name')->label('نام خانوادگی')->searchable(),
                TextColumn::make('national_code')->label('کد ملی')->formatStateUsing(fn (string $state) => IranianId::mask($state, 3)),
                TextColumn::make('birth_date')->label('تاریخ تولد')->date(),
                TextColumn::make('status')->label('وضعیت')->badge()
                    ->formatStateUsing(fn (string $state) => StatusBadge::REVIEW[$state] ?? $state)->color(fn (string $state) => StatusBadge::color($state)),
                TextColumn::make('rejection_reason')->label('دلیل رد')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('submitted_at')->label('ارسال')->since(),
            ])
            ->filters([SelectFilter::make('status')->label('وضعیت')->options(StatusBadge::REVIEW)->default(UserIdentity::PENDING)])
            ->recordActions([
                Action::make('reveal')->label('نمایش کد ملی')->icon(Heroicon::OutlinedEye)->color('gray')
                    ->requiresConfirmation()->modalDescription('مشاهده کد ملی کامل در گزارش ممیزی ثبت می‌شود.')
                    ->action(function (UserIdentity $record) {
                        app(AuditLogger::class)->log('cashout.national_code_revealed', $record->user);
                        Notification::make()->title('کد ملی: '.$record->national_code)->persistent()->send();
                    }),
                Action::make('verify')->label('تأیید')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->visible(fn (UserIdentity $r) => $r->status === UserIdentity::PENDING)
                    ->requiresConfirmation()->modalDescription('نام، کد ملی و تاریخ تولد را با مدارک/استعلام تطبیق داده‌ای؟')
                    ->action(function (UserIdentity $record) {
                        app(CashoutService::class)->reviewIdentity($record, true, static::admin());
                        Notification::make()->title('هویت تأیید شد')->success()->send();
                    }),
                Action::make('reject')->label('رد')->icon(Heroicon::OutlinedXMark)->color('danger')
                    ->visible(fn (UserIdentity $r) => $r->status !== UserIdentity::REJECTED)
                    ->schema([Textarea::make('reason')->label('دلیل (به کاربر اعلام می‌شود)')->required()->maxLength(200)])
                    ->action(function (UserIdentity $record, array $data) {
                        app(CashoutService::class)->reviewIdentity($record, false, static::admin(), $data['reason']);
                        Notification::make()->title('هویت رد شد')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListUserIdentities::route('/')];
    }
}
