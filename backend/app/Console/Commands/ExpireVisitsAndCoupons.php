<?php

namespace App\Console\Commands;

use App\Domain\Sponsor\CouponService;
use App\Domain\Sponsor\VisitService;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Illuminate\Console\Command;

class ExpireVisitsAndCoupons extends Command
{
    protected $signature = 'sponsors:housekeeping';

    protected $description = 'Expire abandoned visits (erasing their last position), passed coupons and finished campaigns';

    public function handle(VisitService $visits, CouponService $coupons): int
    {
        $v = $visits->expireStale();
        $c = $coupons->expire();
        $e = Campaign::query()->whereIn('status', [CampaignStatus::Active, CampaignStatus::Paused])->where('ends_at', '<=', now())->update(['status' => CampaignStatus::Ended]);
        $this->info("visits: {$v}, coupons: {$c}, campaigns: {$e}");

        return self::SUCCESS;
    }
}
