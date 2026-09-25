<?php

namespace App\Domain\Social;

use App\Domain\Settings\Settings;
use App\Domain\Wallet\WalletService;
use App\Enums\SessionKind;
use App\Enums\SessionStatus;
use App\Enums\TransactionType;
use App\Enums\UserStatus;
use App\Exceptions\ApiException;
use App\Models\PointTransaction;
use App\Models\Post;
use App\Models\User;
use App\Models\WalkingSession;
use App\Support\ImageSanitizer;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Photos taken during a walk. A post goes public only once the walk it was taken on
 * is verified by the fraud engine; until then only its author sees it.
 *
 * Views and likes are unique per person and never counted for the author. They pay the
 * author only when they come from a "qualified" account (active, with a verified walk
 * recently), within a per-post and a per-day cap; the shown counts are the real totals.
 */
class PostService
{
    public function __construct(private readonly Settings $settings, private readonly WalletService $wallet) {}

    public function create(User $user, UploadedFile $file, CarbonImmutable $capturedAt, ?string $caption): Post
    {
        if ($capturedAt->gt(now()->addMinutes(2)) || $capturedAt->lt(now()->subDays($this->settings->int('activity.offline_max_age_days')))) {
            throw ApiException::unprocessable('post_time_invalid', 'زمان عکس معتبر نیست.');
        }
        $today = Post::query()->where('user_id', $user->id)->where('created_at', '>=', now($user->timezone)->startOfDay())->count();
        if ($today >= $this->settings->int('social.max_posts_per_day')) {
            throw ApiException::unprocessable('post_limit', 'امروز به سقف تعداد پست رسیده‌اید.');
        }

        $bytes = (string) file_get_contents($file->getRealPath());
        try {
            $full = ImageSanitizer::fitJpeg($bytes, 1600);
            $thumb = ImageSanitizer::fitJpeg($bytes, 480, 78);
        } catch (\RuntimeException) {
            throw ApiException::unprocessable('image_invalid', 'تصویر قابل خواندن نیست.');
        }

        $name = 'posts/'.$user->public_id.'-'.Str::lower((string) Str::ulid());
        Storage::disk('public')->put($name.'.jpg', $full['bytes']);
        Storage::disk('public')->put($name.'-t.jpg', $thumb['bytes']);

        $post = Post::query()->create([
            'user_id' => $user->id,
            'image_path' => $name.'.jpg',
            'thumb_path' => $name.'-t.jpg',
            'width' => $full['width'],
            'height' => $full['height'],
            'caption' => $caption === null ? null : Str::limit(trim(strip_tags($caption)), 200, ''),
            'captured_at' => $capturedAt,
            'status' => Post::PENDING,
        ]);

        return $this->settle($post);
    }

    /** Links a pending post to the walk it was taken on and publishes or rejects it. */
    public function settle(Post $post): Post
    {
        if ($post->status !== Post::PENDING) {
            return $post;
        }
        $at = CarbonImmutable::instance($post->captured_at);
        $session = WalkingSession::query()
            ->where('user_id', $post->user_id)
            ->where('kind', SessionKind::Active)
            ->where('started_at', '<=', $at->addMinute())
            ->where('ended_at', '>=', $at->subMinute())
            ->orderByDesc('id')
            ->first();
        if ($session === null) {
            return $post; // the walk hasn't been uploaded yet
        }

        $post->walking_session_id = $session->id;
        match ($session->status) {
            SessionStatus::Verified, SessionStatus::PartiallyVerified => $post->forceFill(['status' => Post::PUBLISHED, 'published_at' => now()]),
            SessionStatus::Rejected => $post->forceFill(['status' => Post::REJECTED]),
            default => null, // submitted / under review: wait
        };
        $post->save();
        if ($post->status === Post::REJECTED) {
            $post->deleteFiles();
        }

        return $post;
    }

    /** Called when a walk is scored: its photos follow its verdict. */
    public function settleForSession(WalkingSession $session): void
    {
        if ($session->kind !== SessionKind::Active) {
            return;
        }
        Post::query()->where('user_id', $session->user_id)->where('status', Post::PENDING)
            ->whereBetween('captured_at', [$session->started_at->subMinute(), $session->ended_at->addMinute()])
            ->get()->each(fn (Post $p) => $this->settle($p));
    }

    /** Pending posts whose walk never arrived within the offline window are dropped. */
    public function expirePending(): int
    {
        $cutoff = now()->subDays($this->settings->int('activity.offline_max_age_days') + 1);
        $n = 0;
        Post::query()->where('status', Post::PENDING)->where('created_at', '<', $cutoff)->each(function (Post $p) use (&$n) {
            $p->deleteFiles();
            $p->delete();
            $n++;
        });

        return $n;
    }

    public function delete(Post $post): void
    {
        $post->deleteFiles();
        $post->delete();
    }

    public function like(Post $post, User $user): Post
    {
        $this->assertInteractive($post, $user);
        $qualified = $this->qualified($user);
        if ($this->insertOnce('post_likes', $post, $user, ['qualified' => $qualified])) {
            Post::query()->whereKey($post->id)->incrementEach(['likes_count' => 1, 'qualified_likes' => $qualified ? 1 : 0]);
            if ($qualified) {
                $this->award($post);
            }
        }

        return $post->refresh();
    }

    public function unlike(Post $post, User $user): Post
    {
        $row = DB::table('post_likes')->where('post_id', $post->id)->where('user_id', $user->id)->first();
        if ($row !== null && DB::table('post_likes')->where('post_id', $post->id)->where('user_id', $user->id)->delete() > 0) {
            // Points already paid stay paid; a re-like earns nothing new (the total is recomputed).
            Post::query()->whereKey($post->id)->decrementEach(['likes_count' => 1, 'qualified_likes' => $row->qualified ? 1 : 0]);
        }

        return $post->refresh();
    }

    /**
     * Impressions the app saw on screen (≥ 1 s, mostly visible). One per person per post.
     *
     * @param  list<string>  $publicIds
     */
    public function recordViews(User $user, array $publicIds): int
    {
        $qualified = null;
        $new = 0;
        $posts = Post::query()->whereIn('public_id', array_slice(array_unique($publicIds), 0, 50))
            ->where('status', Post::PUBLISHED)->where('user_id', '!=', $user->id)->get();
        foreach ($posts as $post) {
            $qualified ??= $this->qualified($user);
            if ($this->insertOnce('post_views', $post, $user, ['qualified' => $qualified])) {
                Post::query()->whereKey($post->id)->incrementEach(['views_count' => 1, 'qualified_views' => $qualified ? 1 : 0]);
                $new++;
                if ($qualified) {
                    $this->award($post);
                }
            }
        }

        return $new;
    }

    public function report(Post $post, User $user, string $reason): void
    {
        abort_if($post->user_id === $user->id, 422);
        if ($this->insertOnce('post_reports', $post, $user, ['reason' => $reason])) {
            Post::query()->whereKey($post->id)->increment('reports_count');
            $post->refresh();
            if ($post->status === Post::PUBLISHED && $post->reports_count >= $this->settings->int('social.auto_hide_reports')) {
                $post->forceFill(['status' => Post::HIDDEN])->save();
            }
        }
    }

    /** Pays the author the difference between what the post has earned and what was paid. */
    private function award(Post $post): void
    {
        DB::transaction(function () use ($post) {
            $p = Post::query()->whereKey($post->id)->lockForUpdate()->first();
            $earned = min(
                $this->settings->int('social.max_points_per_post'),
                $p->qualified_likes * $this->settings->int('social.points_per_like') + intdiv($p->qualified_views, max(1, $this->settings->int('social.views_per_point'))),
            );
            $owed = $earned - $p->points_awarded;
            if ($owed <= 0) {
                return;
            }
            $owner = User::query()->find($p->user_id);
            if ($owner === null || $owner->status !== UserStatus::Active) {
                return;
            }
            $today = (int) PointTransaction::query()->where('user_id', $owner->id)->where('type', TransactionType::SocialReward)
                ->where('created_at', '>=', now($owner->timezone)->startOfDay()->utc())->sum('amount');
            $give = min($owed, max(0, $this->settings->int('social.daily_cap') - $today));
            if ($give <= 0) {
                return;
            }
            $total = $p->points_awarded + $give;
            $this->wallet->hold($owner, $give, TransactionType::SocialReward, "post:{$p->id}:{$total}", 'پاداش بازدید و لایک عکس پیاده‌روی', $p,
                now()->addHours($this->settings->int('reward.hold_hours')));
            $p->forceFill(['points_awarded' => $total])->save();
        });
    }

    /** Real walkers only: an active account with a verified walk in the last N days. */
    public function qualified(User $user): bool
    {
        return Cache::remember('social:qualified:'.$user->id, 600, fn () => $user->status === UserStatus::Active
            && WalkingSession::query()->where('user_id', $user->id)
                ->whereIn('status', [SessionStatus::Verified, SessionStatus::PartiallyVerified])
                ->where('started_at', '>=', now()->subDays($this->settings->int('social.qualified_days')))
                ->exists());
    }

    private function assertInteractive(Post $post, User $user): void
    {
        if ($post->status !== Post::PUBLISHED) {
            abort(404);
        }
        if ($post->user_id === $user->id) {
            throw ApiException::unprocessable('own_post', 'پست خودتان را نمی‌توانید لایک کنید.');
        }
    }

    /** @param  array<string, mixed>  $extra */
    private function insertOnce(string $table, Post $post, User $user, array $extra): bool
    {
        try {
            return DB::table($table)->insertOrIgnore(['post_id' => $post->id, 'user_id' => $user->id, 'created_at' => now(), ...$extra]) > 0;
        } catch (QueryException) {
            return false;
        }
    }
}
