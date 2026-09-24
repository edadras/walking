<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AdminRole;
use App\Filament\Admin\Resources\ClientErrors\Pages\ListClientErrors;
use App\Http\Controllers\Api\V1\ClientErrorController;
use App\Models\Admin;
use App\Models\ClientError;
use Database\Seeders\PlatformSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

class ClientErrorTest extends TestCase
{
    use RefreshDatabase, SignsDeviceRequests;

    private function report(array $overrides = [], array $headers = [])
    {
        return $this->postJson('/api/v1/client-errors', [
            'type' => 'StateError',
            'message' => 'Bad state: no element for 09121234567',
            'stack' => "#0 ListBase.first (dart:collection/list.dart:12:5)\n#1 _ChallengeDetailPageState.build (package:gamyar/features/challenges/presentation/challenges_page.dart:161:9)",
            'fatal' => true,
            'app_version' => '1.2.0',
            ...$overrides,
        ], $headers);
    }

    public function test_reports_are_grouped_scrubbed_and_reopened(): void
    {
        $this->report()->assertNoContent();
        $this->report(['message' => 'Bad state: other text'])->assertNoContent();

        $row = ClientError::query()->sole();
        $this->assertSame(2, $row->occurrences);
        $this->assertSame('Bad state: other text', $row->message);
        $this->assertStringContainsString('_ChallengeDetailPageState.build', $row->stack, 'long class names survive scrubbing');

        $this->report(['type' => 'FormatException', 'stack' => '#0 other'])->assertNoContent();
        $this->assertSame(2, ClientError::query()->count());

        $row->forceFill(['resolved_at' => now()])->save();
        $this->report();
        $this->assertNull($row->fresh()->resolved_at, 'a fixed crash that comes back is reopened');
    }

    public function test_personal_data_is_scrubbed(): void
    {
        $scrubbed = ClientErrorController::scrub('user +989121234567 mail a.b@x.ir token 1|abcDEF123456789012345678901 order 99812345');
        $this->assertSame('user [phone] mail [email] token [redacted] order [n]', $scrubbed);
    }

    public function test_signed_in_reports_record_the_user_and_input_is_bounded(): void
    {
        $user = $this->loginAs();
        $this->report([], ['Authorization' => 'Bearer '.$this->token])->assertNoContent();
        $this->assertSame($user->id, ClientError::query()->sole()->last_user_id);

        $this->report(['app_version' => '<script>'])->assertStatus(422);
        $this->report(['stack' => str_repeat('x', 9000)])->assertStatus(422);
    }

    public function test_reporting_is_throttled(): void
    {
        foreach (range(1, 10) as $_) {
            $this->report()->assertNoContent();
        }
        $this->report()->assertStatus(429);
    }

    public function test_admin_list_and_resolve(): void
    {
        $this->seed(PlatformSeeder::class);
        $this->report();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs(Admin::factory()->role(AdminRole::Operations)->create(), 'admin');
        $this->get('/admin/client-errors')->assertOk()->assertSee('StateError');
        $row = ClientError::query()->sole();
        Livewire::test(ListClientErrors::class)->callTableAction('resolve', $row);
        $this->assertNotNull($row->fresh()->resolved_at);
    }

    public function test_other_admin_roles_cannot_see_crashes(): void
    {
        $this->seed(PlatformSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(Admin::factory()->role(AdminRole::ContentEditor)->create(), 'admin');
        $this->get('/admin/client-errors')->assertForbidden();
    }

    public function test_stale_groups_are_pruned(): void
    {
        $this->report();
        $this->travel(91)->days();
        $this->artisan('retention:prune')->assertSuccessful();
        $this->assertSame(0, ClientError::query()->count());
    }
}
