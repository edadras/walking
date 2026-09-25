<?php

namespace App\Filament\Admin\Resources\Cashout;

use App\Domain\Audit\AuditLogger;
use App\Domain\Cashout\CashoutService;
use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\BankAccount;
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
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Sheba review: the account must belong to the verified identity (checked via the bank's Sheba inquiry). */
class BankAccountResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = BankAccount::class;

    protected static ?string $viewAbility = 'kyc.review';

    protected static ?string $manageAbility = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'برداشت نقدی';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'حساب بانکی';

    protected static ?string $pluralModelLabel = 'حساب‌های بانکی';

    protected static ?string $slug = 'cashout/bank-accounts';

    public static function getNavigationBadge(): ?string
    {
        $n = BankAccount::query()->where('status', BankAccount::PENDING)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user.identity'))
            ->columns([
                TextColumn::make('user.phone')->label('کاربر')->searchable(),
                TextColumn::make('bank_name')->label('بانک'),
                TextColumn::make('iban_last4')->label('شبا')->formatStateUsing(fn ($state, BankAccount $r) => $r->masked()),
                TextColumn::make('holder_name')->label('نام ثبت‌شده'),
                TextColumn::make('identity_status')->label('هویت')->badge()
                    ->state(fn (BankAccount $r) => $r->user->identity?->status ?? 'none')
                    ->formatStateUsing(fn (string $state) => StatusBadge::REVIEW[$state] ?? 'ثبت نشده')->color(fn (string $state) => StatusBadge::color($state)),
                TextColumn::make('status')->label('وضعیت')->badge()
                    ->formatStateUsing(fn (string $state) => StatusBadge::REVIEW[$state] ?? $state)->color(fn (string $state) => StatusBadge::color($state)),
                TextColumn::make('created_at')->label('ثبت')->since(),
            ])
            ->filters([SelectFilter::make('status')->label('وضعیت')->options(StatusBadge::REVIEW)->default(BankAccount::PENDING)])
            ->recordActions([
                Action::make('reveal')->label('نمایش شبا')->icon(Heroicon::OutlinedEye)->color('gray')
                    ->requiresConfirmation()->modalDescription('برای استعلام نام صاحب حساب. مشاهده در گزارش ممیزی ثبت می‌شود.')
                    ->action(function (BankAccount $record) {
                        app(AuditLogger::class)->log('cashout.iban_revealed', $record);
                        Notification::make()->title($record->iban)->body('صاحب حساب باید «'.$record->holder_name.'» باشد.')->persistent()->send();
                    }),
                Action::make('verify')->label('تأیید')->icon(Heroicon::OutlinedCheck)->color('success')
                    // An account can only be trusted once the person it must belong to is verified.
                    ->visible(fn (BankAccount $r) => $r->status === BankAccount::PENDING && $r->user->identity?->status === UserIdentity::VERIFIED)
                    ->requiresConfirmation()->modalDescription(fn (BankAccount $r) => 'استعلام شبا نام «'.$r->holder_name.'» را نشان داد؟')
                    ->action(function (BankAccount $record) {
                        app(CashoutService::class)->reviewBankAccount($record, true, static::admin());
                        Notification::make()->title('حساب تأیید شد')->success()->send();
                    }),
                Action::make('reject')->label('رد')->icon(Heroicon::OutlinedXMark)->color('danger')
                    ->visible(fn (BankAccount $r) => $r->status !== BankAccount::REJECTED)
                    ->schema([Textarea::make('reason')->label('دلیل (به کاربر اعلام می‌شود)')->required()->maxLength(200)->default('صاحب حساب با مشخصات هویتی مطابقت ندارد.')])
                    ->action(function (BankAccount $record, array $data) {
                        app(CashoutService::class)->reviewBankAccount($record, false, static::admin(), $data['reason']);
                        Notification::make()->title('حساب رد شد')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListBankAccounts::route('/')];
    }
}
