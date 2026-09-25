<?php

namespace App\Filament\Admin\Resources\Devices;

use App\Domain\Audit\AuditLogger;
use App\Domain\User\UserModeration;
use App\Enums\DeviceStatus;
use App\Enums\IntegrityVerdict;
use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\Device;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class DeviceResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = Device::class;

    protected static ?string $viewAbility = 'devices.view';

    protected static ?string $manageAbility = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static string|UnitEnum|null $navigationGroup = 'کاربران';

    protected static ?string $modelLabel = 'دستگاه';

    protected static ?string $pluralModelLabel = 'دستگاه‌ها';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('users');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('public_id')->label('شناسه')->limit(10)->copyable()->searchable(),
                TextColumn::make('model')->label('مدل')->formatStateUsing(fn (Device $record) => trim($record->manufacturer.' '.$record->model)),
                TextColumn::make('app_version')->label('نسخه اپ'),
                TextColumn::make('store')->label('فروشگاه')->badge()->placeholder('—')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'play' => 'Google Play', 'bazaar' => 'بازار', 'myket' => 'مایکت', 'direct' => 'مستقیم', default => $state,
                    }),
                TextColumn::make('integrity_verdict')->label('Integrity')->badge()
                    ->formatStateUsing(fn (?IntegrityVerdict $state) => $state?->label())
                    ->color(fn (?IntegrityVerdict $state) => match ($state) {
                        IntegrityVerdict::Strong, IntegrityVerdict::Device => 'success',
                        IntegrityVerdict::None => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('trust_score')->label('اعتماد')->sortable(),
                TextColumn::make('users_count')->label('حساب‌ها')->sortable()
                    ->color(fn (int $state) => $state >= 3 ? 'danger' : null),
                IconColumn::make('emulator_suspected')->label('شبیه‌ساز')->boolean(),
                IconColumn::make('root_suspected')->label('روت')->boolean(),
                TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (DeviceStatus $state) => $state->label())
                    ->color(fn (DeviceStatus $state) => $state === DeviceStatus::Active ? 'success' : 'danger'),
                TextColumn::make('last_seen_at')->label('آخرین مشاهده')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('وضعیت')->options(collect(DeviceStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                SelectFilter::make('store')->label('فروشگاه')->options(['play' => 'Google Play', 'bazaar' => 'بازار', 'myket' => 'مایکت', 'direct' => 'مستقیم']),
                SelectFilter::make('integrity_verdict')->label('Integrity')->options(collect(IntegrityVerdict::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                TernaryFilter::make('root_suspected')->label('روت'),
            ])
            ->recordActions([
                Action::make('toggle_block')
                    ->label(fn (Device $record) => $record->isActive() ? 'مسدود کردن' : 'رفع مسدودی')
                    ->color(fn (Device $record) => $record->isActive() ? 'danger' : 'success')
                    ->visible(fn () => static::allows('devices.moderate'))
                    ->requiresConfirmation()
                    ->schema([Textarea::make('reason')->label('دلیل')->required()->maxLength(500)])
                    ->action(function (Device $record, array $data) {
                        app(UserModeration::class)->setDeviceStatus(
                            $record,
                            $record->isActive() ? DeviceStatus::Blocked : DeviceStatus::Active,
                            $data['reason'],
                            auth('admin')->user(),
                        );
                        Notification::make()->title('وضعیت دستگاه به‌روزرسانی شد.')->success()->send();
                    }),
                Action::make('require_rotation')->label('الزام تعویض کلید')->color('warning')->icon('heroicon-o-key')
                    ->visible(fn (Device $record) => static::allows('devices.moderate') && ! $record->key_rotation_required)
                    ->requiresConfirmation()
                    ->modalDescription('تا زمانی که اپ کلید جدید بسازد و ثبت کند، درخواست‌های امضاشده این دستگاه پذیرفته نمی‌شوند.')
                    ->action(function (Device $record) {
                        $record->forceFill(['key_rotation_required' => true])->save();
                        app(AuditLogger::class)->log('device.key_rotation_required', $record);
                        Notification::make()->title('تعویض کلید در اولین درخواست بعدی انجام می‌شود.')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListDevices::route('/')];
    }
}
