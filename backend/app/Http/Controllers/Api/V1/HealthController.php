<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Health\HealthSummary;
use App\Domain\Health\WaterService;
use App\Http\Controllers\Controller;
use App\Models\WaterLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HealthController extends Controller
{
    public function summary(Request $request, HealthSummary $health): JsonResponse
    {
        $request->validate(['range' => ['sometimes', 'in:week,month']]);

        return response()->json(['data' => $health->summary($request->user(), $request->input('range', 'week'))]);
    }

    public function weeklyReport(Request $request, HealthSummary $health): JsonResponse
    {
        return response()->json(['data' => $health->weeklyReport($request->user())]);
    }

    public function water(Request $request, WaterService $water): JsonResponse
    {
        $request->validate(['date' => ['sometimes', 'date_format:Y-m-d']]);

        return response()->json(['data' => $water->day($request->user(), $request->input('date'))]);
    }

    public function addWater(Request $request, WaterService $water): JsonResponse
    {
        $data = $request->validate(['amount_ml' => ['required', 'integer', 'between:50,2000']]);
        $water->add($request->user(), $data['amount_ml']);

        return response()->json(['data' => $water->day($request->user())], 201);
    }

    /** Only today's entries can be removed (mistaken taps), never history. */
    public function deleteWater(Request $request, WaterLog $log): Response
    {
        $user = $request->user();
        abort_unless($log->user_id === $user->id && $log->local_date->toDateString() === CarbonImmutable::now($user->timezone)->toDateString(), 404);
        $log->delete();

        return response()->noContent();
    }
}
