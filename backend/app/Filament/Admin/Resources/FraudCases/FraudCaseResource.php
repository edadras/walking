<?php

namespace App\Filament\Admin\Resources\FraudCases;

use App\Domain\Fraud\FraudCaseService;
use App\Enums\FraudCaseStatus;
use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\FraudCase;
use App\Models\FraudEvent;
use App\Models\WalkingSession;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
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
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class FraudCaseResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = FraudCase::class;

    protected static ?string $viewAbility = 'fraud.manage';

    protected static ?string $manageAbility = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static string|UnitEnum|null $navigationGroup = 'ضد تقلب';

    protected static ?string $modelLabel = 'پرونده';

    protected static ?string $pluralModelLabel = 'پرونده‌های تقلب';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $open = FraudCase::query()->whereIn('status', [FraudCaseStatus::Open, FraudCaseStatus::Flagged])->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user:id,public_id,display_name,referral_code');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('زمان')->since()->sortable(),
                TextColumn::make('user.display_name')->label('کاربر')->placeholder('—'),
                TextColumn::make('risk_score')->label('ریسک')->sortable()->badge()
                    ->color(fn (int $state) => $state >= 70 ? 'danger' : 'warning'),
                TextColumn::make('reason')->label('دلیل')->limit(60)->wrap(),
                TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (FraudCaseStatus $state) => $state->label())
                    ->color(fn (FraudCaseStatus $state) => match ($state) {
                        FraudCaseStatus::Open, FraudCaseStatus::Flagged => 'warning',
                        FraudCaseStatus::Approved, FraudCaseStatus::Safe => 'success',
                        default => 'danger',
                    }),
                TextColumn::make('decider.name')->label('تصمیم‌گیرنده')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('وضعیت')->default('open')
                    ->options(collect(FraudCaseStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('پرونده')->columns(4)->schema([
                TextEntry::make('user.display_name')->label('کاربر')
                    ->url(fn (FraudCase $r) => route('filament.admin.resources.users.view', $r->user->public_id)),
                TextEntry::make('risk_score')->label('ریسک'),
                TextEntry::make('status')->label('وضعیت')->formatStateUsing(fn (FraudCaseStatus $state) => $state->label()),
                TextEntry::make('created_at')->label('ایجاد')->dateTime(),
                TextEntry::make('reason')->label('دلیل')->columnSpanFull(),
                TextEntry::make('decision_note')->label('یادداشت تصمیم')->placeholder('—')->columnSpanFull(),
            ]),
            Section::make('جلسه')->columns(4)->visible(fn (FraudCase $r) => $r->subject_type === 'walking_session')->schema([
                TextEntry::make('session_raw')->label('قدم ادعایی')->state(fn (FraudCase $r) => WalkingSession::query()->find($r->subject_id)?->raw_steps),
                TextEntry::make('session_verified')->label('قدم محاسبه‌شده')->state(fn (FraudCase $r) => WalkingSession::query()->find($r->subject_id)?->verified_steps),
                TextEntry::make('session_confidence')->label('اطمینان')->state(fn (FraudCase $r) => WalkingSession::query()->find($r->subject_id)?->confidence_score),
                TextEntry::make('session_link')->label('جزئیات')->state('مشاهده جلسه')
                    ->url(fn (FraudCase $r) => $r->subject_id ? route('filament.admin.resources.walking-sessions.view', WalkingSession::query()->find($r->subject_id)?->public_id ?? '-') : null),
            ]),
            Section::make('سیگنال‌ها')->schema([
                RepeatableEntry::make('signals')->hiddenLabel()
                    ->state(fn (FraudCase $r) => FraudEvent::query()->where('subject_type', $r->subject_type)->where('subject_id', $r->subject_id)->get()
                        ->map(fn (FraudEvent $e) => ['rule' => $e->rule_key, 'score' => $e->score, 'severity' => $e->severity, 'details' => json_encode($e->details, JSON_UNESCAPED_UNICODE)])->all())
                    ->columns(4)
                    ->schema([
                        TextEntry::make('rule')->label('قانون'),
                        TextEntry::make('score')->label('امتیاز ریسک'),
                        TextEntry::make('severity')->label('شدت')->badge(),
                        TextEntry::make('details')->label('شواهد'),
                    ]),
            ]),
        ]);
    }

    /** @return list<Action> */
    public static function decisionActions(): array
    {
        $make = fn (string $name, string $label, string $color, string $method, bool $noteRequired = false) => Action::make($name)
            ->label($label)
            ->color($color)
            ->visible(fn (FraudCase $record) => $record->status->isOpen() && static::allows('fraud.manage'))
            ->requiresConfirmation()
            ->schema([Textarea::make('note')->label('یادداشت')->required($noteRequired)->maxLength(1000)])
            ->action(function (FraudCase $record, array $data) use ($method) {
                app(FraudCaseService::class)->{$method}($record, auth('admin')->user(), $data['note'] ?? null);
                Notification::make()->title('تصمیم ثبت شد.')->success()->send();
            });

        return [
            $make('approve', 'تأیید', 'success', 'approve'),
            $make('safe', 'امن (بدون تقلب)', 'success', 'markSafe'),
            $make('flag', 'علامت‌گذاری', 'warning', 'flag'),
            $make('reject', 'رد', 'danger', 'reject', true),
            $make('ban', 'مسدودسازی کاربر', 'danger', 'ban', true),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFraudCases::route('/'),
            'view' => Pages\ViewFraudCase::route('/{record}'),
        ];
    }
}
