<?php

namespace App\Filament\Sponsor\Resources\Campaigns;

use App\Enums\CampaignStatus;
use App\Filament\Shared\SponsorForms;
use App\Filament\Sponsor\Concerns\RecordsSponsorAction;
use App\Filament\Sponsor\Concerns\SponsorScoped;
use App\Models\Campaign;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class CampaignResource extends Resource
{
    use SponsorScoped;

    protected static ?string $model = Campaign::class;

    protected static string $ability = 'campaigns.manage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'کمپین‌ها';

    protected static ?string $modelLabel = 'کمپین';

    protected static ?string $pluralModelLabel = 'کمپین‌ها';

    /** Only drafts and rejected campaigns can be edited; live ones are paused/resumed. */
    public static function canEdit(Model $record): bool
    {
        return static::canView($record) && in_array($record->status, [CampaignStatus::Draft, CampaignStatus::Rejected], true);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components(SponsorForms::campaign(fn (Builder $q) => $q->where($q->getModel()->getTable().'.sponsor_id', static::sponsorId())));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('کمپین')->searchable(),
                TextColumn::make('reward_points')->label('امتیاز')->numeric(),
                TextColumn::make('rewards_count')->label('بازدید تأییدشده')->numeric(),
                TextColumn::make('budget')->label('مصرف / سقف')->state(fn (Campaign $r) => number_format($r->points_spent).' / '.number_format($r->point_budget)),
                TextColumn::make('ends_at')->label('پایان')->date(),
                TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (CampaignStatus $state) => $state->label())
                    ->color(fn (CampaignStatus $state) => match ($state) {
                        CampaignStatus::Active => 'success', CampaignStatus::PendingApproval => 'warning', CampaignStatus::Rejected => 'danger', default => 'gray'
                    })
                    ->description(fn (Campaign $r) => $r->status === CampaignStatus::Rejected ? $r->rejection_reason : null),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('submit')->label('ارسال برای تأیید')->icon('heroicon-o-paper-airplane')->color('primary')
                    ->visible(fn (Campaign $r) => static::canEdit($r))
                    ->requiresConfirmation()->modalDescription('پس از ارسال، تا تصمیم تیم گام‌یار امکان ویرایش نیست.')
                    ->action(function (Campaign $r) {
                        if ($r->locations()->count() === 0 || $r->ends_at->isPast()) {
                            Notification::make()->title('کمپین باید حداقل یک شعبه و تاریخ پایان آینده داشته باشد.')->danger()->send();

                            return;
                        }
                        RecordsSponsorAction::transition($r, ['status' => CampaignStatus::PendingApproval, 'rejection_reason' => null], 'campaign.submitted');
                    }),
                Action::make('pause')->label('توقف')->icon('heroicon-o-pause')->color('warning')
                    ->visible(fn (Campaign $r) => static::canView($r) && $r->status === CampaignStatus::Active)
                    ->requiresConfirmation()
                    ->action(fn (Campaign $r) => RecordsSponsorAction::transition($r, ['status' => CampaignStatus::Paused], 'campaign.paused')),
                Action::make('resume')->label('ادامه')->icon('heroicon-o-play')->color('success')
                    ->visible(fn (Campaign $r) => static::canView($r) && $r->status === CampaignStatus::Paused && $r->ends_at->isFuture() && $r->sponsor->isApproved())
                    ->action(fn (Campaign $r) => RecordsSponsorAction::transition($r, ['status' => CampaignStatus::Active], 'campaign.resumed')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCampaigns::route('/'),
            'create' => Pages\CreateCampaign::route('/create'),
            'edit' => Pages\EditCampaign::route('/{record}/edit'),
        ];
    }
}
