<?php

namespace App\Filament\Admin\Resources\WalkingSessions;

use App\Enums\ActivityType;
use App\Enums\SessionKind;
use App\Enums\SessionStatus;
use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\WalkingSession;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
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

/** Read-only: sessions are evidence and are never edited by hand. */
class WalkingSessionResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = WalkingSession::class;

    protected static ?string $viewAbility = 'users.view';

    protected static ?string $manageAbility = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'فعالیت';

    protected static ?string $modelLabel = 'جلسه پیاده‌روی';

    protected static ?string $pluralModelLabel = 'جلسه‌های پیاده‌روی';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user:id,public_id,display_name,referral_code', 'device:id,public_id,model']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('started_at')->label('شروع')->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('user.display_name')->label('کاربر')->placeholder('—')
                    ->url(fn (WalkingSession $r) => route('filament.admin.resources.users.view', $r->user->public_id)),
                TextColumn::make('kind')->label('نوع')->badge()->formatStateUsing(fn (SessionKind $state) => $state->label()),
                TextColumn::make('raw_steps')->label('قدم ادعایی')->numeric()->sortable(),
                TextColumn::make('verified_steps')->label('قدم تأییدشده')->numeric()->placeholder('—'),
                TextColumn::make('duration_s')->label('مدت')->formatStateUsing(fn (int $state) => gmdate($state >= 3600 ? 'G:i:s' : 'i:s', $state)),
                TextColumn::make('activity_type')->label('فعالیت')->formatStateUsing(fn (ActivityType $state) => $state->label()),
                TextColumn::make('overlap_s')->label('همپوشانی')->suffix(' ث')->color(fn (int $state) => $state > 0 ? 'danger' : null)->toggleable(),
                TextColumn::make('fraud_score')->label('ریسک')->placeholder('—')->sortable()
                    ->color(fn (?int $state) => $state === null ? null : ($state >= 50 ? 'danger' : ($state >= 20 ? 'warning' : 'success'))),
                TextColumn::make('status')->label('وضعیت')->badge()
                    ->formatStateUsing(fn (SessionStatus $state) => $state->label())
                    ->color(fn (SessionStatus $state) => match ($state) {
                        SessionStatus::Verified => 'success',
                        SessionStatus::PartiallyVerified, SessionStatus::UnderReview => 'warning',
                        SessionStatus::Rejected => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->label('وضعیت')->options(collect(SessionStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                SelectFilter::make('kind')->label('نوع')->options(collect(SessionKind::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                Filter::make('overlap')->label('دارای همپوشانی')->query(fn (Builder $q) => $q->where('overlap_s', '>', 0)),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('جلسه')->columns(4)->schema([
                TextEntry::make('public_id')->label('شناسه')->copyable(),
                TextEntry::make('user.display_name')->label('کاربر'),
                TextEntry::make('device.model')->label('دستگاه'),
                TextEntry::make('sequence')->label('ترتیب'),
                TextEntry::make('started_at')->label('شروع')->dateTime(),
                TextEntry::make('ended_at')->label('پایان')->dateTime(),
                TextEntry::make('local_date')->label('روز کاربر')->date(),
                TextEntry::make('kind')->label('نوع')->formatStateUsing(fn (SessionKind $state) => $state->label()),
                TextEntry::make('raw_steps')->label('قدم ادعایی')->numeric(),
                TextEntry::make('verified_steps')->label('قدم تأییدشده')->numeric()->placeholder('—'),
                TextEntry::make('distance_m')->label('مسافت (m)')->numeric(),
                TextEntry::make('calories_kcal')->label('کالری تخمینی'),
                TextEntry::make('confidence_score')->label('اطمینان')->placeholder('—'),
                TextEntry::make('fraud_score')->label('ریسک تقلب')->placeholder('—'),
                TextEntry::make('overlap_s')->label('همپوشانی (ثانیه)'),
                TextEntry::make('status')->label('وضعیت')->formatStateUsing(fn (SessionStatus $state) => $state->label()),
            ]),
            Section::make('خلاصه حرکت و GPS')->columns(2)->schema([
                KeyValueEntry::make('motion_summary')->label('حرکت')
                    ->state(fn (WalkingSession $r) => collect($r->motion_summary ?? [])->map(fn ($v) => is_array($v) ? json_encode($v) : (string) $v)->all()),
                KeyValueEntry::make('gps_summary')->label('GPS')
                    ->state(fn (WalkingSession $r) => collect($r->gps_summary ?? [])->map(fn ($v) => is_array($v) ? json_encode($v) : (string) $v)->all()),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWalkingSessions::route('/'),
            'view' => Pages\ViewWalkingSession::route('/{record}'),
        ];
    }
}
