<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Social\SocialService;
use App\Http\Controllers\Controller;
use App\Models\FriendChallenge;
use App\Models\Friendship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialController extends Controller
{
    public function __construct(private readonly SocialService $social) {}

    public function friends(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->social->overview($request->user())]);
    }

    public function request(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:12', 'regex:/^[A-Za-z0-9]+$/']]);
        $this->social->request($request->user(), $data['code']);

        return response()->json(['data' => $this->social->overview($request->user())], 201);
    }

    public function accept(Request $request, Friendship $friendship): JsonResponse
    {
        $this->social->accept($request->user(), $friendship);

        return response()->json(['data' => $this->social->overview($request->user())]);
    }

    public function remove(Request $request, Friendship $friendship): JsonResponse
    {
        $this->social->remove($request->user(), $friendship);

        return response()->json(['data' => $this->social->overview($request->user())]);
    }

    public function challenges(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->social->challenges($request->user())]);
    }

    public function createChallenge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:60'],
            'days' => ['required', 'integer', 'in:3,7,14'],
            'friend_ids' => ['required', 'array', 'min:1', 'max:9'],
            'friend_ids.*' => ['string', 'size:26'],
        ]);
        $c = $this->social->createChallenge($request->user(), $data['title'], $data['days'], $data['friend_ids']);

        return response()->json(['data' => $this->social->present($request->user(), $c)], 201);
    }

    public function challenge(Request $request, FriendChallenge $challenge): JsonResponse
    {
        return response()->json(['data' => $this->social->present($request->user(), $challenge)]);
    }

    public function join(Request $request, FriendChallenge $challenge): JsonResponse
    {
        $this->social->respond($request->user(), $challenge, true);

        return response()->json(['data' => $this->social->present($request->user(), $challenge->fresh())]);
    }

    public function leave(Request $request, FriendChallenge $challenge): JsonResponse
    {
        $this->social->respond($request->user(), $challenge, false);

        return response()->json(['data' => $this->social->challenges($request->user())]);
    }
}
