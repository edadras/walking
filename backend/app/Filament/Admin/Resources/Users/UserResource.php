<?php

namespace App\Filament\Admin\Resources\Users;

use App\Domain\User\UserModeration;
use App\Enums\UserStatus;
use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\Admin;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\IconEntry;
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
use UnitEnum;

class UserResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = User::class;

    protected static ?string $viewAbility = 'users.view';

    protected static ?string $manageAbility = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'کاربران';

    protected static ?string $modelLabel = 'کاربر';

    protected static ?string $pluralModelLabel = 'کاربران';

    protected static ?string $recordTitleAttribute = 'display_name';

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('display_name')->label('نام نمایشی')->placeholder('—')->searchable(),
                TextColumn::make('phone')->label('موبایل')
                    ->formatStateUsing(fn (User $record) => $record->maskedPhone())
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('phone', 'like', '%'.ltrim(preg_replace('/\D/', '', $search), '0').'%')),
                TextColumn::make('status')->label('وضعیت')->badge()
                    ->formatStateUsing(fn (UserStatus $state) => $state->label())
                    ->color(fn (UserStatus $state) => match ($state) {
                        UserStatus::Active => 'success',
                        UserStatus::Suspended => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('level')->label('سطح')->sortable(),
                TextColumn::make('referral_code')->label('کد دعوت')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_active_at')->label('آخرین فعالیت')->since()->sortable(),
                TextColumn::make('created_at')->label('عضویت')->date('Y-m-d')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('وضعیت')
                    ->options(collect(UserStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('حساب')->columns(3)->schema([
                TextEntry::make('public_id')->label('شناسه')->copyable(),
                TextEntry::make('display_name')->label('نام نمایشی')->placeholder('—'),
                TextEntry::make('phone')->label('موبایل')->formatStateUsing(fn (User $record) => $record->maskedPhone()),
                TextEntry::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (UserStatus $state) => $state->label()),
                TextEntry::make('status_reason')->label('دلیل وضعیت')->placeholder('—'),
                TextEntry::make('timezone')->label('منطقه زمانی'),
                TextEntry::make('level')->label('سطح'),
                TextEntry::make('xp')->label('XP')->numeric(),
                IconEntry::make('leaderboard_visible')->label('نمایش در رتبه‌بندی')->boolean(),
                TextEntry::make('referrer.display_name')->label('معرف')->placeholder('—'),
                TextEntry::make('created_at')->label('تاریخ عضویت')->dateTime(),
                TextEntry::make('last_active_at')->label('آخرین فعالیت')->since()->placeholder('—'),
            ]),
            Section::make('پروفایل')->columns(4)->schema([
                TextEntry::make('profile.birth_year')->label('سال تولد')->placeholder('—'),
                TextEntry::make('profile.height_cm')->label('قد (cm)')->placeholder('—'),
                TextEntry::make('profile.weight_kg')->label('وزن (kg)')->placeholder('—'),
                TextEntry::make('profile.daily_step_goal')->label('هدف روزانه')->numeric(),
            ]),
        ]);
    }

    /** @return list<Action> */
    public static function moderationActions(): array
    {
        $action = fn (string $name, string $label, UserStatus $status, string $color) => Action::make($name)
            ->label($label)
            ->color($color)
            ->visible(fn (User $record) => $record->status !== $status && static::allows('users.moderate'))
            ->requiresConfirmation()
            ->schema([Textarea::make('reason')->label('دلیل')->required()->maxLength(500)])
            ->action(function (User $record, array $data) use ($status) {
                /** @var Admin $admin */
                $admin = auth('admin')->user();
                app(UserModeration::class)->setStatus($record, $status, $data['reason'], $admin);
                Notification::make()->title('وضعیت کاربر به‌روزرسانی شد.')->success()->send();
            });

        return [
            $action('activate', 'فعال‌سازی', UserStatus::Active, 'success'),
            $action('suspend', 'تعلیق', UserStatus::Suspended, 'warning'),
            $action('ban', 'مسدودسازی', UserStatus::Banned, 'danger'),
        ];
    }

    public static function getRelations(): array
    {
        return [RelationManagers\DevicesRelationManager::class, RelationManagers\DailyActivitiesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'view' => Pages\ViewUser::route('/{record}'),
        ];
    }
}
