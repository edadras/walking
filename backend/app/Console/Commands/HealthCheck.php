<?php

namespace App\Console\Commands;

use App\Domain\Ops\OpsAlerter;
use App\Models\CashoutRequest;
use App\Models\ClientError;
use App\Models\ReconciliationRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * Early warning before users notice: queue backlog, failing jobs and SMS, crash
 * spikes, payouts waiting too long, and a stale ledger reconciliation. Alerts go
 * through OpsAlerter (panel bell, e-mail, chat webhook) with per-alert quiet periods.
 */
class HealthCheck extends Command
{
    protected $signature = 'ops:health-check';

    protected $description = 'Check queues, jobs, SMS, crashes and payouts and alert on problems';

    public function handle(OpsAlerter $alerts): int
    {
        $t = config('walk.ops.thresholds');
        $problems = 0;
        $raise = function (string $key, string $title, string $body, string $ability = 'ops.view') use ($alerts, &$problems) {
            $problems++;
            $alerts->alert($key, $title, $body, $ability);
            $this->warn("{$title}: {$body}");
        };

        foreach ($t['queue_backlog'] as $queue => $max) {
            try {
                $size = Queue::connection()->size($queue);
            } catch (Throwable $e) {
                $raise('queue-down', 'صف‌ها در دسترس نیستند', $e->getMessage());
                break;
            }
            if ($size > $max) {
                $raise("queue-backlog:{$queue}", "صف {$queue} عقب مانده", "{$size} کار در صف (حد {$max}). Horizon را بررسی کنید.");
            }
        }

        $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subHour())->count();
        if ($failed >= $t['failed_jobs_per_hour']) {
            $raise('failed-jobs', 'کارهای ناموفق زیاد', "{$failed} کار ناموفق در یک ساعت اخیر.");
        }
        $sms = DB::table('failed_jobs')->where('failed_at', '>=', now()->subMinutes(15))->where('payload', 'like', '%SendOtpSms%')->count();
        if ($sms >= $t['sms_failures_per_15_min']) {
            $raise('sms-failing', 'ارسال پیامک OTP ناموفق است', "{$sms} پیامک در ۱۵ دقیقه اخیر ارسال نشد؛ کاربران نمی‌توانند وارد شوند. اعتبار و کلید کاوه‌نگار را بررسی کنید.");
        }

        // Fatal crash reports are aggregated per group, so compare the running total with the previous check.
        $total = (int) ClientError::query()->where('fatal', true)->sum('occurrences');
        $previous = Cache::get('ops:fatal-crash-total');
        Cache::forever('ops:fatal-crash-total', $total);
        if ($previous !== null && $total - (int) $previous >= $t['fatal_crashes_per_5_min']) {
            $raise('crash-spike', 'جهش کرش اپ', ($total - (int) $previous).' کرش از بررسی قبلی. نسخه اخیر را بررسی کنید.');
        }
        $newGroups = ClientError::query()->where('fatal', true)->where('first_seen_at', '>=', now()->subMinutes(5))->count();
        if ($newGroups > 0) {
            $raise('crash-new:'.now()->format('YmdH'), 'کرش جدید در اپ', "{$newGroups} نوع کرش تازه (پنل › خطاهای اپ).");
        }

        $stale = CashoutRequest::query()->where('status', CashoutRequest::PENDING)->where('created_at', '<', now()->subHours($t['cashout_pending_hours']))->count();
        if ($stale > 0) {
            $raise('cashout-stale', 'درخواست برداشت معطل', "{$stale} درخواست بیش از {$t['cashout_pending_hours']} ساعت بررسی نشده.", 'cashout.manage');
        }
        $stuck = CashoutRequest::query()->where('status', CashoutRequest::PROCESSING)->where('sent_at', '<', now()->subHours($t['cashout_processing_hours']))->count();
        if ($stuck > 0) {
            $raise('cashout-stuck', 'انتقال بانکی طولانی شده', "{$stuck} انتقال بیش از {$t['cashout_processing_hours']} ساعت در جریان است.", 'cashout.manage');
        }

        $last = ReconciliationRun::query()->latest('id')->value('finished_at');
        if ($last === null || now()->diffInHours($last, true) > $t['reconcile_max_age_hours']) {
            $raise('reconcile-stale', 'مغایرت‌گیری دفتر کل اجرا نشده', 'آخرین اجرا: '.($last ?? 'هرگز').'. Scheduler را بررسی کنید.', 'wallet.view');
        }

        Cache::forever('ops:health-check:last-run', now()->toIso8601String());
        $this->info($problems === 0 ? 'Healthy' : "Problems={$problems}");

        return self::SUCCESS;
    }
}
