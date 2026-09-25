<?php

namespace App\Filament\Sponsor\Pages;

use App\Domain\Sponsor\SponsorTopUps;
use App\Exceptions\ApiException;
use App\Models\SponsorTopUp;
use App\Models\SponsorUser;
use BackedEnum;
use Filament\Actions\Action;
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
use UnitEnum;

/** Owner buys budget points online; the pool is credited after the gateway verifies the payment. */
class Billing extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'گزارش';

    protected static ?string $navigationLabel = 'اعتبار و شارژ';

    protected static ?string $title = 'اعتبار و شارژ بودجه';

    protected static ?string $slug = 'billing';

    public static function canAccess(): bool
    {
        $user = auth('sponsor')->user();

        return $user instanceof SponsorUser && $user->hasAbility('billing.manage');
    }

    public function content(Schema $schema): Schema
    {
        $sponsor = auth('sponsor')->user()->sponsor;
        $topUps = app(SponsorTopUps::class);
        $l = $topUps->limits();

        return $schema->components([
            Text::make('بودجه باقی‌مانده: '.number_format($sponsor->budgetRemaining()).' امتیاز ('.number_format($sponsor->points_spent).' مصرف‌شده از '.number_format($sponsor->point_budget).')'),
            Text::make('قیمت هر امتیاز: '.number_format($l['price']).' ریال · شارژ بین '.number_format($l['min']).' و '.number_format($l['max']).' ریال'
                .($topUps->available() ? '' : ' · درگاه پرداخت فعال نیست؛ برای شارژ با پشتیبانی تماس بگیرید.')),
            EmbeddedTable::make(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        $topUps = app(SponsorTopUps::class);
        $l = $topUps->limits();

        return [
            Action::make('topup')->label('شارژ آنلاین')->icon(Heroicon::OutlinedCreditCard)
                ->visible(fn () => $topUps->available() && auth('sponsor')->user()->sponsor->isApproved())
                ->schema([
                    TextInput::make('amount')->label('مبلغ (ریال)')->numeric()->integer()->required()->minValue($l['min'])->maxValue($l['max'])->live(debounce: 400)
                        ->helperText(fn ($state) => (int) $state > 0 ? '≈ '.number_format(intdiv((int) $state, $l['price'])).' امتیاز به بودجه اضافه می‌شود' : null),
                ])
                ->action(function (array $data) use ($topUps) {
                    $user = auth('sponsor')->user();
                    try {
                        $payment = $topUps->start($user->sponsor, $user, (int) $data['amount']);
                    } catch (ApiException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();

                        return null;
                    }

                    return redirect()->away($payment->pay_url);
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(SponsorTopUp::query()->where('sponsor_id', auth('sponsor')->user()->sponsor_id))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('number')->label('شماره')->state(fn (SponsorTopUp $r) => $r->number()),
                TextColumn::make('amount_rial')->label('مبلغ (ریال)')->numeric(),
                TextColumn::make('points')->label('امتیاز')->numeric(),
                TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (string $state) => SponsorTopUp::LABELS[$state] ?? $state)
                    ->color(fn (string $state) => ['paid' => 'success', 'failed' => 'danger'][$state] ?? 'warning'),
                TextColumn::make('ref_id')->label('کد پیگیری')->placeholder('—'),
                TextColumn::make('sponsorUser.name')->label('توسط')->placeholder('—'),
                TextColumn::make('created_at')->label('زمان')->dateTime(),
            ])
            ->emptyStateHeading('هنوز شارژی ثبت نشده است');
    }
}
