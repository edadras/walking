<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Activity\Actions\SubmitWalkingSession;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SubmitWalkingSessionBatchRequest;
use App\Http\Requests\Api\V1\SubmitWalkingSessionRequest;
use App\Http\Requests\Api\V1\WalkingSessionRules;
use App\Http\Resources\V1\WalkingSessionResource;
use App\Models\WalkingSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;

class WalkingSessionController extends Controller
{
    public function __construct(private readonly SubmitWalkingSession $submit) {}

    public function store(SubmitWalkingSessionRequest $request): JsonResponse
    {
        [$session, $created] = $this->submit->handle(
            $request->user(),
            $request->attributes->get('device'),
            WalkingSessionRules::only($request->validated()),
        );

        return WalkingSessionResource::make($session)
            ->additional(['meta' => ['duplicate' => ! $created]])
            ->response()
            ->setStatusCode($created ? 202 : 200);
    }

    /**
     * Offline backlog: items are applied in sequence order, each independently.
     * The response reports a per-item outcome so the client can drop what's done.
     */
    public function batch(SubmitWalkingSessionBatchRequest $request): JsonResponse
    {
        $items = collect($request->input('sessions'))->sortBy(fn ($s) => (int) ($s['sequence'] ?? 0))->values();
        $results = [];

        foreach ($items as $item) {
            $id = $item['client_session_id'];
            $validator = Validator::make($item, WalkingSessionRules::rules());
            if ($validator->fails()) {
                $results[] = ['client_session_id' => $id, 'status' => 'rejected', 'error' => ['code' => 'validation_failed', 'message' => $validator->errors()->first()]];

                continue;
            }

            try {
                [$session, $created] = $this->submit->handle($request->user(), $request->attributes->get('device'), WalkingSessionRules::only($validator->validated()));
                $results[] = ['client_session_id' => $id, 'status' => $created ? 'accepted' : 'duplicate', 'session_id' => $session->public_id];
            } catch (ApiException $e) {
                $results[] = ['client_session_id' => $id, 'status' => 'rejected', 'error' => ['code' => $e->errorCode, 'message' => $e->getMessage()]];
            }
        }

        return response()->json(['data' => $results]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['per_page' => ['sometimes', 'integer', 'between:1,50'], 'date' => ['sometimes', 'date_format:Y-m-d']]);

        $sessions = WalkingSession::query()
            ->where('user_id', $request->user()->id)
            ->when($request->input('date'), fn ($q, $d) => $q->where('local_date', $d))
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->cursorPaginate($request->integer('per_page', 20));

        return WalkingSessionResource::collection($sessions);
    }

    public function show(Request $request, WalkingSession $session): WalkingSessionResource
    {
        abort_unless($session->user_id === $request->user()->id, 404);

        return WalkingSessionResource::make($session->load('samples'));
    }
}
