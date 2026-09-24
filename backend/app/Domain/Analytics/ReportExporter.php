<?php

namespace App\Domain\Analytics;

use App\Support\Phone;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * CSV reports for admins. Rows are streamed (cursor) so large ranges don't
 * load into memory. Phone numbers are masked; users are referenced by their
 * public id.
 */
class ReportExporter
{
    public const REPORTS = [
        'daily' => 'خلاصه روزانه',
        'ledger' => 'دفتر امتیاز',
        'orders' => 'سفارش‌ها',
        'visits' => 'بازدید مکان‌ها',
        'fraud' => 'سیگنال‌های تقلب',
        'users' => 'کاربران',
    ];

    public function __construct(private readonly Metrics $metrics) {}

    /** @return list<string> */
    public function header(string $report): array
    {
        return match ($report) {
            'daily' => ['تاریخ', 'کاربر فعال', 'ثبت‌نام', 'قدم ثبت‌شده', 'قدم تأییدشده', 'امتیاز صادرشده', 'امتیاز مصرف‌شده', 'سفارش'],
            'ledger' => ['زمان (UTC)', 'کاربر', 'نوع', 'مقدار', 'وضعیت', 'ارزش ریالی', 'توضیح'],
            'orders' => ['زمان (UTC)', 'شماره', 'کاربر', 'وضعیت', 'امتیاز', 'اقلام'],
            'visits' => ['زمان (UTC)', 'اسپانسر', 'کمپین', 'شعبه', 'وضعیت', 'حضور (ثانیه)', 'امتیاز', 'دلیل'],
            'fraud' => ['زمان (UTC)', 'کاربر', 'Rule', 'امتیاز ریسک', 'شدت', 'نوع موضوع'],
            'users' => ['شناسه', 'تلفن', 'ثبت‌نام (UTC)', 'وضعیت', 'سطح', 'XP', 'آخرین فعالیت (UTC)'],
            default => throw new InvalidArgumentException("Unknown report {$report}"),
        };
    }

    /** @return Generator<int, list<string|int|null>> */
    public function rows(string $report, CarbonImmutable $from, CarbonImmutable $to): Generator
    {
        $range = [$from->utc(), $to->utc()];

        switch ($report) {
            case 'daily':
                $days = (int) max(1, min(366, $from->diffInDays(CarbonImmutable::now('Asia/Tehran')) + 1));
                $active = $this->metrics->activeUsers($days);
                $new = $this->metrics->newUsers($days);
                $steps = $this->metrics->steps($days);
                $points = $this->metrics->points($days);
                $orders = $this->metrics->orders($days)['orders'];
                foreach ($this->metrics->days($days) as $d) {
                    if ($d < $from->toDateString() || $d > $to->toDateString()) {
                        continue;
                    }
                    yield [$d, $active[$d], $new[$d], $steps['raw'][$d], $steps['verified'][$d], $points['issued'][$d], $points['spent'][$d], $orders[$d]];
                }
                break;
            case 'ledger':
                foreach (DB::table('point_transactions as t')->join('users as u', 'u.id', '=', 't.user_id')->whereBetween('t.created_at', $range)->orderBy('t.id')
                    ->select('t.created_at', 'u.public_id', 't.type', 't.amount', 't.status', DB::raw('CAST(t.amount AS SIGNED) * CAST(t.rial_rate AS SIGNED) as rial_value'), 't.description')->cursor() as $r) {
                    yield [$r->created_at, $r->public_id, $r->type, $r->amount, $r->status, $r->rial_value, $r->description];
                }
                break;
            case 'orders':
                foreach (DB::table('orders as o')->join('users as u', 'u.id', '=', 'o.user_id')->whereBetween('o.placed_at', $range)->orderBy('o.id')
                    ->select('o.id', 'o.placed_at', 'o.public_id', 'u.public_id as user', 'o.status', 'o.total_points')->cursor() as $r) {
                    $items = DB::table('order_items')->where('order_id', $r->id)->get()->map(fn ($i) => $i->name.' ×'.$i->quantity)->implode('، ');
                    yield [$r->placed_at, '#'.strtoupper(substr($r->public_id, -6)), $r->user, $r->status, $r->total_points, $items];
                }
                break;
            case 'visits':
                foreach (DB::table('visits as v')->join('campaigns as c', 'c.id', '=', 'v.campaign_id')->join('sponsors as s', 's.id', '=', 'c.sponsor_id')->join('locations as l', 'l.id', '=', 'v.location_id')
                    ->whereBetween('v.created_at', $range)->orderBy('v.id')
                    ->select('v.created_at', 's.name as sponsor', 'c.name as campaign', 'l.name as branch', 'v.status', 'v.stay_seconds', 'v.points_awarded', 'v.rejection_reason')->cursor() as $r) {
                    yield [$r->created_at, $r->sponsor, $r->campaign, $r->branch, $r->status, $r->stay_seconds, $r->points_awarded, $r->rejection_reason];
                }
                break;
            case 'fraud':
                foreach (DB::table('fraud_events as f')->join('users as u', 'u.id', '=', 'f.user_id')->whereBetween('f.created_at', $range)->orderBy('f.id')
                    ->select('f.created_at', 'u.public_id', 'f.rule_key', 'f.score', 'f.severity', 'f.subject_type')->cursor() as $r) {
                    yield [$r->created_at, $r->public_id, $r->rule_key, $r->score, $r->severity, $r->subject_type];
                }
                break;
            case 'users':
                foreach (DB::table('users')->whereBetween('created_at', $range)->orderBy('id')
                    ->select('public_id', 'phone', 'created_at', 'status', 'level', 'xp', 'last_active_at')->cursor() as $r) {
                    yield [$r->public_id, Phone::mask((string) $r->phone), $r->created_at, $r->status, $r->level, $r->xp, $r->last_active_at];
                }
                break;
            default:
                throw new InvalidArgumentException("Unknown report {$report}");
        }
    }

    /** Writes the CSV (UTF-8 BOM so Excel shows Persian correctly) to the output stream. */
    public function stream(string $report, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $this->header($report), escape: '');
        foreach ($this->rows($report, $from, $to) as $row) {
            fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v, $row), escape: '');
        }
        fclose($out);
    }
}
