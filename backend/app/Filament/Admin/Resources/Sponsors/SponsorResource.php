<?php

namespace App\Filament\Admin\Resources\Sponsors;

use App\Domain\Sponsor\SponsorModeration;
use App\Enums\SponsorStatus;
use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\Sponsor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class SponsorResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = Sponsor::class;

    protected static ?string $viewAbility = 'sponsors.manage';

    protected static ?string $manageAbility = 'sponsors.manage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'اسپانسرها';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'اسپانسر';

    protected static ?string $pluralModelLabel = 'اسپانسرها';

    public static function getNavigationBadge(): ?string
    {
        $n = Sponsor::query()->where('status', SponsorStatus::Pending)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('نام تجاری')->required()->maxLength(120),
            TextInput::make('legal_name')->label('نام حقوقی')->maxLength(190),
            TextInput::make('national_id')->label('شناسه ملی')->maxLength(20),
            TextInput::make('contact_phone')->label('تلفن')->tel()->maxLength(20),
            TextInput::make('contact_email')->label('ایمیل')->email(),
            TextInput::make('website')->label('وب‌سایت')->url(),
            Textarea::make('description')->label('معرفی')->rows(3)->columnSpanFull(),
            FileUpload::make('logo_path')->label('لوگو')->image()->disk('public')->directory('sponsors')->maxSize(512)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('نام')->searchable(),
                TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (SponsorStatus $state) => $state->label())
                    ->color(fn (SponsorStatus $state) => match ($state) {
                        SponsorStatus::Approved => 'success', SponsorStatus::Pending => 'warning', default => 'danger'
                    }),
                TextColumn::make('point_budget')->label('بودجه (امتیاز)')->numeric(),
                TextColumn::make('points_spent')->label('مصرف‌شده')->numeric(),
                TextColumn::make('remaining')->label('باقی‌مانده')->state(fn (Sponsor $r) => $r->budgetRemaining())->numeric(),
                TextColumn::make('campaigns_count')->label('کمپین')->counts('campaigns'),
                TextColumn::make('created_at')->label('ثبت')->since(),
            ])
            ->filters([SelectFilter::make('status')->label('وضعیت')->options(collect(SponsorStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]))])
            ->recordActions([EditAction::make(), ...static::statusActions()]);
    }

    /** @return list<Action> */
    public static function statusActions(): array
    {
        $mod = fn () => app(SponsorModeration::class);
        $admin = fn () => auth('admin')->user();

        return [
            Action::make('approve')->label('تأیید')->color('success')->icon('heroicon-o-check')
                ->visible(fn (Sponsor $r) => static::allows('sponsors.manage') && $r->status !== SponsorStatus::Approved)
                ->requiresConfirmation()
                ->action(fn (Sponsor $r) => $mod()->approveSponsor($r, $admin())),
            Action::make('reject')->label('رد')->color('danger')
                ->visible(fn (Sponsor $r) => static::allows('sponsors.manage') && $r->status === SponsorStatus::Pending)
                ->schema([Textarea::make('reason')->label('دلیل')->required()->maxLength(250)])
                ->action(fn (Sponsor $r, array $data) => $mod()->rejectSponsor($r, $data['reason'], $admin())),
            Action::make('suspend')->label('تعلیق')->color('danger')->icon('heroicon-o-pause')
                ->visible(fn (Sponsor $r) => static::allows('sponsors.manage') && $r->status === SponsorStatus::Approved)
                ->schema([Textarea::make('reason')->label('دلیل')->required()->maxLength(250)])
                ->action(fn (Sponsor $r, array $data) => $mod()->suspendSponsor($r, $data['reason'], $admin())),
            Action::make('top_up')->label('افزایش بودجه')->color('warning')->icon('heroicon-o-banknotes')
                ->visible(fn () => static::allows('sponsors.manage'))
                ->schema([
                    TextInput::make('points')->label('امتیاز')->numeric()->integer()->minValue(1)->maxValue(100_000_000)->required(),
                    TextInput::make('note')->label('شماره فاکتور / توضیح')->required()->maxLength(200),
                ])
                ->action(function (Sponsor $r, array $data) use ($mod, $admin) {
                    $mod()->topUp($r, (int) $data['points'], $data['note'], $admin());
                    Notification::make()->title('بودجه افزایش یافت.')->success()->send();
                }),
        ];
    }

    public static function getRelations(): array
    {
        return [RelationManagers\UsersRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSponsors::route('/'),
            'create' => Pages\CreateSponsor::route('/create'),
            'edit' => Pages\EditSponsor::route('/{record}/edit'),
        ];
    }
}
