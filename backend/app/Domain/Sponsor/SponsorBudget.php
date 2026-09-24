<?php

namespace App\Domain\Sponsor;

use App\Models\Campaign;
use Illuminate\Support\Facades\DB;

/**
 * Draws reward points from the campaign AND the sponsor budget with
 * conditional UPDATEs, so concurrent visits can never overspend
 * (docs/phase-0/05-flows.md §5.6). Must run inside the caller's transaction.
 */
class SponsorBudget
{
    public function consume(Campaign $campaign, int $points): bool
    {
        $campaignOk = DB::table('campaigns')
            ->where('id', $campaign->id)
            ->whereRaw('points_spent + ? <= point_budget', [$points])
            ->where(fn ($q) => $q->whereNull('total_limit')->orWhereColumn('rewards_count', '<', 'total_limit'))
            ->update(['points_spent' => DB::raw('points_spent + '.(int) $points), 'rewards_count' => DB::raw('rewards_count + 1')]);
        if ($campaignOk === 0) {
            return false;
        }

        $sponsorOk = DB::table('sponsors')
            ->where('id', $campaign->sponsor_id)
            ->whereRaw('points_spent + ? <= point_budget', [$points])
            ->update(['points_spent' => DB::raw('points_spent + '.(int) $points)]);

        return $sponsorOk === 1;
    }
}
