<?php

namespace App\Filament\Admin\Resources\Announcements;

use App\Domain\Audit\AuditLogger;
use App\Filament\Admin\Concerns\RequiresAbility;
use App\Jobs\SendAnnouncement;
use App\Models\Announcement;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AnnouncementResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = Announcement::class;

    protected static ?string $viewAbility = 'content.manage';

    protected static ?string $manageAbility = 'content.manage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'تعامل';

    protected static ?string $modelLabel = 'اطلاعیه';

    protected static ?string $pluralModelLabel = 'اطلاعیه‌ها';

    public static function canEdit(Model $record): bool
    {
        return $record->sent_at === null && parent::canEdit($record);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('عنوان')->required()->maxLength(80),
            Textarea::make('body')->label('متن')->required()->maxLength(300)->rows(3),
            TextInput::make('deep_link')->label('لینک داخلی (اختیاری)')->placeholder('/rewards')->regex('/^\/[a-z0-9\/_-]*$/'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('title')->label('عنوان'),
                TextColumn::make('sent_at')->label('ارسال')->dateTime()->placeholder('ارسال‌نشده'),
                TextColumn::make('recipients')->label('گیرندگان')->numeric(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('send')->label('ارسال به همه')->color('warning')
                    ->visible(fn (Announcement $r) => $r->sent_at === null && static::allows('content.manage'))
                    ->requiresConfirmation()
                    ->modalDescription('این اطلاعیه برای همه کاربران فعال ارسال می‌شود (فقط کاربرانی که اعلان «اطلاعیه‌ها» را خاموش نکرده‌اند Push دریافت می‌کنند).')
                    ->action(function (Announcement $r) {
                        SendAnnouncement::dispatch($r->id);
                        app(AuditLogger::class)->log('announcement.sent', $r);
                        Notification::make()->title('ارسال در صف قرار گرفت.')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnnouncements::route('/'),
            'create' => Pages\CreateAnnouncement::route('/create'),
            'edit' => Pages\EditAnnouncement::route('/{record}/edit'),
        ];
    }
}
