<?php

namespace App\Filament\Admin\Resources\ClientErrors;

use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\ClientError;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** App crash reports, grouped. Read-only apart from marking a group fixed. */
class ClientErrorResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = ClientError::class;

    protected static ?string $viewAbility = 'ops.view';

    protected static ?string $manageAbility = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBugAnt;

    protected static string|UnitEnum|null $navigationGroup = 'امنیت';

    protected static ?string $modelLabel = 'خطای اپ';

    protected static ?string $pluralModelLabel = 'خطاهای اپ';

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $open = ClientError::query()->whereNull('resolved_at')->where('last_seen_at', '>=', now()->subDay())->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_seen_at', 'desc')
            ->columns([
                IconColumn::make('fatal')->label('کرش')->boolean()->trueIcon(Heroicon::OutlinedExclamationTriangle)->falseIcon(Heroicon::OutlinedMinus)->trueColor('danger')->falseColor('gray'),
                TextColumn::make('error_type')->label('نوع')->fontFamily('mono')->searchable(),
                TextColumn::make('message')->label('پیام')->limit(70)->searchable()->wrap(),
                TextColumn::make('occurrences')->label('تعداد')->numeric()->sortable(),
                TextColumn::make('app_version')->label('نسخه')->placeholder('—'),
                TextColumn::make('last_seen_at')->label('آخرین بار')->since()->sortable(),
                TextColumn::make('resolved_at')->label('وضعیت')->badge()
                    ->state(fn (ClientError $r) => $r->resolved_at ? 'رفع‌شده' : 'باز')
                    ->color(fn (ClientError $r) => $r->resolved_at ? 'success' : 'warning'),
            ])
            ->filters([
                TernaryFilter::make('open')->label('وضعیت')->placeholder('همه')->trueLabel('باز')->falseLabel('رفع‌شده')->default(true)
                    ->queries(true: fn (Builder $q) => $q->whereNull('resolved_at'), false: fn (Builder $q) => $q->whereNotNull('resolved_at')),
                TernaryFilter::make('fatal')->label('فقط کرش‌ها'),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('resolve')->label('رفع شد')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->visible(fn (ClientError $r) => $r->resolved_at === null)
                    ->action(fn (ClientError $r) => $r->forceFill(['resolved_at' => now()])->save()),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('error_type')->label('نوع')->fontFamily('mono'),
            TextEntry::make('occurrences')->label('تعداد رخداد')->numeric(),
            TextEntry::make('first_seen_at')->label('اولین بار')->dateTime(),
            TextEntry::make('last_seen_at')->label('آخرین بار')->dateTime(),
            TextEntry::make('app_version')->label('نسخه اپ')->placeholder('—'),
            TextEntry::make('lastUser.phone')->label('آخرین کاربر')->placeholder('—')
                ->formatStateUsing(fn (?string $state) => $state ? substr($state, 0, 5).'•••'.substr($state, -2) : null),
            TextEntry::make('message')->label('پیام')->columnSpanFull(),
            TextEntry::make('stack')->label('Stack trace')->fontFamily('mono')->columnSpanFull()->extraAttributes(['dir' => 'ltr', 'style' => 'white-space: pre-wrap; font-size: 12px']),
        ])->columns(2);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListClientErrors::route('/')];
    }
}
