<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Quest\QuestService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestController extends Controller
{
    public function index(Request $request, QuestService $quests): JsonResponse
    {
        return response()->json(['data' => $quests->list($request->user())]);
    }

    public function claim(Request $request, string $quest, QuestService $quests): JsonResponse
    {
        $quests->claim($request->user(), $quest);

        return response()->json(['data' => $quests->list($request->user())]);
    }
}
