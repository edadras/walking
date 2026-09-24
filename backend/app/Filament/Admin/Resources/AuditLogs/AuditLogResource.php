<?php

namespace App\Filament\Admin\Resources\AuditLogs;

use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Read-only: there is intentionally no edit or delete page. */
class AuditLogResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = AuditLog::class;

    protected static ?string $viewAbility = 'audit.view';

    protected static ?string $manageAbility = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'امنیت';

    protected static ?string $modelLabel = 'رویداد';

    protected static ?string $pluralModelLabel = 'گزارش عملیات (Audit)';

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('زمان')->dateTime()->sortable(),
                TextColumn::make('actor_type')->label('انجام‌دهنده')->badge()
                    ->formatStateUsing(fn (AuditLog $record) => $record->actor_type.($record->actor_id ? ' #'.$record->actor_id : '')),
                TextColumn::make('action')->label('عملیات')->fontFamily('mono')->searchable(),
                TextColumn::make('subject_type')->label('موضوع')
                    ->formatStateUsing(fn (AuditLog $record) => class_basename((string) $record->subject_type).($record->subject_id ? ' #'.$record->subject_id : '')),
            ])
            ->filters([
                SelectFilter::make('actor_type')->label('نوع انجام‌دهنده')
                    ->options(['admin' => 'Admin', 'sponsor_user' => 'Sponsor', 'user' => 'کاربر', 'system' => 'سیستم']),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('action')->label('عملیات'),
            TextEntry::make('created_at')->label('زمان')->dateTime(),
            TextEntry::make('actor_type')->label('انجام‌دهنده'),
            TextEntry::make('actor_id')->label('شناسه انجام‌دهنده')->placeholder('—'),
            TextEntry::make('subject_type')->label('نوع موضوع')->placeholder('—'),
            TextEntry::make('subject_id')->label('شناسه موضوع')->placeholder('—'),
            KeyValueEntry::make('old_values')->label('مقادیر قبلی')->columnSpanFull(),
            KeyValueEntry::make('new_values')->label('مقادیر جدید')->columnSpanFull(),
            KeyValueEntry::make('meta')->label('جزئیات')->columnSpanFull(),
        ])->columns(2);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAuditLogs::route('/')];
    }
}
