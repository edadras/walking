<?php

namespace App\Http\Resources\V1;

use App\Models\WalkingSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WalkingSession */
class WalkingSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'client_session_id' => $this->client_session_id,
            'kind' => $this->kind->value,
            'started_at' => $this->started_at->toIso8601String(),
            'ended_at' => $this->ended_at->toIso8601String(),
            'local_date' => $this->local_date->toDateString(),
            'steps' => $this->raw_steps,
            'verified_steps' => $this->verified_steps,
            'distance_m' => $this->distance_m,
            'duration_s' => $this->duration_s,
            'active_duration_s' => $this->active_duration_s,
            'calories_kcal' => $this->calories_kcal,
            'activity_type' => $this->activity_type->value,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'reward_status' => $this->reward_status->value,
            'confidence_score' => $this->confidence_score,
            'samples' => $this->whenLoaded('samples', fn () => $this->samples->map(fn ($s) => [
                'started_at' => $s->started_at->toIso8601String(),
                'duration_s' => $s->duration_s,
                'steps' => $s->steps,
            ])),
        ];
    }
}
