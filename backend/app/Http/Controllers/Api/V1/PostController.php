<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Social\PostService;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Walk photos: feed, own posts, likes, unique views and reports. */
class PostController extends Controller
{
    public function __construct(private readonly PostService $posts) {}

    /** GET /posts?scope=all|mine&before={id} — newest first, 20 per page. */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['scope' => ['nullable', 'in:all,mine'], 'before' => ['nullable', 'string', 'max:40']]);
        $user = $request->user();
        $mine = ($data['scope'] ?? 'all') === 'mine';

        $query = Post::query()->with(['user', 'session'])
            ->when($mine, fn ($q) => $q->where('user_id', $user->id)->whereIn('status', [Post::PENDING, Post::PUBLISHED, Post::HIDDEN]),
                fn ($q) => $q->where('status', Post::PUBLISHED))
            ->orderByDesc('id');
        if (! empty($data['before']) && ($cursor = Post::query()->where('public_id', $data['before'])->value('id'))) {
            $query->where('id', '<', $cursor);
        }
        $page = $query->limit(21)->get();
        $more = $page->count() > 20;
        $page = $page->take(20);

        $liked = DB::table('post_likes')->where('user_id', $user->id)->whereIn('post_id', $page->pluck('id'))->pluck('post_id')->flip();

        return response()->json([
            'data' => $page->map(fn (Post $p) => $this->present($p, $user, $liked->has($p->id)))->values(),
            'meta' => ['next' => $more ? $page->last()->public_id : null],
        ]);
    }

    public function show(Request $request, Post $post): JsonResponse
    {
        $user = $request->user();
        abort_unless($post->status === Post::PUBLISHED || $post->user_id === $user->id, 404);
        $liked = DB::table('post_likes')->where('user_id', $user->id)->where('post_id', $post->id)->exists();

        return response()->json(['data' => $this->present($post->load(['user', 'session']), $user, $liked)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=320,min_height=320,max_width=8000,max_height=8000'],
            'captured_at' => ['required', 'date'],
            'caption' => ['nullable', 'string', 'max:200'],
        ]);
        $post = $this->posts->create($request->user(), $request->file('image'), CarbonImmutable::parse($data['captured_at'])->utc(), $data['caption'] ?? null);

        return response()->json(['data' => $this->present($post->load(['user', 'session']), $request->user(), false)], 201);
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        abort_unless($post->user_id === $request->user()->id, 404);
        $this->posts->delete($post);

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function like(Request $request, Post $post): JsonResponse
    {
        $post = $this->posts->like($post, $request->user());

        return response()->json(['data' => ['liked' => true, 'likes_count' => $post->likes_count]]);
    }

    public function unlike(Request $request, Post $post): JsonResponse
    {
        $post = $this->posts->unlike($post, $request->user());

        return response()->json(['data' => ['liked' => false, 'likes_count' => $post->likes_count]]);
    }

    /** POST /posts/views {ids: [...]} — batched on-screen impressions. */
    public function views(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'max:50'], 'ids.*' => ['string', 'max:40']]);

        return response()->json(['data' => ['recorded' => $this->posts->recordViews($request->user(), $data['ids'])]]);
    }

    public function report(Request $request, Post $post): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'in:inappropriate,privacy,spam,not_walk,other']]);
        abort_unless($post->status === Post::PUBLISHED, 404);
        $this->posts->report($post, $request->user(), $data['reason']);

        return response()->json(['data' => ['reported' => true]]);
    }

    /** @return array<string, mixed> */
    private function present(Post $p, User $viewer, bool $liked): array
    {
        $mine = $p->user_id === $viewer->id;
        $s = $p->session;

        return array_filter([
            'id' => $p->public_id,
            'image_url' => $p->imageUrl(),
            'thumb_url' => $p->thumbUrl(),
            'width' => $p->width,
            'height' => $p->height,
            'caption' => $p->caption,
            'captured_at' => $p->captured_at->toIso8601String(),
            'published_at' => $p->published_at?->toIso8601String(),
            'author' => [
                'name' => $p->user?->publicName() ?? 'کاربر گام‌یار',
                'avatar_url' => $p->user?->avatar_path ? Storage::disk('public')->url($p->user->avatar_path) : null,
                'level' => $p->user?->level,
            ],
            'walk' => $s === null ? null : [
                'activity_type' => $s->activity_type->value,
                'steps' => $s->raw_steps,
                'distance_m' => max($s->distance_m, $s->cycling_distance_m),
            ],
            'views_count' => $p->views_count,
            'likes_count' => $p->likes_count,
            'liked' => $liked,
            'mine' => $mine,
            // Only the author sees moderation state and what the post earned.
            'status' => $mine ? $p->status : null,
            'points_awarded' => $mine ? $p->points_awarded : null,
        ], fn ($v) => $v !== null);
    }
}
