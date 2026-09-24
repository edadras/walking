<?php

namespace App\Http\Resources\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin User */
class MeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->profile;

        return [
            'id' => $this->public_id,
            'phone_masked' => $this->maskedPhone(),
            'display_name' => $this->display_name,
            'public_name' => $this->publicName(),
            'avatar_url' => $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null,
            'status' => $this->status->value,
            'level' => $this->level,
            'xp' => $this->xp,
            'referral_code' => $this->referral_code,
            'timezone' => $this->timezone,
            'joined_at' => $this->created_at?->toIso8601String(),
            'deletion_requested_at' => $this->deletion_requested_at?->toIso8601String(),
            'profile' => [
                'birth_year' => $profile?->birth_year,
                'gender' => $profile?->gender,
                'height_cm' => $profile?->height_cm,
                'weight_kg' => $profile?->weight_kg,
            ],
            'settings' => [
                'daily_step_goal' => $profile?->daily_step_goal,
                'water_goal_ml' => $profile?->water_goal_ml,
                'water_reminder_enabled' => (bool) $profile?->water_reminder_enabled,
                'water_reminder_interval_min' => $profile?->water_reminder_interval_min,
                'quiet_hours_start' => $profile?->quiet_hours_start ? substr($profile->quiet_hours_start, 0, 5) : null,
                'quiet_hours_end' => $profile?->quiet_hours_end ? substr($profile->quiet_hours_end, 0, 5) : null,
                'leaderboard_visible' => $this->leaderboard_visible,
            ],
            'profile_completed' => $this->display_name !== null && $profile?->weight_kg !== null,
        ];
    }
}
