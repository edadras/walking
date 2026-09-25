<?php

namespace App\Console\Commands;

use App\Domain\Settings\Settings;
use App\Domain\Social\PostService;
use App\Models\ActivitySample;
use App\Models\AnalyticsEvent;
use App\Models\ClientError;
use App\Models\OtpCode;
use Illuminate\Console\Command;

/** Enforces the retention windows documented in docs/phase-0/06-security.md §6.5. */
class PruneActivityData extends Command
{
    protected $signature = 'retention:prune';

    protected $description = 'Delete minute samples, analytics events, OTP rows and stale crash reports past retention';

    public function handle(Settings $settings, PostService $posts): int
    {
        $samples = $this->chunkDelete(ActivitySample::query()->where('started_at', '<', now()->subDays($settings->int('activity.samples_retention_days'))));
        $events = $this->chunkDelete(AnalyticsEvent::query()->where('occurred_at', '<', now()->subDays($settings->int('analytics.retention_days'))));
        $otps = $this->chunkDelete(OtpCode::query()->where('created_at', '<', now()->subDay()));
        // Crash groups not seen for 90 days are fixed or gone with old app versions.
        $crashes = $this->chunkDelete(ClientError::query()->where('last_seen_at', '<', now()->subDays(90)));

        // Walk photos whose walk never reached the server.
        $photos = $posts->expirePending();

        $this->info("Pruned samples={$samples} analytics={$events} otp={$otps} client_errors={$crashes} pending_photos={$photos}");

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
