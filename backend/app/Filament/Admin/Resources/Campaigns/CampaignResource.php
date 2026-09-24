<?php

namespace App\Filament\Admin\Resources\Campaigns;

use App\Enums\CampaignStatus;
use App\Enums\VerificationMethod;
use App\Filament\Admin\Concerns\ModerationActions;
use App\Filament\Admin\Concerns\RequiresAbility;
use App\Filament\Shared\SponsorForms;
use App\Models\Campaign;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CampaignResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = Campaign::class;

    protected static ?string $viewAbility = 'sponsors.manage';

    protected static ?string $manageAbility = 'sponsors.manage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'اسپانسرها';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'کمپین';

    protected static ?string $pluralModelLabel = 'کمپین‌ها';

    public static function getNavigationBadge(): ?string
    {
        $n = Campaign::query()->where('status', CampaignStatus::PendingApproval)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        // Admin may pick any sponsor's branches/coupons, but they must belong to the campaign's sponsor.
        return $schema->columns(2)->components(SponsorForms::campaign(
            fn (Builder $q, ?Campaign $record) => $q->where($q->getModel()->getTable().'.sponsor_id', $record?->sponsor_id ?? 0),
        ));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('کمپین')->searchable(),
                TextColumn::make('sponsor.name')->label('اسپانسر'),
                TextColumn::make('verification_method')->label('روش')->badge()->formatStateUsing(fn (VerificationMethod $state) => $state === VerificationMethod::GeofenceQr ? 'QR' : 'حضور'),
                TextColumn::make('reward_points')->label('امتیاز')->numeric(),
                TextColumn::make('rewards_count')->label('پاداش‌ها')->numeric(),
                TextColumn::make('budget')->label('مصرف / بودجه')->state(fn (Campaign $r) => number_format($r->points_spent).' / '.number_format($r->point_budget)),
                TextColumn::make('ends_at')->label('پایان')->date(),
                TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (CampaignStatus $state) => $state->label())
                    ->color(fn (CampaignStatus $state) => match ($state) {
                        CampaignStatus::Active => 'success', CampaignStatus::PendingApproval => 'warning', default => 'gray'
                    }),
            ])
            ->filters([SelectFilter::make('status')->label('وضعیت')->options(collect(CampaignStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]))])
            ->recordActions([EditAction::make(), ...ModerationActions::make(fn (Campaign $r) => $r->status === CampaignStatus::PendingApproval)]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCampaigns::route('/'),
            'edit' => Pages\EditCampaign::route('/{record}/edit'),
        ];
    }
}
