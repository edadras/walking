<?php

namespace App\Domain\Analytics;

use App\Support\Jalali;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Daily series for the dashboards. Days are Iran calendar days; timestamps are
 * stored in UTC and shifted by the fixed Iran offset (+03:30, no DST since 2022).
 * Every series has an entry for every day (zero-filled).
 */
class Metrics
{
    private const IRAN_OFFSET_MINUTES = 210;

    /** @return list<string> Y-m-d, oldest first */
    public function days(int $count): array
    {
        $today = CarbonImmutable::now('Asia/Tehran')->startOfDay();

        return array_map(fn ($i) => $today->subDays($count - 1 - $i)->toDateString(), range(0, $count - 1));
    }

    /** @return list<string> Jalali "d/m" labels */
    public function labels(int $count): array
    {
        return array_map(function (string $d) {
            [, $m, $day] = Jalali::of(CarbonImmutable::parse($d));

            return "{$day}/{$m}";
        }, $this->days($count));
    }

    /** @return array<string, int> */
    public function activeUsers(int $days): array
    {
        return $this->fill($days, DB::table('daily_activities')->where('raw_steps', '>', 0)
            ->where('local_date', '>=', $this->days($days)[0])
            ->selectRaw('local_date d, COUNT(DISTINCT user_id) v')->groupBy('local_date'));
    }

    /** @return array<string, int> */
    public function newUsers(int $days): array
    {
        return $this->fill($days, $this->byDay(DB::table('users'), 'created_at', $days)->selectRaw('COUNT(*) v'));
    }

    /** @return array{raw: array<string, int>, verified: array<string, int>} */
    public function steps(int $days): array
    {
        $rows = DB::table('daily_activities')->where('local_date', '>=', $this->days($days)[0])
            ->selectRaw('local_date d, SUM(raw_steps) r, SUM(verified_steps) v')->groupBy('local_date')->get()->keyBy(fn ($r) => (string) $r->d);

        return [
            'raw' => $this->zero($days, fn ($d) => (int) ($rows[$d]->r ?? 0)),
            'verified' => $this->zero($days, fn ($d) => (int) ($rows[$d]->v ?? 0)),
        ];
    }

    /** @return array{issued: array<string, int>, spent: array<string, int>} */
    public function points(int $days): array
    {
        $base = fn () => $this->byDay(DB::table('point_transactions'), 'created_at', $days)->where('status', '!=', 'reversed');

        return [
            'issued' => $this->fill($days, $base()->where('amount', '>', 0)->where('type', '!=', 'refund')->selectRaw('SUM(amount) v')),
            'spent' => $this->fill($days, $base()->where('amount', '<', 0)->selectRaw('-SUM(amount) v')),
        ];
    }

    /** @return array<string, array<string, int>> status => series */
    public function sessionsByStatus(int $days): array
    {
        $rows = DB::table('walking_sessions')->where('local_date', '>=', $this->days($days)[0])
            ->selectRaw('local_date d, status s, COUNT(*) v')->groupBy('local_date', 'status')->get();
        $out = [];
        foreach (['verified', 'partially_verified', 'review', 'rejected'] as $status) {
            $byDay = $rows->where('s', $status)->keyBy(fn ($r) => (string) $r->d);
            $out[$status] = $this->zero($days, fn ($d) => (int) ($byDay[$d]->v ?? 0));
        }

        return $out;
    }

    /** @return array{orders: array<string, int>, points: array<string, int>} */
    public function orders(int $days): array
    {
        $q = fn () => $this->byDay(DB::table('orders'), 'placed_at', $days)->whereNotIn('status', ['cancelled', 'refunded', 'awaiting_payment']);

        return ['orders' => $this->fill($days, $q()->selectRaw('COUNT(*) v')), 'points' => $this->fill($days, $q()->selectRaw('SUM(total_points) v'))];
    }

    /** @return array{rewarded: array<string, int>, rejected: array<string, int>} */
    public function visits(int $days, ?int $sponsorId = null): array
    {
        $q = fn (string $status) => $this->byDay(DB::table('visits'), 'visits.created_at', $days)->where('visits.status', $status)
            ->when($sponsorId, fn ($q) => $q->join('campaigns', 'campaigns.id', '=', 'visits.campaign_id')->where('campaigns.sponsor_id', $sponsorId))
            ->selectRaw('COUNT(*) v');

        return ['rewarded' => $this->fill($days, $q('rewarded')), 'rejected' => $this->fill($days, $q('rejected'))];
    }

    /** @return array{claimed: array<string, int>, redeemed: array<string, int>} */
    public function coupons(int $days, ?int $sponsorId = null): array
    {
        $q = fn (string $column) => $this->byDay(DB::table('user_coupons'), 'user_coupons.'.$column, $days)
            ->when($sponsorId, fn ($q) => $q->join('coupons', 'coupons.id', '=', 'user_coupons.coupon_id')->where('coupons.sponsor_id', $sponsorId))
            ->selectRaw('COUNT(*) v');

        return ['claimed' => $this->fill($days, $q('claimed_at')), 'redeemed' => $this->fill($days, $q('used_at'))];
    }

    /** @return array{impressions: array<string, int>, clicks: array<string, int>} */
    public function ads(int $days, ?int $sponsorId = null): array
    {
        $q = fn (string $type) => DB::table('ad_events')->where('ad_events.type', $type)->where('local_date', '>=', $this->days($days)[0])
            ->when($sponsorId, fn ($q) => $q->join('ad_campaigns', 'ad_campaigns.id', '=', 'ad_events.ad_campaign_id')->where('ad_campaigns.sponsor_id', $sponsorId))
            ->selectRaw('local_date d, COUNT(*) v')->groupBy('local_date');

        return ['impressions' => $this->fill($days, $q('impression')), 'clicks' => $this->fill($days, $q('click'))];
    }

    /** @return list<object{rule_key: string, events: int, users: int, avg_score: float}> */
    public function topFraudRules(int $days, int $limit = 10): array
    {
        return DB::table('fraud_events')->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('rule_key, COUNT(*) events, COUNT(DISTINCT user_id) users, ROUND(AVG(score), 1) avg_score')
            ->groupBy('rule_key')->orderByDesc('events')->limit($limit)->get()->all();
    }

    private function byDay(Builder $q, string $column, int $days): Builder
    {
        $from = CarbonImmutable::parse($this->days($days)[0], 'Asia/Tehran')->utc();
        $expr = 'DATE(DATE_ADD('.$column.', INTERVAL '.self::IRAN_OFFSET_MINUTES.' MINUTE))';

        return $q->where($column, '>=', $from)->selectRaw($expr.' d')->groupByRaw($expr);
    }

    /** @return array<string, int> */
    private function fill(int $days, Builder $q): array
    {
        $rows = $q->get()->keyBy(fn ($r) => (string) $r->d);

        return $this->zero($days, fn ($d) => (int) ($rows[$d]->v ?? 0));
    }

    /** @return array<string, int> */
    private function zero(int $days, callable $value): array
    {
        $out = [];
        foreach ($this->days($days) as $d) {
            $out[$d] = $value($d);
        }

        return $out;
    }
}
