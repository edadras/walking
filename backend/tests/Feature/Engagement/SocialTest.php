<?php

namespace Tests\Feature\Engagement;

use App\Domain\Social\SocialService;
use App\Exceptions\ApiException;
use App\Models\DailyActivity;
use App\Models\FriendChallenge;
use App\Models\Friendship;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

class SocialTest extends TestCase
{
    use RefreshDatabase, SignsDeviceRequests;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 12:00:00', 'Asia/Tehran')->utc());
        $this->seed(PlatformSeeder::class);
    }

    private function social(): SocialService
    {
        return app(SocialService::class);
    }

    private function steps(User $u, string $date, int $steps): void
    {
        DailyActivity::query()->create(['user_id' => $u->id, 'local_date' => $date, 'verified_steps' => $steps, 'goal_steps' => 7500]);
    }

    private function friends(User $a, User $b): Friendship
    {
        $f = $this->social()->request($a, $b->referral_code);

        return $this->social()->accept($b, $f);
    }

    public function test_request_accept_and_weekly_ranking_through_the_api(): void
    {
        $me = $this->loginAs();
        $sara = User::factory()->create(['display_name' => 'سارا']);
        $stranger = User::factory()->create(['display_name' => 'غریبه']);
        $this->steps($me, '2026-09-23', 4000);
        $this->steps($sara, '2026-09-22', 9000);
        $this->steps($sara, '2026-09-10', 50000); // last week: ignored
        $this->steps($stranger, '2026-09-23', 99999);

        $this->authedJson('POST', '/api/v1/friends', ['code' => strtolower($sara->referral_code)])->assertCreated()
            ->assertJsonPath('data.outgoing.0.name', 'سارا')->assertJsonCount(1, 'data.ranking');

        // Pending: no steps shared yet. Sara accepts (she sends our code back).
        $this->social()->request($sara, $me->referral_code);
        $data = $this->authedJson('GET', '/api/v1/friends')->assertOk()->json('data');
        $this->assertSame(['سارا', $me->publicName()], array_column($data['ranking'], 'name'));
        $this->assertSame([9000, 4000], array_column($data['ranking'], 'week_steps'));
        $this->assertSame($me->referral_code, $data['code']);
        $this->assertNotContains('غریبه', array_column($data['ranking'], 'name'));

        $this->authedJson('DELETE', '/api/v1/friends/'.$data['ranking'][0]['friendship_id'])->assertOk()->assertJsonCount(1, 'data.ranking');
    }

    public function test_bad_codes_and_other_peoples_friendships(): void
    {
        $me = $this->loginAs();
        $this->authedJson('POST', '/api/v1/friends', ['code' => $me->referral_code])->assertStatus(422);
        $this->authedJson('POST', '/api/v1/friends', ['code' => 'NOPE1234'])->assertStatus(422)->assertJsonPath('error.code', 'friend_not_found');

        $a = User::factory()->create();
        $b = User::factory()->create();
        $f = $this->friends($a, $b);
        $this->authedJson('DELETE', "/api/v1/friends/{$f->id}")->assertNotFound();
        $this->authedJson('POST', "/api/v1/friends/{$f->id}/accept")->assertNotFound();
    }

    public function test_requester_cannot_accept_their_own_request(): void
    {
        [$a, $b] = [User::factory()->create(), User::factory()->create()];
        $f = $this->social()->request($a, $b->referral_code);
        $this->social()->accept($a, $f);
        $this->assertSame(Friendship::PENDING, $f->fresh()->status);
    }

    public function test_friendly_race_invites_only_friends_and_ranks_joined_members(): void
    {
        $me = $this->loginAs();
        $sara = User::factory()->create(['display_name' => 'سارا']);
        $reza = User::factory()->create(['display_name' => 'رضا']);
        $stranger = User::factory()->create();
        $this->friends($me, $sara);
        $this->friends($me, $reza);

        $this->authedJson('POST', '/api/v1/friend-challenges', ['title' => 'هفته پرقدم', 'days' => 7, 'friend_ids' => [$stranger->public_id]])
            ->assertStatus(422)->assertJsonPath('error.code', 'not_friends');

        $id = $this->authedJson('POST', '/api/v1/friend-challenges', ['title' => 'هفته پرقدم', 'days' => 7, 'friend_ids' => [$sara->public_id, $reza->public_id]])
            ->assertCreated()->assertJsonPath('data.starts_on', '2026-09-25')->assertJsonPath('data.status', 'upcoming')
            ->assertJsonPath('data.members', 1)->json('data.id');
        $this->assertTrue($sara->notifications()->where('data->data->type', 'friend_challenge')->exists());

        $challenge = FriendChallenge::query()->where('public_id', $id)->sole();
        $this->social()->respond($sara, $challenge, true);
        $this->social()->respond($reza, $challenge, false);

        $this->travel(3)->days();
        $this->steps($me, '2026-09-25', 3000);
        $this->steps($sara, '2026-09-26', 8000);
        $this->steps($sara, '2026-09-24', 20000); // before the start

        $this->authedJson('GET', "/api/v1/friend-challenges/{$id}")->assertOk()
            ->assertJsonPath('data.status', 'running')
            ->assertJsonPath('data.standings.0.name', 'سارا')->assertJsonPath('data.standings.0.steps', 8000)
            ->assertJsonPath('data.standings.1.steps', 3000)->assertJsonCount(2, 'data.standings');
        $this->authedJson('GET', '/api/v1/friend-challenges')->assertJsonCount(1, 'data');

        // Strangers can't peek.
        $this->expectException(NotFoundHttpException::class);
        $this->social()->present($stranger, $challenge);
    }

    public function test_limits(): void
    {
        $me = User::factory()->create();
        $f = User::factory()->create();
        $this->friends($me, $f);
        foreach (range(1, 3) as $i) {
            $this->social()->createChallenge($me, "رقابت {$i}", 7, [$f->public_id]);
        }
        $this->expectException(ApiException::class);
        $this->social()->createChallenge($me, 'چهارمی', 7, [$f->public_id]);
    }
}
