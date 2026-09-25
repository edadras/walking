<?php

namespace App\Filament\Admin\Resources\ReconciliationRuns;

use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\ReconciliationRun;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Nightly `ledger:reconcile` results. Findings are fixed by hand through audited adjustments. */
class ReconciliationRunResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = ReconciliationRun::class;

    protected static ?string $viewAbility = 'wallet.view';

    protected static ?string $manageAbility = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'امتیاز و کیف پول';

    protected static ?int $navigationSort = 9;

    protected static ?string $modelLabel = 'مغایرت‌گیری';

    protected static ?string $pluralModelLabel = 'مغایرت‌گیری دفتر کل';

    public const KINDS = [
        'available_mismatch' => 'موجودی قابل استفاده ≠ دفتر کل',
        'pending_mismatch' => 'موجودی در انتظار ≠ دفتر کل',
        'negative_balance' => 'مانده منفی',
        'ledger_without_wallet' => 'تراکنش بدون کیف پول',
        'cashout_debit_missing' => 'برداشت بدون کسر امتیاز',
        'cashout_refund_missing' => 'برداشت رد/لغوشده بدون بازگشت امتیاز',
        'cashout_refunded_but_open' => 'بازگشت امتیاز برای درخواست باز',
        'cashout_paid_without_controls' => 'واریز بدون کد پیگیری یا چهار چشم',
    ];

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('started_at')->label('زمان')->dateTime(),
                TextColumn::make('status')->label('نتیجه')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'ok' ? 'بدون مغایرت' : 'مغایرت')->color(fn (string $state) => $state === 'ok' ? 'success' : 'danger'),
                TextColumn::make('issue_count')->label('موارد')->numeric(),
                TextColumn::make('wallets_checked')->label('کیف پول‌ها')->numeric(),
                TextColumn::make('cashouts_checked')->label('برداشت‌ها')->numeric(),
                TextColumn::make('totals.points_available')->label('امتیاز در گردش')->numeric(),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('خلاصه')->columns(4)->schema([
                TextEntry::make('started_at')->label('زمان')->dateTime(),
                TextEntry::make('issue_count')->label('موارد')->numeric(),
                TextEntry::make('totals.points_available')->label('امتیاز قابل استفاده')->numeric(),
                TextEntry::make('totals.points_pending')->label('امتیاز در انتظار')->numeric(),
                TextEntry::make('totals.cashout_paid_rial')->label('کل واریز شده (ریال)')->numeric(),
                TextEntry::make('totals.cashout_open_rial')->label('برداشت باز (ریال)')->numeric(),
            ]),
            Section::make('موارد')->visible(fn (ReconciliationRun $r) => $r->issue_count > 0)->schema([
                KeyValueEntry::make('issues_list')->hiddenLabel()->keyLabel('نوع')->valueLabel('جزئیات')
                    ->state(fn (ReconciliationRun $r) => collect($r->issues)->mapWithKeys(fn (array $i, int $n) => [
                        ($n + 1).'. '.(self::KINDS[$i['kind']] ?? $i['kind']) => json_encode(array_diff_key($i, ['kind' => 1]), JSON_UNESCAPED_UNICODE),
                    ])->all()),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReconciliationRuns::route('/'),
            'view' => Pages\ViewReconciliationRun::route('/{record}'),
        ];
    }
}
