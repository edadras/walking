<?php

namespace App\Filament\Org\Resources\Members;

use App\Domain\Audit\AuditLogger;
use App\Models\OrganizationMember;
use App\Models\OrganizationUser;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Who joined with the company code. Deliberately no per-person activity data here. */
class MemberResource extends Resource
{
    protected static ?string $model = OrganizationMember::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'عضو';

    protected static ?string $pluralModelLabel = 'اعضا';

    protected static ?string $slug = 'members';

    private static function user(): ?OrganizationUser
    {
        $u = auth('org')->user();

        return $u instanceof OrganizationUser ? $u : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('organization_id', self::user()?->organization_id ?? 0)->with('user');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $departments = fn () => collect(self::user()?->organization->departments ?? [])->mapWithKeys(fn ($d) => [$d => $d])->all();

        return $table
            ->defaultSort('joined_at', 'desc')
            ->columns([
                TextColumn::make('user.display_name')->label('نام')->placeholder('بدون نام')->searchable(),
                TextColumn::make('department')->label('واحد')->placeholder('—'),
                TextColumn::make('joined_at')->label('عضویت')->date(),
            ])
            ->filters([SelectFilter::make('department')->label('واحد')->options($departments)])
            ->recordActions([
                Action::make('department')->label('تغییر واحد')->icon(Heroicon::OutlinedBuildingOffice)
                    ->visible(fn () => self::user()?->isAdmin() && $departments() !== [])
                    ->schema([Select::make('department')->label('واحد')->options($departments)->required()])
                    ->action(fn (OrganizationMember $record, array $data) => $record->update(['department' => $data['department']])),
                Action::make('remove')->label('حذف از سازمان')->icon(Heroicon::OutlinedUserMinus)->color('danger')
                    ->visible(fn () => self::user()?->isAdmin())
                    ->requiresConfirmation()->modalDescription('عضو از رتبه‌بندی و چالش‌های سازمان خارج می‌شود و یک صندلی آزاد می‌شود.')
                    ->action(function (OrganizationMember $record) {
                        $record->delete();
                        app(AuditLogger::class)->log('organization.member_removed', $record->organization, meta: ['user' => $record->user?->public_id], actor: self::user());
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListMembers::route('/')];
    }
}
