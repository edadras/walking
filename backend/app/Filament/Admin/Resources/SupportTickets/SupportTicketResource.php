<?php

namespace App\Filament\Admin\Resources\SupportTickets;

use App\Domain\Support\SupportService;
use App\Enums\FraudCaseStatus;
use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\FraudCase;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class SupportTicketResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = SupportTicket::class;

    protected static ?string $viewAbility = 'support.manage';

    protected static ?string $manageAbility = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static string|UnitEnum|null $navigationGroup = 'پشتیبانی';

    protected static ?string $modelLabel = 'درخواست پشتیبانی';

    protected static ?string $pluralModelLabel = 'درخواست‌های پشتیبانی';

    public static function getNavigationBadge(): ?string
    {
        $n = SupportTicket::query()->whereIn('status', [TicketStatus::Open, TicketStatus::AwaitingSupport])->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_message_at', 'desc')
            ->columns([
                TextColumn::make('number')->label('شماره')->state(fn (SupportTicket $r) => '#'.strtoupper(substr($r->public_id, -6))),
                TextColumn::make('subject')->label('موضوع')->searchable()->limit(40),
                TextColumn::make('user.phone')->label('کاربر')->searchable(),
                TextColumn::make('category')->label('دسته')->badge()->formatStateUsing(fn (TicketCategory $state) => $state->label()),
                TextColumn::make('priority')->label('اولویت')->badge()->formatStateUsing(fn (string $state) => match ($state) {
                    'high' => 'بالا', 'low' => 'پایین', default => 'عادی'
                })
                    ->color(fn (string $state) => $state === 'high' ? 'danger' : 'gray'),
                TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (TicketStatus $state) => $state->label())
                    ->color(fn (TicketStatus $state) => match ($state) {
                        TicketStatus::Open, TicketStatus::AwaitingSupport => 'warning', TicketStatus::AwaitingUser => 'info', default => 'success'
                    }),
                TextColumn::make('assignee.name')->label('مسئول')->placeholder('—'),
                TextColumn::make('last_message_at')->label('آخرین پیام')->since(),
            ])
            ->filters([
                SelectFilter::make('status')->label('وضعیت')->options(collect(TicketStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                SelectFilter::make('category')->label('دسته')->options(collect(TicketCategory::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                Filter::make('mine')->label('فقط درخواست‌های من')->query(fn (Builder $query) => $query->where('assigned_admin_id', auth('admin')->id())),
                Filter::make('waiting')->label('منتظر پشتیبانی')->default()->query(fn (Builder $query) => $query->whereIn('status', [TicketStatus::Open, TicketStatus::AwaitingSupport])),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('گفتگو')->columnSpan(2)->schema([
                TextEntry::make('subject')->hiddenLabel()->size('lg')->weight('bold'),
                RepeatableEntry::make('messages')->hiddenLabel()->schema([
                    TextEntry::make('author_type')->hiddenLabel()
                        ->formatStateUsing(fn (string $state, SupportMessage $record) => ($state === 'admin' ? 'پشتیبانی' : 'کاربر').($record->is_internal ? ' · یادداشت داخلی' : '').' · '.$record->created_at->diffForHumans())
                        ->color(fn (SupportMessage $record) => $record->is_internal ? 'warning' : ($record->author_type === 'admin' ? 'primary' : 'gray')),
                    TextEntry::make('body')->hiddenLabel()->prose(),
                ]),
            ]),
            Section::make('کاربر')->columnSpan(1)->schema([
                TextEntry::make('user.phone')->label('تلفن'),
                TextEntry::make('user.status')->label('وضعیت حساب')->formatStateUsing(fn ($state) => $state?->label()),
                TextEntry::make('wallet')->label('موجودی')->state(fn (SupportTicket $r) => number_format($r->user->wallet?->available_balance ?? 0).' / در بررسی '.number_format($r->user->wallet?->pending_balance ?? 0)),
                TextEntry::make('fraud')->label('پرونده تقلب باز')->state(fn (SupportTicket $r) => FraudCase::query()->where('user_id', $r->user_id)->whereIn('status', [FraudCaseStatus::Open, FraudCaseStatus::Flagged])->count()),
                TextEntry::make('category')->label('دسته')->formatStateUsing(fn (TicketCategory $state) => $state->label()),
                TextEntry::make('subject_ref')->label('مرتبط با')->placeholder('—')->formatStateUsing(fn ($state, SupportTicket $r) => $r->subject_type.': '.$state),
                TextEntry::make('app_version')->label('نسخه اپ')->placeholder('—'),
                TextEntry::make('status')->label('وضعیت')->formatStateUsing(fn (TicketStatus $state) => $state->label()),
            ]),
        ]);
    }

    /** @return list<Action> */
    public static function deskActions(): array
    {
        $service = fn () => app(SupportService::class);

        return [
            Action::make('reply')->label('پاسخ')->icon('heroicon-o-paper-airplane')
                ->schema([
                    Textarea::make('body')->label('پاسخ به کاربر')->required()->rows(6)->maxLength(3000),
                    Select::make('status')->label('وضعیت پس از پاسخ')->default(TicketStatus::AwaitingUser->value)
                        ->options([TicketStatus::AwaitingUser->value => TicketStatus::AwaitingUser->label(), TicketStatus::Resolved->value => TicketStatus::Resolved->label()]),
                ])
                ->action(function (SupportTicket $record, array $data) use ($service) {
                    $service()->staffReply(auth('admin')->user(), $record, $data['body'], status: TicketStatus::from($data['status']));
                    Notification::make()->title('پاسخ ارسال شد.')->success()->send();
                }),
            Action::make('note')->label('یادداشت داخلی')->color('gray')->icon('heroicon-o-pencil-square')
                ->schema([Textarea::make('body')->label('فقط برای تیم')->required()->rows(4)->maxLength(3000)])
                ->action(fn (SupportTicket $record, array $data) => $service()->staffReply(auth('admin')->user(), $record, $data['body'], internal: true)),
            Action::make('assign')->label('به من')->color('gray')->icon('heroicon-o-user')
                ->visible(fn (SupportTicket $record) => $record->assigned_admin_id !== auth('admin')->id())
                ->action(fn (SupportTicket $record) => $record->forceFill(['assigned_admin_id' => auth('admin')->id()])->save()),
            Action::make('priority')->label('اولویت')->color('gray')
                ->schema([Select::make('priority')->label('اولویت')->required()->options(['low' => 'پایین', 'normal' => 'عادی', 'high' => 'بالا'])])
                ->action(fn (SupportTicket $record, array $data) => $record->forceFill(['priority' => $data['priority']])->save()),
            Action::make('close')->label('بستن')->color('danger')
                ->visible(fn (SupportTicket $record) => $record->status->isOpen())
                ->requiresConfirmation()
                ->action(fn (SupportTicket $record) => $service()->setStatus(auth('admin')->user(), $record, TicketStatus::Closed)),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportTickets::route('/'),
            'view' => Pages\ViewSupportTicket::route('/{record}'),
        ];
    }
}
