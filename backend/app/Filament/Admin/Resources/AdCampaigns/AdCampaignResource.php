<?php

namespace App\Filament\Admin\Resources\AdCampaigns;

use App\Domain\Audit\AuditLogger;
use App\Enums\CampaignStatus;
use App\Filament\Admin\Concerns\RequiresAbility;
use App\Filament\Shared\AdForms;
use App\Models\AdCampaign;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class AdCampaignResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = AdCampaign::class;

    protected static ?string $viewAbility = 'ads.manage';

    protected static ?string $manageAbility = 'ads.manage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'تبلیغات';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'کمپین تبلیغاتی';

    protected static ?string $pluralModelLabel = 'کمپین‌های تبلیغاتی';

    public static function getNavigationBadge(): ?string
    {
        $n = AdCampaign::query()->where('status', CampaignStatus::PendingApproval)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            ...AdForms::campaign(withSponsor: true, withPriority: true),
            Select::make('status')->label('وضعیت')->required()->default(CampaignStatus::Draft->value)
                ->options(collect(CampaignStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('کمپین')->searchable(),
                TextColumn::make('sponsor.name')->label('تبلیغ‌دهنده')->placeholder('پلتفرم'),
                TextColumn::make('impressions_count')->label('نمایش')->numeric(),
                TextColumn::make('clicks_count')->label('کلیک')->numeric(),
                TextColumn::make('ctr')->label('CTR')->state(fn (AdCampaign $r) => $r->ctr().'٪'),
                TextColumn::make('rewards_count')->label('تماشای جایزه‌دار')->numeric(),
                TextColumn::make('ends_at')->label('پایان')->date(),
                TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (CampaignStatus $state) => $state->label())
                    ->color(fn (CampaignStatus $state) => match ($state) {
                        CampaignStatus::Active => 'success',
                        CampaignStatus::PendingApproval => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([SelectFilter::make('status')->label('وضعیت')->options(collect(CampaignStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]))])
            ->recordActions([
                EditAction::make(),
                Action::make('approve')->label('تأیید')->color('success')->icon('heroicon-o-check')
                    ->visible(fn (AdCampaign $r) => static::allows('ads.manage') && $r->status === CampaignStatus::PendingApproval)
                    ->requiresConfirmation()
                    ->action(fn (AdCampaign $r) => static::transition($r, ['status' => CampaignStatus::Active, 'approved_by' => auth('admin')->id(), 'approved_at' => now(), 'rejection_reason' => null], 'ad_campaign.approved')),
                Action::make('reject')->label('رد')->color('danger')->icon('heroicon-o-x-mark')
                    ->visible(fn (AdCampaign $r) => static::allows('ads.manage') && $r->status === CampaignStatus::PendingApproval)
                    ->schema([Textarea::make('reason')->label('دلیل')->required()->maxLength(250)])
                    ->action(fn (AdCampaign $r, array $data) => static::transition($r, ['status' => CampaignStatus::Rejected, 'rejection_reason' => $data['reason']], 'ad_campaign.rejected')),
            ]);
    }

    private static function transition(AdCampaign $r, array $changes, string $action): void
    {
        $old = array_intersect_key($r->getAttributes(), $changes);
        $r->forceFill($changes)->save();
        app(AuditLogger::class)->log($action, $r, $old, array_map(fn ($v) => $v instanceof BackedEnum ? $v->value : $v, $changes));
    }

    public static function getRelations(): array
    {
        return [RelationManagers\AdsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdCampaigns::route('/'),
            'create' => Pages\CreateAdCampaign::route('/create'),
            'edit' => Pages\EditAdCampaign::route('/{record}/edit'),
        ];
    }
}
