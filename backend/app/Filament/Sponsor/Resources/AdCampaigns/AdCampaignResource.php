<?php

namespace App\Filament\Sponsor\Resources\AdCampaigns;

use App\Enums\CampaignStatus;
use App\Filament\Shared\AdForms;
use App\Filament\Sponsor\Concerns\RecordsSponsorAction;
use App\Filament\Sponsor\Concerns\SponsorScoped;
use App\Models\AdCampaign;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AdCampaignResource extends Resource
{
    use SponsorScoped;

    protected static ?string $model = AdCampaign::class;

    protected static string $ability = 'ads.manage';

    protected static ?string $slug = 'ads';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'کمپین‌ها';

    protected static ?string $modelLabel = 'کمپین تبلیغاتی';

    protected static ?string $pluralModelLabel = 'تبلیغات';

    public static function canEdit(Model $record): bool
    {
        return static::canView($record) && in_array($record->status, [CampaignStatus::Draft, CampaignStatus::Rejected], true);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components(AdForms::campaign());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('کمپین'),
                TextColumn::make('impressions_count')->label('نمایش')->numeric(),
                TextColumn::make('clicks_count')->label('کلیک')->numeric(),
                TextColumn::make('ctr')->label('CTR')->state(fn (AdCampaign $r) => $r->ctr().'٪'),
                TextColumn::make('ends_at')->label('پایان')->date(),
                TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (CampaignStatus $state) => $state->label())
                    ->description(fn (AdCampaign $r) => $r->status === CampaignStatus::Rejected ? $r->rejection_reason : null),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('submit')->label('ارسال برای تأیید')->icon('heroicon-o-paper-airplane')
                    ->visible(fn (AdCampaign $r) => static::canEdit($r))
                    ->requiresConfirmation()
                    ->action(function (AdCampaign $r) {
                        if ($r->ads()->where('is_active', true)->doesntExist()) {
                            Notification::make()->title('ابتدا حداقل یک آگهی فعال اضافه کنید.')->danger()->send();

                            return;
                        }
                        RecordsSponsorAction::transition($r, ['status' => CampaignStatus::PendingApproval, 'rejection_reason' => null], 'ad_campaign.submitted');
                    }),
                Action::make('pause')->label('توقف')->icon('heroicon-o-pause')->color('warning')
                    ->visible(fn (AdCampaign $r) => static::canView($r) && $r->status === CampaignStatus::Active)
                    ->action(fn (AdCampaign $r) => RecordsSponsorAction::transition($r, ['status' => CampaignStatus::Paused], 'ad_campaign.paused')),
                Action::make('resume')->label('ادامه')->icon('heroicon-o-play')->color('success')
                    ->visible(fn (AdCampaign $r) => static::canView($r) && $r->status === CampaignStatus::Paused && $r->ends_at->isFuture())
                    ->action(fn (AdCampaign $r) => RecordsSponsorAction::transition($r, ['status' => CampaignStatus::Active], 'ad_campaign.resumed')),
            ]);
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
