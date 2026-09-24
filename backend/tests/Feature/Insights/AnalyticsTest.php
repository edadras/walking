<?php

namespace Tests\Feature\Insights;

use App\Domain\Analytics\Metrics;
use App\Domain\Analytics\ReportExporter;
use App\Domain\Wallet\WalletService;
use App\Enums\AdminRole;
use App\Enums\SponsorRole;
use App\Enums\TransactionType;
use App\Filament\Admin\Pages\Reports;
use App\Filament\Admin\Widgets\PointsFlowChart;
use App\Filament\Admin\Widgets\TopFraudRules;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\DailyActivity;
use App\Models\FraudEvent;
use App\Models\FraudRule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PlatformSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesSponsorOffers;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use CreatesSponsorOffers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 12:00:00', 'Asia/Tehran')->utc());
        $this->seed(PlatformSeeder::class);
    }

    private function world(): User
    {
        $a = User::factory()->create(['created_at' => now()->subDays(2)]);
        $b = User::factory()->create();
        DailyActivity::query()->create(['user_id' => $a->id, 'local_date' => '2026-09-24', 'raw_steps' => 8000, 'verified_steps' => 7000, 'goal_steps' => 7500]);
        DailyActivity::query()->create(['user_id' => $b->id, 'local_date' => '2026-09-24', 'raw_steps' => 3000, 'verified_steps' => 3000, 'goal_steps' => 7500]);
        DailyActivity::query()->create(['user_id' => $a->id, 'local_date' => '2026-09-23', 'raw_steps' => 5000, 'verified_steps' => 5000, 'goal_steps' => 7500]);
        $w = app(WalletService::class);
        $w->credit($a, 120, TransactionType::WalkingReward, 'r1', 'x');
        $w->debit($a, 50, TransactionType::Purchase, 'p1', 'y');
        FraudEvent::query()->create(['user_id' => $b->id, 'subject_type' => 'walking_session', 'subject_id' => 1, 'rule_key' => 'vehicle_speed', 'score' => 60, 'severity' => 'high']);

        return $a;
    }

    public function test_daily_series_are_zero_filled_and_in_iran_days(): void
    {
        $this->world();
        $m = app(Metrics::class);

        $this->assertCount(7, $m->days(7));
        $this->assertSame('2026-09-24', array_key_last($m->activeUsers(7)));
        $this->assertSame(2, $m->activeUsers(7)['2026-09-24']);
        $this->assertSame(1, $m->activeUsers(7)['2026-09-23']);
        $this->assertSame(0, $m->activeUsers(7)['2026-09-20']);
        $this->assertSame(10_000, $m->steps(7)['verified']['2026-09-24']);
        $this->assertSame(120, $m->points(7)['issued']['2026-09-24']);
        $this->assertSame(50, $m->points(7)['spent']['2026-09-24']);
        $this->assertSame('vehicle_speed', $m->topFraudRules(7)[0]->rule_key);
        $this->assertSame('2/7', $m->labels(1)[0], 'Jalali 1405/07/02');
    }

    public function test_reports_stream_masked_csv_and_are_audited(): void
    {
        $user = $this->world();
        $exporter = app(ReportExporter::class);
        $from = CarbonImmutable::parse('2026-09-01', 'Asia/Tehran');
        $to = CarbonImmutable::parse('2026-09-24', 'Asia/Tehran')->endOfDay();

        $users = iterator_to_array($exporter->rows('users', $from, $to));
        $this->assertStringContainsString('***', $users[0][1]);
        $this->assertStringNotContainsString(substr($user->phone, -7, 3), $users[0][1]);

        ob_start();
        $exporter->stream('daily', $from, $to);
        $csv = ob_get_clean();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('2026-09-24,2,', $csv);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(Admin::factory()->role(AdminRole::Finance)->create(), 'admin');
        $this->get('/admin/reports')->assertOk();
        Livewire::test(Reports::class)->fillForm(['report' => 'ledger', 'from' => '2026-09-01', 'to' => '2026-09-24'])->call('export')->assertFileDownloaded();
        $this->assertTrue(AuditLog::query()->where('action', 'report.exported')->exists());
    }

    public function test_dashboards_render_for_the_right_roles(): void
    {
        $this->world();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs(Admin::factory()->role(AdminRole::FraudAnalyst)->create(), 'admin');
        $this->get('/admin/fraud')->assertOk();
        Livewire::test(TopFraudRules::class)->assertSee(FraudRule::query()->where('key', 'vehicle_speed')->value('name'));
        $this->get('/admin/reports')->assertForbidden();
    }

    public function test_platform_dashboard_charts(): void
    {
        $this->world();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(Admin::factory()->role(AdminRole::SuperAdmin)->create(), 'admin');
        $this->get('/admin')->assertOk();
        Livewire::test(PointsFlowChart::class)->assertOk();
    }

    public function test_sponsor_analytics_are_scoped(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('sponsor'));
        $sponsor = $this->sponsor();
        $this->actingAs($this->sponsorUser($sponsor, SponsorRole::Analyst), 'sponsor');
        $this->get('/sponsor')->assertOk();
        $this->get('/sponsor/visits')->assertOk()->assertSee('خروجی ۹۰ روز');
        $this->assertSame(0, array_sum(app(Metrics::class)->visits(30, $sponsor->id)['rewarded']));
    }
}
