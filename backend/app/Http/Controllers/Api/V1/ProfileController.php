<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\User\ProfileService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateNotificationPreferencesRequest;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Requests\Api\V1\UpdateSettingsRequest;
use App\Http\Resources\V1\MeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $profiles) {}

    public function show(Request $request): MeResource
    {
        return MeResource::make($request->user()->load('profile'));
    }

    public function update(UpdateProfileRequest $request): MeResource
    {
        return MeResource::make($this->profiles->update($request->user(), $request->validated())->load('profile'));
    }

    public function updateSettings(UpdateSettingsRequest $request): MeResource
    {
        return MeResource::make($this->profiles->updateSettings($request->user(), $request->validated())->load('profile'));
    }

    public function updateAvatar(Request $request): MeResource
    {
        $request->validate(['avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=64,min_height=64,max_width=2048,max_height=2048']]);

        return MeResource::make($this->profiles->updateAvatar($request->user(), $request->file('avatar'))->load('profile'));
    }

    public function notificationPreferences(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->profiles->notificationPreferences($request->user())]);
    }

    public function updateNotificationPreferences(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->profiles->updateNotificationPreferences($request->user(), $request->validated('preferences'))]);
    }

    public function requestDeletion(Request $request): JsonResponse
    {
        $deletion = $this->profiles->requestDeletion($request->user());

        return response()->json(['data' => [
            'status' => $deletion->status,
            'scheduled_for' => $deletion->scheduled_for->toIso8601String(),
        ]], 202);
    }

    public function cancelDeletion(Request $request): JsonResponse
    {
        $this->profiles->cancelDeletion($request->user());

        return response()->json(['data' => ['status' => 'cancelled']]);
    }
}
