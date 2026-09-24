<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Activity\HomeSummary;
use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\DailyActivityResource;
use App\Http\Resources\V1\WalkingSessionResource;
use App\Models\ActivitySample;
use App\Models\DailyActivity;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function home(Request $request, HomeSummary $home): JsonResponse
    {
        return response()->json(['data' => $home->for($request->user())]);
    }

    /** A day's timeline: the sessions plus steps per local hour. Defaults to today. */
    public function day(Request $request): JsonResponse
    {
        $request->validate(['date' => ['sometimes', 'date_format:Y-m-d']]);
        $user = $request->user();
        $date = $request->input('date', CarbonImmutable::now($user->timezone)->toDateString());

        $sessions = $user->walkingSessions()
            ->where('local_date', $date)
            ->where('status', '!=', SessionStatus::Rejected)
            ->orderBy('started_at')
            ->get();

        $hourly = array_fill(0, 24, 0);
        ActivitySample::query()
            ->whereIn('walking_session_id', $sessions->pluck('id'))
            ->get(['started_at', 'steps'])
            ->each(function (ActivitySample $s) use (&$hourly, $user) {
                $hourly[(int) $s->started_at->setTimezone($user->timezone)->format('G')] += $s->steps;
            });

        $daily = DailyActivity::query()->where('user_id', $user->id)->where('local_date', $date)->first();

        return response()->json(['data' => [
            'date' => $date,
            'summary' => $daily ? DailyActivityResource::make($daily) : null,
            'hourly' => $hourly,
            'sessions' => WalkingSessionResource::collection($sessions),
        ]]);
    }

    /** Daily rows for a range (max 92 days), with explicit zero days so charts don't need to fill gaps. */
    public function daily(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = CarbonImmutable::now($user->timezone)->toDateString();
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:'.$today],
        ]);

        $from = CarbonImmutable::parse($data['from']);
        $to = CarbonImmutable::parse($data['to']);
        if ($from->diffInDays($to) > 92) {
            $from = $to->subDays(92);
        }

        $rows = DailyActivity::query()
            ->where('user_id', $user->id)
            ->whereBetween('local_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn (DailyActivity $d) => $d->local_date->toDateString());

        $goal = $user->profile?->daily_step_goal ?? 7500;
        $days = [];
        foreach (CarbonPeriod::create($from, $to) as $day) {
            $key = $day->toDateString();
            $days[] = isset($rows[$key])
                ? DailyActivityResource::make($rows[$key])->resolve()
                : ['date' => $key, 'steps' => 0, 'verified_steps' => 0, 'goal' => $goal, 'goal_reached' => false, 'distance_m' => 0, 'calories_kcal' => 0, 'active_minutes' => 0, 'points' => 0];
        }

        return response()->json(['data' => $days]);
    }
}
