<?php

namespace App\Http\Resources\V1;

use App\Models\DailyActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DailyActivity */
class DailyActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->local_date->toDateString(),
            'steps' => $this->raw_steps,
            'verified_steps' => $this->verified_steps,
            'goal' => $this->goal_steps,
            'goal_reached' => $this->goal_reached_at !== null,
            'distance_m' => $this->distance_m,
            'calories_kcal' => $this->calories_kcal,
            'active_minutes' => $this->active_minutes,
            'points' => $this->points_earned,
        ];
    }
}
