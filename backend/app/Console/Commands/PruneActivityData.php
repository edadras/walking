<?php

namespace App\Console\Commands;

use App\Domain\Settings\Settings;
use App\Models\ActivitySample;
use App\Models\AnalyticsEvent;
use App\Models\OtpCode;
use Illuminate\Console\Command;

/** Enforces the retention windows documented in docs/phase-0/06-security.md §6.5. */
class PruneActivityData extends Command
{
    protected $signature = 'retention:prune';

    protected $description = 'Delete minute samples, analytics events and OTP rows past retention';

    public function handle(Settings $settings): int
    {
        $samples = $this->chunkDelete(ActivitySample::query()->where('started_at', '<', now()->subDays($settings->int('activity.samples_retention_days'))));
        $events = $this->chunkDelete(AnalyticsEvent::query()->where('occurred_at', '<', now()->subDays($settings->int('analytics.retention_days'))));
        $otps = $this->chunkDelete(OtpCode::query()->where('created_at', '<', now()->subDay()));

        $this->info("Pruned samples={$samples} analytics={$events} otp={$otps}");

        return self::SUCCESS;
    }

    /** Deletes in small batches so the job never holds long locks on hot tables. */
    private function chunkDelete($query): int
    {
        $total = 0;
        do {
            $deleted = (clone $query)->limit(5000)->delete();
            $total += $deleted;
        } while ($deleted > 0);

        return $total;
    }
}
