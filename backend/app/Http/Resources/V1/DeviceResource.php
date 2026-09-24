<?php

namespace App\Http\Resources\V1;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/** @mixin Device */
class DeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $current = $request->attributes->get('device');

        return [
            'id' => $this->public_id,
            'platform' => $this->platform,
            'model' => trim(($this->manufacturer ?? '').' '.($this->model ?? '')) ?: null,
            'app_version' => $this->app_version,
            'last_seen_at' => ($seen = $this->pivot?->last_seen_at ?? $this->last_seen_at) ? Carbon::parse($seen)->toIso8601String() : null,
            'is_current' => $current instanceof Device && $current->is($this->resource),
        ];
    }
}
