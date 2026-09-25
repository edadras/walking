<?php

namespace Tests\Feature\Engagement;

use App\Domain\Fraud\FraudEngine;
use App\Domain\Settings\FeatureFlags;
use App\Enums\AdminRole;
use App\Enums\IntegrityVerdict;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Filament\Admin\Resources\Quests\Pages\ListQuests;
use App\Models\Admin;
use App\Models\FeatureFlag;
use App\Models\PointTransaction;
use App\Models\Quest;
use App\Models\QuestClaim;
use App\Models\User;
use App\Models\WaterLog;
use Carbon\CarbonImmutable;
use Database\Seeders\PlatformSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesScoredSessions;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

class QuestTest extends TestCase
{
    use CreatesScoredSessions, RefreshDatabase, SignsDeviceRequests;

    protected function setUp(): void
    {
        parent::setUp();
        // Thursday 24 Sep 2026: the Iranian week began on Saturday the 19th.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 21:00:00', 'Asia/Tehran')->utc());
        $this->seed(PlatformSeeder::class);
    }

    private function walk(User $user, string $date, int $steps): void
    {
        $user->devices()->first()?->update(['integrity_verdict' => IntegrityVerdict::Device]);
        $buckets = [];
        for ($left = $steps, $i = 0; $left > 0; $i++) {
            $n = min($left, 100 + ($i * 7) % 11);
            $buckets[] = ['steps' => $n, 'detector_steps' => $n, 'accel_std' => 2.1, 'accel_peak_hz' => 1.9];
            $left -= $n;
        }
        $start = CarbonImmutable::parse("$date 10:00:00", 'Asia/Tehran')->utc()->addMinutes($this->seq * 90);
        app(FraudEngine::class)->score($this->makeSession($user, $this->device, $buckets, start: $start));
    }

    private function quest(array $list, string $key): array
    {
        return collect($list)->firstWhere('key', $key);
    }

    public function test_progress_comes_from_verified_data_and_claims_pay_once(): void
    {
        $user = $this->loginAs();
        $this->device->update(['integrity_verdict' => IntegrityVerdict::Device]);
        $this->walk($user, '2026-09-24', 6500);
        WaterLog::query()->create(['user_id' => $user->id, 'local_date' => '2026-09-24', 'amount_ml' => 750, 'logged_at' => now()]);

        $list = $this->authedJson('GET', '/api/v1/quests')->assertOk()->json('data');
        $steps = $this->quest($list, 'daily_steps_6k');
        $this->assertSame(6000, $steps['progress'], 'capped at the target');
        $this->assertTrue($steps['claimable']);
        $this->assertSame(750, $this->quest($list, 'daily_water_2l')['progress']);
        $this->assertFalse($this->quest($list, 'daily_water_2l')['claimable']);
        $this->assertSame('2026-09-25', $this->quest($list, 'weekly_goal_5')['ends_on'], 'week ends on Friday');

        $this->signedJson('POST', '/api/v1/quests/daily_steps_6k/claim')->assertOk();
        $this->signedJson('POST', '/api/v1/quests/daily_steps_6k/claim')->assertOk();
        $this->assertSame(1, QuestClaim::query()->count());
        $tx = PointTransaction::query()->where('type', TransactionType::QuestReward)->sole();
        $this->assertSame(TransactionStatus::Pending, $tx->status, 'held like every other reward');
        $this->assertSame(10, $tx->amount);

        $this->signedJson('POST', '/api/v1/quests/daily_water_2l/claim')->assertStatus(409)->assertJsonPath('error.code', 'quest_incomplete');
        $this->signedJson('POST', '/api/v1/quests/nope/claim')->assertStatus(422);

        // A new day is a new period for daily quests.
        $this->travel(1)->days();
        $this->assertFalse($this->quest($this->authedJson('GET', '/api/v1/quests')->json('data'), 'daily_steps_6k')['claimed']);
    }

    public function test_disabled_flag_hides_and_blocks_quests(): void
    {
        $this->loginAs();
        FeatureFlag::query()->where('key', 'quests')->update(['is_enabled' => false]);
        app(FeatureFlags::class)->flush();
        $this->authedJson('GET', '/api/v1/quests')->assertOk()->assertJsonPath('data', []);
        $this->signedJson('POST', '/api/v1/quests/daily_steps_6k/claim')->assertForbidden();
    }

    public function test_admin_manages_quests(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(Admin::factory()->role(AdminRole::Operations)->create(), 'admin');
        $this->get('/admin/quests')->assertOk()->assertSee('پنج روز هدف');
        Livewire::test(ListQuests::class)->assertCanSeeTableRecords(Quest::query()->get());
        $this->get('/admin/quests/create')->assertOk();
    }
}
