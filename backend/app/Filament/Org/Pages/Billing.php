<?php

namespace App\Filament\Org\Pages;

use App\Domain\Organization\OrganizationBilling;
use App\Domain\Organization\OrganizationService;
use App\Exceptions\ApiException;
use App\Models\OrganizationInvoice;
use App\Models\OrganizationUser;
use App\Support\Jalali;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class Billing extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $navigationLabel = 'اشتراک و تنظیمات';

    protected static ?string $title = 'اشتراک و تنظیمات سازمان';

    protected static ?string $slug = 'billing';

    private static function user(): OrganizationUser
    {
        return auth('org')->user();
    }

    public function content(Schema $schema): Schema
    {
        $org = self::user()->organization;

        return $schema->components([
            Text::make('کد عضویت کارکنان: '.$org->join_code.' — در اپ گام‌یار: پروفایل › سازمان من'),
            Text::make('اشتراک: '.$org->seats.' صندلی، هر صندلی '.number_format($org->seat_price_rial).' ریال در ماه · اعتبار تا '
                .($org->paid_until ? Jalali::format($org->paid_until) : 'پرداخت نشده')),
            Text::make('واحدها: '.(implode('، ', $org->departments ?? []) ?: 'تعریف نشده')),
            EmbeddedTable::make(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        $billing = app(OrganizationBilling::class);
        $admin = fn () => self::user()->isAdmin();

        return [
            Action::make('pay')->label('پرداخت / تمدید اشتراک')->icon(Heroicon::OutlinedCreditCard)
                ->visible(fn () => $admin() && $billing->available())
                ->fillForm(fn () => ['seats' => max(self::user()->organization->seats, self::user()->organization->members()->count()), 'months' => 3])
                ->schema([
                    TextInput::make('seats')->label('تعداد صندلی')->numeric()->integer()->required()->minValue(fn () => max(1, self::user()->organization->members()->count()))->live(debounce: 400),
                    Select::make('months')->label('مدت')->options([1 => '۱ ماه', 3 => '۳ ماه', 6 => '۶ ماه', 12 => '۱۲ ماه'])->required()->live(),
                    Text::make(fn ($get) => 'مبلغ: '.number_format($billing->quote(self::user()->organization, (int) $get('seats'), (int) $get('months'))).' ریال'),
                ])
                ->action(function (array $data) use ($billing) {
                    try {
                        $payment = $billing->start(self::user()->organization, self::user(), (int) $data['seats'], (int) $data['months']);
                    } catch (ApiException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();

                        return null;
                    }

                    return redirect()->away($payment->pay_url);
                }),
            Action::make('departments')->label('واحدها')->icon(Heroicon::OutlinedBuildingOffice)->color('gray')
                ->visible($admin)
                ->fillForm(fn () => ['departments' => self::user()->organization->departments ?? []])
                ->schema([TagsInput::make('departments')->label('نام واحدها')->placeholder('مثلاً: فروش')])
                ->action(fn (array $data) => self::user()->organization->update(['departments' => array_values(array_unique(array_map('trim', $data['departments'])))])),
            Action::make('code')->label('کد عضویت جدید')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                ->visible($admin)
                ->requiresConfirmation()->modalDescription('کد قبلی دیگر کار نمی‌کند؛ اعضای فعلی عضو می‌مانند.')
                ->action(fn () => self::user()->organization->update(['join_code' => OrganizationService::newJoinCode()])),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(OrganizationInvoice::query()->where('organization_id', self::user()->organization_id))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('number')->label('شماره')->state(fn (OrganizationInvoice $r) => $r->number()),
                TextColumn::make('seats')->label('صندلی')->numeric(),
                TextColumn::make('months')->label('ماه'),
                TextColumn::make('amount_rial')->label('مبلغ (ریال)')->numeric(),
                TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (string $state) => OrganizationInvoice::LABELS[$state] ?? $state)
                    ->color(fn (string $state) => ['paid' => 'success', 'failed' => 'danger'][$state] ?? 'warning'),
                TextColumn::make('period_to')->label('اعتبار تا')->date()->placeholder('—'),
                TextColumn::make('ref_id')->label('کد پیگیری')->placeholder('—'),
            ])
            ->emptyStateHeading('هنوز پرداختی ثبت نشده است');
    }
}
