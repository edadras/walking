<?php

namespace App\Filament\Admin\Resources\Cashout;

use App\Domain\Audit\AuditLogger;
use App\Domain\Cashout\CashoutRequestNumber;
use App\Domain\Cashout\CashoutRisk;
use App\Domain\Cashout\CashoutService;
use App\Domain\Cashout\Providers\PayoutProvider;
use App\Exceptions\ApiException;
use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\CashoutRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Payout queue: pending → approved (finance A) → paid with the bank's reference (finance B ≠ A).
 * Rejecting at any open step refunds the points through the ledger.
 */
class CashoutRequestResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = CashoutRequest::class;

    protected static ?string $viewAbility = 'cashout.manage';

    protected static ?string $manageAbility = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'برداشت نقدی';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'درخواست برداشت';

    protected static ?string $pluralModelLabel = 'درخواست‌های برداشت';

    protected static ?string $slug = 'cashout/requests';

    public static function getNavigationBadge(): ?string
    {
        $n = CashoutRequest::query()->whereIn('status', CashoutRequest::OPEN)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user.identity', 'bankAccount', 'approver']))
            ->columns([
                TextColumn::make('number')->label('شماره')->state(fn (CashoutRequest $r) => static::number($r)),
                TextColumn::make('user.phone')->label('کاربر')->searchable(),
                TextColumn::make('holder')->label('صاحب حساب')->state(fn (CashoutRequest $r) => $r->bankAccount?->holder_name),
                TextColumn::make('points')->label('امتیاز')->numeric(),
                TextColumn::make('amount_rial')->label('مبلغ (ریال)')->numeric(),
                TextColumn::make('risk_score')->label('ریسک')->badge()->sortable()->placeholder('—')
                    ->color(fn (?int $state) => ['high' => 'danger', 'medium' => 'warning', 'low' => 'success'][CashoutRisk::level($state)] ?? 'gray')
                    ->tooltip(fn (CashoutRequest $r) => collect($r->risk_signals ?? [])->pluck('label')->join('، ') ?: 'بدون نشانه'),
                TextColumn::make('bankAccount.bank_name')->label('بانک'),
                TextColumn::make('status')->label('وضعیت')->badge()
                    ->formatStateUsing(fn (string $state) => CashoutRequest::LABELS[$state] ?? $state)->color(fn (string $state) => StatusBadge::color($state)),
                TextColumn::make('approver.name')->label('تأییدکننده')->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->label('ثبت')->dateTime(),
            ])
            ->filters([
                SelectFilter::make('status')->label('وضعیت')->options(CashoutRequest::LABELS),
                SelectFilter::make('risk')->label('ریسک')->options(['high' => 'بالا', 'medium' => 'متوسط', 'low' => 'پایین'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'high' => $query->where('risk_score', '>=', CashoutRisk::HIGH),
                        'medium' => $query->whereBetween('risk_score', [CashoutRisk::MEDIUM, CashoutRisk::HIGH - 1]),
                        'low' => $query->where('risk_score', '<', CashoutRisk::MEDIUM),
                        default => $query,
                    }),
            ])
            ->recordActions([ViewAction::make(), ...static::workflowActions()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('درخواست')->columns(4)->schema([
                TextEntry::make('number')->label('شماره')->state(fn (CashoutRequest $r) => static::number($r)),
                TextEntry::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (string $state) => CashoutRequest::LABELS[$state] ?? $state)
                    ->color(fn (string $state) => StatusBadge::color($state)),
                TextEntry::make('points')->label('امتیاز')->numeric(),
                TextEntry::make('amount_rial')->label('مبلغ واریز (ریال)')->numeric(),
                TextEntry::make('rial_per_point')->label('نرخ هر امتیاز (ریال)')->numeric(),
                TextEntry::make('created_at')->label('ثبت')->dateTime(),
                TextEntry::make('rejection_reason')->label('دلیل رد/لغو')->placeholder('—'),
            ]),
            Section::make('ارزیابی ریسک')->columns(1)->schema([
                TextEntry::make('risk_score')->label('امتیاز ریسک (۰ تا ۱۰۰)')->badge()->placeholder('—')
                    ->color(fn (?int $state) => ['high' => 'danger', 'medium' => 'warning', 'low' => 'success'][CashoutRisk::level($state)] ?? 'gray'),
                TextEntry::make('risk_list')->label('نشانه‌ها')->listWithLineBreaks()->bulleted()
                    ->state(fn (CashoutRequest $r) => collect($r->risk_signals ?? [])->map(fn (array $s) => $s['label'].' (+'.$s['points'].')')->all() ?: ['بدون نشانه']),
            ]),
            Section::make('مقصد واریز')->columns(3)->schema([
                TextEntry::make('user.phone')->label('موبایل'),
                TextEntry::make('bankAccount.holder_name')->label('صاحب حساب'),
                TextEntry::make('bankAccount.bank_name')->label('بانک'),
                // Finance needs the full Sheba to transfer; only once the request is approved.
                TextEntry::make('sheba')->label('شبا')->copyable()
                    ->state(fn (CashoutRequest $r) => $r->status === CashoutRequest::APPROVED ? $r->bankAccount->iban : $r->bankAccount?->masked()),
            ]),
            Section::make('پرداخت')->columns(4)->schema([
                TextEntry::make('approver.name')->label('تأییدکننده')->placeholder('—'),
                TextEntry::make('approved_at')->label('زمان تأیید')->dateTime()->placeholder('—'),
                TextEntry::make('payer.name')->label('ثبت‌کننده واریز')->placeholder('—'),
                TextEntry::make('bank_reference')->label('کد پیگیری بانک')->placeholder('—'),
                TextEntry::make('payout_provider')->label('روش انتقال')->placeholder('دستی (CSV)'),
                TextEntry::make('payout_state')->label('وضعیت انتقال')->placeholder('—'),
                TextEntry::make('payout_error')->label('خطای انتقال')->placeholder('—')->color('danger'),
                TextEntry::make('sender.name')->label('ارسال‌کننده')->placeholder('—'),
            ]),
        ]);
    }

    public static function number(CashoutRequest $r): string
    {
        return CashoutRequestNumber::of($r);
    }

    /** @return list<Action> */
    public static function workflowActions(): array
    {
        $run = function (callable $fn, string $ok) {
            try {
                $fn();
                Notification::make()->title($ok)->success()->send();
            } catch (ApiException $e) {
                Notification::make()->title($e->getMessage())->danger()->send();
            }
        };
        $can = fn () => static::allows('cashout.manage');

        return [
            Action::make('approve')->label('تأیید برداشت')->icon(Heroicon::OutlinedCheck)->color('warning')
                ->visible(fn (CashoutRequest $r) => $can() && $r->status === CashoutRequest::PENDING)
                ->requiresConfirmation()
                ->modalDescription(fn (CashoutRequest $r) => (CashoutRisk::level($r->risk_score) === 'high' ? '⚠️ ریسک بالا: '.collect($r->risk_signals)->pluck('label')->join('، ').".\n" : '')
                    .'واریز '.number_format($r->amount_rial).' ریال به «'.$r->bankAccount?->holder_name.'» تأیید شود؟ ثبت واریز باید توسط مدیر دیگری انجام شود.')
                ->action(fn (CashoutRequest $record) => $run(fn () => app(CashoutService::class)->approve($record, static::admin()), 'تأیید شد؛ آماده واریز')),
            Action::make('send')->label('ارسال به بانک')->icon(Heroicon::OutlinedPaperAirplane)->color('success')
                ->visible(fn (CashoutRequest $r) => $can() && app(PayoutProvider::class)->automatic() && $r->status === CashoutRequest::APPROVED && $r->approved_by !== static::admin()?->id)
                ->requiresConfirmation()
                ->modalDescription(fn (CashoutRequest $r) => 'انتقال '.number_format($r->amount_rial).' ریال به شبای «'.$r->bankAccount?->holder_name.'» از طریق '.app(PayoutProvider::class)->name().' ارسال شود؟')
                ->action(fn (CashoutRequest $record) => $run(fn () => app(CashoutService::class)->sendToBank($record, static::admin()), 'به بانک ارسال شد')),
            Action::make('paid')->label('ثبت واریز')->icon(Heroicon::OutlinedBanknotes)->color('success')
                ->visible(fn (CashoutRequest $r) => $can() && ! app(PayoutProvider::class)->automatic() && $r->status === CashoutRequest::APPROVED && $r->approved_by !== static::admin()?->id)
                ->schema([TextInput::make('bank_reference')->label('کد پیگیری/شماره مرجع انتقال')->required()->maxLength(64)])
                ->action(fn (CashoutRequest $record, array $data) => $run(fn () => app(CashoutService::class)->markPaid($record, static::admin(), trim($data['bank_reference'])), 'واریز ثبت شد')),
            Action::make('reassess')->label('ارزیابی مجدد ریسک')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                ->visible(fn (CashoutRequest $r) => $can() && in_array($r->status, CashoutRequest::OPEN, true))
                ->action(fn (CashoutRequest $record) => $run(fn () => app(CashoutService::class)->reassess($record), 'ریسک به‌روز شد')),
            Action::make('reject')->label('رد و بازگشت امتیاز')->icon(Heroicon::OutlinedXMark)->color('danger')
                ->visible(fn (CashoutRequest $r) => $can() && in_array($r->status, CashoutRequest::OPEN, true))
                ->schema([Textarea::make('reason')->label('دلیل (به کاربر اعلام می‌شود)')->required()->maxLength(200)])
                ->action(fn (CashoutRequest $record, array $data) => $run(fn () => app(CashoutService::class)->reject($record, static::admin(), $data['reason']), 'رد شد و امتیاز برگشت')),
        ];
    }

    /** Approved requests as a CSV for the bank's batch (Paya/Satna) transfer upload. */
    public static function exportApproved(): StreamedResponse
    {
        $rows = CashoutRequest::query()->where('status', CashoutRequest::APPROVED)->with('bankAccount')->orderBy('id')->get();
        app(AuditLogger::class)->log('cashout.exported', meta: ['count' => $rows->count(), 'ids' => $rows->pluck('public_id')->all()]);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excel needs the BOM for Persian text
            fputcsv($out, ['شماره', 'صاحب حساب', 'شبا', 'بانک', 'مبلغ (ریال)'], escape: '');
            foreach ($rows as $r) {
                fputcsv($out, [static::number($r), $r->bankAccount->holder_name, $r->bankAccount->iban, $r->bankAccount->bank_name, $r->amount_rial], escape: '');
            }
            fclose($out);
        }, 'cashout-approved-'.now()->format('Ymd-Hi').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCashoutRequests::route('/'),
            'view' => Pages\ViewCashoutRequest::route('/{record}'),
        ];
    }
}
