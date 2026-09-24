<?php

namespace App\Filament\Admin\Resources\Orders;

use App\Domain\Store\OrderService;
use App\Enums\OrderStatus;
use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\Order;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class OrderResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = Order::class;

    protected static ?string $viewAbility = 'orders.manage';

    protected static ?string $manageAbility = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'فروشگاه';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'سفارش';

    protected static ?string $pluralModelLabel = 'سفارش‌ها';

    public static function getNavigationBadge(): ?string
    {
        $n = Order::query()->whereIn('status', [OrderStatus::Paid, OrderStatus::Processing])->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('number')->label('شماره')->state(fn (Order $r) => '#'.strtoupper(substr($r->public_id, -6))),
                TextColumn::make('user.phone')->label('کاربر')->searchable(),
                TextColumn::make('items_count')->label('اقلام')->counts('items'),
                TextColumn::make('total_points')->label('امتیاز')->numeric(),
                TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (OrderStatus $state) => $state->label())
                    ->color(fn (OrderStatus $state) => match ($state) {
                        OrderStatus::Paid, OrderStatus::Processing => 'warning',
                        OrderStatus::Delivered => 'success',
                        OrderStatus::Refunded, OrderStatus::Cancelled => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('placed_at')->label('ثبت')->dateTime(),
            ])
            ->filters([SelectFilter::make('status')->label('وضعیت')->options(collect(OrderStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]))])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('سفارش')->columns(4)->schema([
                TextEntry::make('public_id')->label('شناسه'),
                TextEntry::make('user.phone')->label('کاربر'),
                TextEntry::make('total_points')->label('امتیاز')->numeric(),
                TextEntry::make('status')->label('وضعیت')->formatStateUsing(fn (OrderStatus $state) => $state->label()),
                TextEntry::make('tracking_code')->label('کد رهگیری')->placeholder('—'),
                TextEntry::make('user_note')->label('یادداشت کاربر')->placeholder('—'),
            ]),
            Section::make('اقلام')->schema([
                RepeatableEntry::make('items')->hiddenLabel()->columns(4)->schema([
                    TextEntry::make('name')->label('کالا'),
                    TextEntry::make('type')->label('نوع')->formatStateUsing(fn ($state) => $state->label()),
                    TextEntry::make('quantity')->label('تعداد'),
                    TextEntry::make('unit_point_price')->label('قیمت واحد')->numeric(),
                ]),
            ]),
            Section::make('ارسال')->visible(fn (Order $r) => $r->shipping_address !== null)->columns(3)->schema([
                TextEntry::make('shipping_address.recipient')->label('گیرنده'),
                TextEntry::make('shipping_address.phone')->label('تلفن'),
                TextEntry::make('shipping_address.postal_code')->label('کد پستی'),
                TextEntry::make('shipping_address.province')->label('استان'),
                TextEntry::make('shipping_address.city')->label('شهر'),
                TextEntry::make('shipping_address.line')->label('نشانی'),
            ]),
            Section::make('تاریخچه')->schema([
                RepeatableEntry::make('history')->hiddenLabel()->columns(3)->schema([
                    TextEntry::make('to_status')->label('وضعیت')->formatStateUsing(fn (OrderStatus $state) => $state->label()),
                    TextEntry::make('note')->label('توضیح')->placeholder('—'),
                    TextEntry::make('created_at')->label('زمان')->dateTime(),
                ]),
            ]),
        ]);
    }

    /** @return list<Action> */
    public static function fulfilmentActions(): array
    {
        $move = function (Order $record, OrderStatus $to, array $data = []) {
            app(OrderService::class)->transition($record, $to, auth('admin')->user(), $data['note'] ?? null, $data['tracking_code'] ?? null);
            Notification::make()->title('وضعیت سفارش: '.$to->label())->success()->send();
        };
        $allowed = fn (Order $r, OrderStatus $to) => static::allows('orders.manage') && $r->status->canMoveTo($to);

        return [
            Action::make('processing')->label('آماده‌سازی')->color('warning')
                ->visible(fn (Order $r) => $allowed($r, OrderStatus::Processing))
                ->requiresConfirmation()->action(fn (Order $record) => $move($record, OrderStatus::Processing)),
            Action::make('ship')->label('ارسال')->color('primary')
                ->visible(fn (Order $r) => $allowed($r, OrderStatus::Shipped))
                ->schema([TextInput::make('tracking_code')->label('کد رهگیری')->required()->maxLength(64)])
                ->action(fn (Order $record, array $data) => $move($record, OrderStatus::Shipped, $data)),
            Action::make('deliver')->label('تحویل شد')->color('success')
                ->visible(fn (Order $r) => $allowed($r, OrderStatus::Delivered))
                ->requiresConfirmation()->action(fn (Order $record) => $move($record, OrderStatus::Delivered)),
            Action::make('refund')->label('بازگشت امتیاز')->color('danger')
                ->visible(fn (Order $r) => $allowed($r, OrderStatus::Refunded))
                ->schema([Textarea::make('note')->label('دلیل (به کاربر اعلام می‌شود)')->required()->maxLength(300)])
                ->action(fn (Order $record, array $data) => $move($record, OrderStatus::Refunded, $data)),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
