<?php

namespace App\Filament\Sponsor\Pages;

use App\Domain\Wallet\ConversionRate;
use App\Enums\UserCouponStatus;
use App\Enums\VisitStatus;
use App\Models\Campaign;
use App\Models\SponsorUser;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * "What did my budget buy?" per campaign: verified visits, unique and repeat
 * visitors, coupons issued and actually used in store, and the cost of each.
 */
class Effectiveness extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = 'گزارش';

    protected static ?string $navigationLabel = 'اثربخشی کمپین‌ها';

    protected static ?string $title = 'اثربخشی کمپین‌ها';

    protected static ?string $slug = 'effectiveness';

    public static function canAccess(): bool
    {
        $user = auth('sponsor')->user();

        return $user instanceof SponsorUser && $user->hasAbility('analytics.view') && $user->sponsor->isApproved();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Text::make('فقط بازدیدهای تأییدشده (حضور در محدوده شعبه + QR) و کوپن‌هایی که صندوق‌دار ثبت کرده شمرده می‌شوند.'),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        $sponsorId = auth('sponsor')->user()->sponsor_id;
        $verified = [VisitStatus::Verified->value, VisitStatus::Rewarded->value];
        $rate = app(ConversionRate::class)->current();

        return $table
            ->query(fn (): Builder => Campaign::query()->where('sponsor_id', $sponsorId)
                ->addSelect(['visits_verified' => DB::table('visits')->selectRaw('COUNT(*)')->whereColumn('campaign_id', 'campaigns.id')->whereIn('status', $verified)])
                ->addSelect(['visitors' => DB::table('visits')->selectRaw('COUNT(DISTINCT user_id)')->whereColumn('campaign_id', 'campaigns.id')->whereIn('status', $verified)])
                ->addSelect(['repeat_visitors' => DB::table(DB::raw('(SELECT campaign_id, user_id FROM visits WHERE status IN (\'verified\',\'rewarded\') GROUP BY campaign_id, user_id HAVING COUNT(*) > 1) r'))
                    ->selectRaw('COUNT(*)')->whereColumn('r.campaign_id', 'campaigns.id')])
                // Coupons count only when issued by a visit of this very campaign.
                ->addSelect(['coupons_issued' => $this->couponsFromVisits()])
                ->addSelect(['coupons_used' => $this->couponsFromVisits()->where('user_coupons.status', UserCouponStatus::Used->value)]))
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('کمپین')->searchable()->description(fn (Campaign $c) => $c->status->label()),
                TextColumn::make('points_spent')->label('بودجه مصرف‌شده')->numeric()->sortable()
                    ->description(fn (Campaign $c) => number_format($c->points_spent * $rate).' ریال'),
                TextColumn::make('visits_verified')->label('بازدید تأییدشده')->numeric()->sortable(),
                TextColumn::make('visitors')->label('بازدیدکننده یکتا')->numeric()
                    ->description(fn (Campaign $c) => $c->visitors ? 'بازگشتی: '.number_format((int) $c->repeat_visitors) : null),
                TextColumn::make('cost_per_visit')->label('هزینه هر بازدید')
                    ->state(fn (Campaign $c) => $c->visits_verified ? (int) round($c->points_spent / $c->visits_verified) : null)
                    ->formatStateUsing(fn (int $state) => number_format($state).' امتیاز')->placeholder('—')
                    ->description(fn (Campaign $c) => $c->visits_verified ? number_format((int) round($c->points_spent / $c->visits_verified * $rate)).' ریال' : null),
                TextColumn::make('coupons_issued')->label('کوپن صادرشده')->numeric(),
                TextColumn::make('coupons_used')->label('کوپن استفاده‌شده')->numeric()
                    ->description(fn (Campaign $c) => $c->coupons_issued ? round($c->coupons_used * 100 / $c->coupons_issued).'٪ نرخ استفاده' : null),
            ])
            ->emptyStateHeading('هنوز کمپینی ندارید');
    }

    private function couponsFromVisits(): QueryBuilder
    {
        return DB::table('user_coupons')->selectRaw('COUNT(*)')
            ->join('visits', fn ($j) => $j->on('visits.id', '=', 'user_coupons.source_id')->where('user_coupons.source_type', '=', 'visit'))
            ->whereColumn('visits.campaign_id', 'campaigns.id');
    }
}
