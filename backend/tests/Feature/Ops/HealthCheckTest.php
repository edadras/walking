<?php

namespace Tests\Feature\Ops;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\CashoutRequest;
use App\Models\ClientError;
use App\Models\ReconciliationRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        config(['walk.ops.alert_webhook' => 'https://chat.example/hook']);
        ReconciliationRun::query()->create(['status' => 'ok', 'wallets_checked' => 0, 'cashouts_checked' => 0, 'issue_count' => 0, 'issues' => [], 'totals' => [],
            'started_at' => now(), 'finished_at' => now()]);
    }

    private function alerts(): array
    {
        return Http::recorded()->map(fn ($pair) => $pair[0]['text'])->all();
    }

    public function test_quiet_system_is_healthy(): void
    {
        $this->artisan('ops:health-check')->expectsOutput('Healthy');
        Http::assertNothingSent();
    }

    public function test_failing_sms_and_stale_payouts_alert_the_right_people(): void
    {
        $ops = Admin::factory()->role(AdminRole::Operations)->create();
        $finance = Admin::factory()->role(AdminRole::Finance)->create();
        foreach (range(1, 3) as $i) {
            DB::table('failed_jobs')->insert(['uuid' => "u{$i}", 'connection' => 'redis', 'queue' => 'critical',
                'payload' => json_encode(['displayName' => 'App\\Jobs\\SendOtpSms']), 'exception' => 'Kavenegar 418', 'failed_at' => now()]);
        }
        $u = User::factory()->create();
        $acc = DB::table('bank_accounts')->insertGetId(['public_id' => (string) str()->ulid(), 'user_id' => $u->id, 'iban' => '', 'iban_hash' => 'h', 'iban_last4' => '0000',
            'bank_name' => 'ملت', 'holder_name' => 'x', 'status' => 'verified', 'created_at' => now(), 'updated_at' => now()]);
        CashoutRequest::query()->create(['user_id' => $u->id, 'bank_account_id' => $acc, 'points' => 1, 'rial_per_point' => 1, 'amount_rial' => 1,
            'status' => 'pending', 'idempotency_key' => 'k'])->forceFill(['created_at' => now()->subHours(50)])->save();

        $this->artisan('ops:health-check')->expectsOutputToContain('Problems=2');
        $texts = implode("\n", $this->alerts());
        $this->assertStringContainsString('پیامک', $texts);
        $this->assertStringContainsString('معطل', $texts);
        $this->assertSame(1, $ops->notifications()->count(), 'SMS alert → operations');
        $this->assertSame(1, $finance->notifications()->count(), 'payout alert → finance');

        // Quiet period: the same problems don't page again five minutes later.
        $this->artisan('ops:health-check');
        Http::assertSentCount(2);
    }

    public function test_crash_spike_and_stale_reconciliation(): void
    {
        $crash = ClientError::query()->create(['fingerprint' => str_repeat('a', 64), 'error_type' => 'StateError', 'message' => 'x', 'fatal' => true,
            'occurrences' => 5, 'first_seen_at' => now()->subDay(), 'last_seen_at' => now()]);
        $this->artisan('ops:health-check'); // baseline
        $crash->increment('occurrences', 40);
        ReconciliationRun::query()->update(['finished_at' => now()->subDays(2)]);
        $this->artisan('ops:health-check')->expectsOutputToContain('Problems=2');
        $texts = implode("\n", $this->alerts());
        $this->assertStringContainsString('جهش کرش', $texts);
        $this->assertStringContainsString('مغایرت‌گیری', $texts);
    }
}
