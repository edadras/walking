<?php

namespace Tests\Feature\Social;

use App\Domain\Fraud\FraudEngine;
use App\Domain\Social\PostService;
use App\Domain\User\AccountPurger;
use App\Enums\AdminRole;
use App\Enums\IntegrityVerdict;
use App\Enums\SessionStatus;
use App\Enums\TransactionType;
use App\Filament\Admin\Resources\Posts\Pages\ListPosts;
use App\Models\AccountDeletionRequest;
use App\Models\Admin;
use App\Models\Device;
use App\Models\PointTransaction;
use App\Models\Post;
use App\Models\User;
use App\Models\WalkingSession;
use Carbon\CarbonImmutable;
use Database\Seeders\PlatformSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\CreatesScoredSessions;
use Tests\TestCase;

class WalkPhotosTest extends TestCase
{
    use CreatesScoredSessions, RefreshDatabase;

    private User $author;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 12:00:00', 'Asia/Tehran')->utc());
        $this->seed(PlatformSeeder::class);
        Storage::fake('public');
        Cache::flush();
        $this->author = User::factory()->create();
        $this->device = Device::factory()->create(['integrity_verdict' => IntegrityVerdict::Device]);
        $this->device->users()->attach($this->author, ['first_seen_at' => now(), 'last_seen_at' => now()]);
    }

    /** App user without a device token (the device middleware only checks real tokens). */
    private function as(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAs($user->fresh(), 'sanctum');
    }

    /** A verified walk from 11:00 to 11:15 Tehran time. */
    private function walk(?User $user = null, bool $score = true): WalkingSession
    {
        $user ??= $this->author;
        $session = $this->makeSession($user, $this->device, $this->walkingMinutes(15), start: CarbonImmutable::parse('2026-09-24 11:00:00', 'Asia/Tehran')->utc());

        return $score ? app(FraudEngine::class)->score($session)->fresh() : $session;
    }

    /** A walker whose views/likes count for points. */
    private function walker(): User
    {
        $u = User::factory()->create();
        $this->makeSession($u, $this->device, $this->walkingMinutes(5), ['status' => SessionStatus::Verified], CarbonImmutable::now()->subDays(2));

        return $u;
    }

    private function upload(string $at = '2026-09-24 11:07:00', ?UploadedFile $file = null)
    {
        $this->as($this->author);

        return $this->postJson('/api/v1/posts', [
            'image' => $file ?? UploadedFile::fake()->image('walk.jpg', 1200, 900),
            'captured_at' => CarbonImmutable::parse($at, 'Asia/Tehran')->toIso8601String(),
            'caption' => 'غروب پارک <b>ملت</b>',
        ]);
    }

    private function published(): Post
    {
        $this->walk();
        $this->upload()->assertCreated()->assertJsonPath('data.status', 'published');

        return Post::query()->sole();
    }

    public function test_a_photo_taken_during_a_walk_goes_public_when_the_walk_is_verified(): void
    {
        $this->upload()->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.caption', 'غروب پارک ملت');
        $post = Post::query()->sole();
        Storage::disk('public')->assertExists([$post->image_path, $post->thumb_path]);
        $this->assertSame([1200, 900], [$post->width, $post->height]);

        // Others don't see it yet.
        $this->as($this->walker());
        $this->getJson('/api/v1/posts')->assertOk()->assertJsonCount(0, 'data');

        $this->walk(); // uploaded + scored → listener settles the photo
        $this->assertSame(Post::PUBLISHED, $post->fresh()->status);
        $this->getJson('/api/v1/posts')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.author.name', $this->author->publicName())
            ->assertJsonPath('data.0.walk.steps', $post->fresh()->session->raw_steps)
            ->assertJsonMissingPath('data.0.status')
            ->assertJsonMissingPath('data.0.points_awarded');
    }

    public function test_photos_outside_any_walk_wait_and_photos_from_rejected_walks_are_dropped(): void
    {
        $this->walk();
        $this->upload('2026-09-24 11:40:00')->assertCreated()->assertJsonPath('data.status', 'pending');

        $bad = $this->makeSession($this->author, $this->device, $this->walkingMinutes(10), start: CarbonImmutable::parse('2026-09-24 09:00:00', 'Asia/Tehran')->utc());
        $bad->forceFill(['status' => SessionStatus::Rejected, 'scored_at' => now()])->save();
        $this->upload('2026-09-24 09:05:00')->assertCreated()->assertJsonPath('data.status', 'rejected');
        $rejected = Post::query()->where('status', Post::REJECTED)->sole();
        Storage::disk('public')->assertMissing($rejected->image_path);

        // Never-matched photos expire after the offline window.
        CarbonImmutable::setTestNow(now()->addDays(9));
        $this->assertSame(1, app(PostService::class)->expirePending());
    }

    public function test_metadata_is_stripped_and_the_picture_re_encoded(): void
    {
        $fake = UploadedFile::fake()->image('a.jpg', 800, 600);
        $jpeg = file_get_contents($fake->getRealPath());
        // Inject an EXIF block carrying a fake GPS tag right after SOI.
        $exif = "Exif\0\0".'GPSLatitude=35.7;GPSLongitude=51.4';
        $withExif = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
        $path = tempnam(sys_get_temp_dir(), 'img').'.jpg';
        file_put_contents($path, $withExif);

        $this->walk();
        $this->upload(file: new UploadedFile($path, 'a.jpg', 'image/jpeg', null, true))->assertCreated();
        $stored = Storage::disk('public')->get(Post::query()->sole()->image_path);
        $this->assertStringNotContainsString('GPSLatitude', $stored);
        $this->assertStringNotContainsString('Exif', $stored);
    }

    public function test_views_are_unique_real_and_never_the_authors_own(): void
    {
        $post = $this->published();
        $viewer = $this->walker();
        $newcomer = User::factory()->create(); // no verified walk: counts, doesn't pay

        $this->as($viewer);
        $this->postJson('/api/v1/posts/views', ['ids' => [$post->public_id]])->assertJsonPath('data.recorded', 1);
        $this->postJson('/api/v1/posts/views', ['ids' => [$post->public_id, $post->public_id]])->assertJsonPath('data.recorded', 0);
        $this->as($newcomer);
        $this->postJson('/api/v1/posts/views', ['ids' => [$post->public_id]])->assertJsonPath('data.recorded', 1);
        $this->as($this->author);
        $this->postJson('/api/v1/posts/views', ['ids' => [$post->public_id]])->assertJsonPath('data.recorded', 0);

        $post->refresh();
        $this->assertSame(2, $post->views_count);
        $this->assertSame(1, $post->qualified_views);
        $this->getJson('/api/v1/posts/'.$post->public_id)->assertJsonPath('data.views_count', 2);
    }

    public function test_every_n_qualified_views_pay_a_point(): void
    {
        $post = $this->published();
        foreach (range(1, 20) as $i) {
            $this->as($this->walker());
            $this->postJson('/api/v1/posts/views', ['ids' => [$post->public_id]])->assertOk();
        }

        $this->assertSame(1, $post->fresh()->points_awarded);
        $this->assertSame(1, (int) PointTransaction::query()->where('user_id', $this->author->id)->where('type', TransactionType::SocialReward)->sum('amount'));
    }

    public function test_likes_toggle_count_once_and_pay_once(): void
    {
        $post = $this->published();
        $fan = $this->walker();
        $this->as($fan);

        $this->postJson("/api/v1/posts/{$post->public_id}/like")->assertJsonPath('data.likes_count', 1);
        $this->postJson("/api/v1/posts/{$post->public_id}/like")->assertJsonPath('data.likes_count', 1);
        $this->getJson('/api/v1/posts')->assertJsonPath('data.0.liked', true);
        $this->deleteJson("/api/v1/posts/{$post->public_id}/like")->assertJsonPath('data.likes_count', 0);
        $this->postJson("/api/v1/posts/{$post->public_id}/like")->assertJsonPath('data.likes_count', 1);

        $this->assertSame(1, $post->fresh()->points_awarded);
        $this->assertSame(1, PointTransaction::query()->where('type', TransactionType::SocialReward)->count());

        $this->as($this->author);
        $this->postJson("/api/v1/posts/{$post->public_id}/like")->assertUnprocessable()->assertJsonPath('error.code', 'own_post');
        $this->getJson('/api/v1/posts?scope=mine')->assertJsonPath('data.0.points_awarded', 1);
    }

    public function test_points_stop_at_the_post_and_daily_caps(): void
    {
        $post = $this->published();
        $post->forceFill(['qualified_likes' => 40, 'likes_count' => 40])->save();
        $this->as($this->walker());
        $this->postJson("/api/v1/posts/{$post->public_id}/like")->assertOk();

        // 41 qualified likes → 30 per post, but only 20 per day.
        $this->assertSame(20, $post->fresh()->points_awarded);
        CarbonImmutable::setTestNow(now()->addDay());
        $this->as($this->walker());
        $this->postJson("/api/v1/posts/{$post->public_id}/like")->assertOk();
        $this->assertSame(30, $post->fresh()->points_awarded);
    }

    public function test_social_points_are_not_withdrawable_by_default(): void
    {
        $this->assertStringNotContainsString('social_reward', config('walk.settings')['cashout.eligible_types']['value']);
    }

    public function test_three_reports_hide_a_post_until_reviewed(): void
    {
        $post = $this->published();
        foreach (range(1, 3) as $i) {
            $this->as($this->walker());
            $this->postJson("/api/v1/posts/{$post->public_id}/report", ['reason' => 'privacy'])->assertOk();
        }

        $this->assertSame(Post::HIDDEN, $post->fresh()->status);
        $this->getJson('/api/v1/posts')->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/posts/{$post->public_id}/like")->assertNotFound();
    }

    public function test_daily_post_limit_and_own_delete(): void
    {
        $this->walk();
        foreach (range(1, 5) as $i) {
            $this->upload('2026-09-24 11:0'.$i.':00')->assertCreated();
        }
        $this->upload()->assertUnprocessable()->assertJsonPath('error.code', 'post_limit');

        $post = Post::query()->first();
        $this->as($this->walker());
        $this->deleteJson('/api/v1/posts/'.$post->public_id)->assertNotFound();
        $this->as($this->author);
        $this->deleteJson('/api/v1/posts/'.$post->public_id)->assertOk();
        Storage::disk('public')->assertMissing($post->image_path);
    }

    public function test_deleting_the_account_removes_its_photos(): void
    {
        $post = $this->published();
        $request = AccountDeletionRequest::query()->create(['user_id' => $this->author->id, 'status' => 'pending', 'requested_at' => now(), 'scheduled_for' => now()]);

        $this->assertTrue(app(AccountPurger::class)->purge($request));
        $this->assertNull(Post::query()->find($post->id));
        Storage::disk('public')->assertMissing($post->image_path);
    }

    public function test_moderators_review_reported_photos_in_the_panel(): void
    {
        $post = $this->published();
        $post->forceFill(['status' => Post::HIDDEN, 'reports_count' => 3])->save();
        DB::table('post_reports')->insert(['post_id' => $post->id, 'user_id' => $this->walker()->id, 'reason' => 'privacy', 'created_at' => now()]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(Admin::factory()->role(AdminRole::ContentEditor)->create(), 'admin');
        $this->get('/admin/posts')->assertOk()->assertSee('عکس‌های پیاده‌روی');
        $this->get('/admin/posts/'.$post->public_id)->assertOk()->assertSee('حریم خصوصی');

        Livewire::test(ListPosts::class)
            ->assertCanSeeTableRecords([$post])
            ->callTableAction('restore', $post);
        $this->assertSame(Post::PUBLISHED, $post->fresh()->status);
        $this->assertSame(0, $post->fresh()->reports_count);

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->actingAs(Admin::factory()->role(AdminRole::Finance)->create(), 'admin');
        $this->get('/admin/posts')->assertForbidden();
    }
}
