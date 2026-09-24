<?php

namespace Tests\Feature\Api\V1;

use App\Models\WalkingSession;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

/**
 * Submits the exact payload the Flutter app produces (pinned in
 * /contracts/walking_session_batch.json by mobile/test/.../payload_contract_test.dart).
 * If either side changes the format, one of the two tests fails.
 */
class PayloadContractTest extends TestCase
{
    use RefreshDatabase, SignsDeviceRequests;

    public function test_the_apps_batch_payload_is_accepted_as_is(): void
    {
        // The fixture was generated for 2026-09-24 (Tehran); run the server on that day.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 22:00:00', 'Asia/Tehran')->utc());
        $this->loginAs();

        $payload = json_decode(file_get_contents(base_path('../contracts/walking_session_batch.json')), true, flags: JSON_THROW_ON_ERROR);

        $results = $this->signedJson('POST', '/api/v1/walking-sessions/batch', $payload)->assertOk()->json('data');

        $this->assertSame(['accepted', 'accepted'], array_column($results, 'status'), json_encode($results, JSON_UNESCAPED_UNICODE));

        $active = WalkingSession::query()->where('kind', 'active')->firstOrFail();
        $this->assertSame(339, $active->raw_steps);
        $this->assertSame(263, $active->distance_m); // plausible GPS distance (262.5 m) wins over the step estimate
        $this->assertSame(0.982, round($active->motion_summary['detector_ratio'], 3)); // 333 detector / 339 counter

        $passive = WalkingSession::query()->where('kind', 'passive')->firstOrFail();
        $this->assertSame(1600, $passive->raw_steps);
        $this->assertSame('2026-09-24', $passive->local_date->toDateString());
    }
}
