<?php

namespace App\Filament\Admin\Resources\Posts;

use App\Domain\Audit\AuditLogger;
use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\Post;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/** Walk photos: reported ones first. Hide / restore / delete are audited. */
class PostResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = Post::class;

    protected static ?string $viewAbility = 'content.manage';

    protected static ?string $manageAbility = 'content.manage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'محتوا';

    protected static ?string $modelLabel = 'عکس پیاده‌روی';

    protected static ?string $pluralModelLabel = 'عکس‌های پیاده‌روی';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Post::query()->where('status', Post::HIDDEN)->where('reports_count', '>', 0)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->defaultSort('id', 'desc')
            ->columns([
                ImageColumn::make('thumb_path')->label('عکس')->disk('public')->height(64),
                TextColumn::make('user.phone')->label('کاربر')->description(fn (Post $p) => $p->user?->publicName()),
                TextColumn::make('caption')->label('متن')->limit(40)->placeholder('—'),
                TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (string $state) => Post::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        Post::PUBLISHED => 'success',
                        Post::HIDDEN => 'danger',
                        Post::PENDING => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('reports_count')->label('گزارش')->numeric()->sortable()->color(fn (int $state) => $state > 0 ? 'danger' : null),
                TextColumn::make('views_count')->label('بازدید')->numeric()->sortable(),
                TextColumn::make('likes_count')->label('لایک')->numeric()->sortable(),
                TextColumn::make('points_awarded')->label('امتیاز داده‌شده')->numeric(),
                TextColumn::make('captured_at')->label('زمان عکس')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('وضعیت')->options(Post::STATUSES),
                Filter::make('reported')->label('گزارش‌شده')->query(fn (Builder $q) => $q->where('reports_count', '>', 0)),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('hide')->label('پنهان')->icon(Heroicon::OutlinedEyeSlash)->color('danger')
                    ->visible(fn (Post $p) => $p->status === Post::PUBLISHED && static::canEdit($p))
                    ->requiresConfirmation()
                    ->action(fn (Post $p) => static::moderate($p, Post::HIDDEN)),
                Action::make('restore')->label('انتشار دوباره')->icon(Heroicon::OutlinedEye)->color('success')
                    ->visible(fn (Post $p) => $p->status === Post::HIDDEN && static::canEdit($p))
                    ->requiresConfirmation()
                    ->modalDescription('گزارش‌های این پست پاک می‌شوند.')
                    ->action(fn (Post $p) => static::moderate($p, Post::PUBLISHED)),
                Action::make('delete')->label('حذف')->icon(Heroicon::OutlinedTrash)->color('danger')
                    ->visible(fn (Post $p) => static::canDelete($p))
                    ->requiresConfirmation()
                    ->action(function (Post $p) {
                        app(AuditLogger::class)->log('post.deleted', $p, meta: ['user_id' => $p->user_id, 'reports' => $p->reports_count]);
                        $p->deleteFiles();
                        $p->delete();
                        Notification::make()->title('حذف شد')->success()->send();
                    }),
            ]);
    }

    private static function moderate(Post $p, string $status): void
    {
        DB::transaction(function () use ($p, $status) {
            if ($status === Post::PUBLISHED) {
                DB::table('post_reports')->where('post_id', $p->id)->delete();
                $p->reports_count = 0;
            }
            $p->forceFill(['status' => $status])->save();
        });
        app(AuditLogger::class)->log('post.'.$status, $p, meta: ['user_id' => $p->user_id]);
        Notification::make()->title('انجام شد')->success()->send();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()->columns(2)->schema([
                ImageEntry::make('image_path')->hiddenLabel()->disk('public')->height(420)->columnSpanFull(),
                TextEntry::make('caption')->label('متن')->placeholder('—')->columnSpanFull(),
                TextEntry::make('user.phone')->label('کاربر'),
                TextEntry::make('status')->label('وضعیت')->formatStateUsing(fn (string $state) => Post::STATUSES[$state] ?? $state),
                TextEntry::make('captured_at')->label('زمان عکس')->dateTime(),
                TextEntry::make('session.public_id')->label('پیاده‌روی')->placeholder('—'),
                TextEntry::make('views_count')->label('بازدید (واجد شرایط)')->formatStateUsing(fn (Post $p) => number_format($p->views_count).' ('.number_format($p->qualified_views).')'),
                TextEntry::make('likes_count')->label('لایک (واجد شرایط)')->formatStateUsing(fn (Post $p) => number_format($p->likes_count).' ('.number_format($p->qualified_likes).')'),
                TextEntry::make('reasons')->label('دلایل گزارش')->columnSpanFull()->placeholder('—')
                    ->state(fn (Post $p) => DB::table('post_reports')->where('post_id', $p->id)->pluck('reason')->countBy()
                        ->map(fn ($n, $r) => (PostReportReasons::LABELS[$r] ?? $r).' × '.$n)->implode('، ')),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosts::route('/'),
            'view' => Pages\ViewPost::route('/{record}'),
        ];
    }
}
