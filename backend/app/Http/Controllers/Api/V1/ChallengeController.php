<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Challenge\ChallengeService;
use App\Enums\ChallengeStatus;
use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Models\ChallengeParticipant;
use App\Models\OrganizationMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChallengeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $mine = ChallengeParticipant::query()->where('user_id', $user->id)->get()->keyBy('challenge_id');

        $orgId = OrganizationMember::query()->where('user_id', $user->id)->value('organization_id');
        $challenges = Challenge::query()
            ->whereIn('status', [ChallengeStatus::Active, ChallengeStatus::Ended])
            // Company challenges only for that company's members.
            ->where(fn ($q) => $q->whereNull('organization_id')->when($orgId, fn ($q) => $q->orWhere('organization_id', $orgId)))
            ->where(fn ($q) => $q->where('ends_at', '>', now()->subDays(14))->orWhereIn('id', $mine->keys()))
            ->orderBy('ends_at')
            ->limit(100)
            ->get();

        return response()->json(['data' => $challenges->map(fn (Challenge $c) => $this->present($c, $mine[$c->id] ?? null))->values()]);
    }

    public function show(Request $request, Challenge $challenge): JsonResponse
    {
        abort_unless(in_array($challenge->status, [ChallengeStatus::Active, ChallengeStatus::Ended], true), 404);
        abort_if($challenge->organization_id !== null
            && ! OrganizationMember::query()->where('user_id', $request->user()->id)->where('organization_id', $challenge->organization_id)->exists(), 404);
        $mine = ChallengeParticipant::query()->where('user_id', $request->user()->id)->where('challenge_id', $challenge->id)->first();
        $top = ChallengeParticipant::query()->where('challenge_id', $challenge->id)->with('user')->orderByDesc('progress')->limit(10)->get()
            ->filter(fn ($p) => $p->user?->leaderboard_visible)
            ->values()
            ->map(fn (ChallengeParticipant $p, $i) => ['rank' => $i + 1, 'name' => $p->user->publicName(), 'progress' => $p->progress, 'completed' => $p->completed_at !== null, 'is_me' => $p->user_id === $request->user()->id]);

        return response()->json(['data' => [...$this->present($challenge, $mine), 'top' => $top]]);
    }

    public function join(Request $request, Challenge $challenge, ChallengeService $service): JsonResponse
    {
        $participant = $service->join($request->user(), $challenge);

        return response()->json(['data' => $this->present($challenge->fresh(), $participant)], 201);
    }

    private function present(Challenge $c, ?ChallengeParticipant $p): array
    {
        return [
            'id' => $c->public_id,
            'title' => $c->title,
            'description' => $c->description,
            'image_url' => $c->image_path ? Storage::disk('public')->url($c->image_path) : null,
            'type' => $c->type->value,
            'type_label' => $c->type->label(),
            'metric' => $c->metric,
            'organization' => $c->organization_id !== null,
            'target' => $c->target_value,
            'reward_points' => $c->reward_points,
            'reward_xp' => $c->reward_xp,
            'sponsored' => $c->sponsor_id !== null,
            'starts_at' => $c->starts_at->toIso8601String(),
            'ends_at' => $c->ends_at->toIso8601String(),
            'participants' => $c->participants_count,
            'status' => $c->ends_at->isPast() ? 'ended' : ($c->starts_at->isFuture() ? 'upcoming' : 'running'),
            'joinable' => $p === null && $c->isJoinable(),
            'joined' => $p !== null,
            'progress' => $p?->progress ?? 0,
            'completed_at' => $p?->completed_at?->toIso8601String(),
        ];
    }
}
