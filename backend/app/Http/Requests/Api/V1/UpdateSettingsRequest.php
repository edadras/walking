<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Map\RouteMap;
use App\Domain\Settings\Settings;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function rules(): array
    {
        $settings = app(Settings::class);

        return [
            'daily_step_goal' => ['sometimes', 'integer', 'between:'.$settings->int('activity.min_daily_goal').','.$settings->int('activity.max_daily_goal')],
            'water_goal_ml' => ['sometimes', 'integer', 'between:500,6000'],
            'water_reminder_enabled' => ['sometimes', 'boolean'],
            'water_reminder_interval_min' => ['sometimes', 'integer', 'between:30,480'],
            'quiet_hours_start' => ['sometimes', 'nullable', 'date_format:H:i'],
            'quiet_hours_end' => ['sometimes', 'nullable', 'date_format:H:i'],
            'leaderboard_visible' => ['sometimes', 'boolean'],
            'share_route' => ['sometimes', 'boolean'],
            'route_color' => ['sometimes', 'in:'.implode(',', RouteMap::COLORS)],
        ];
    }
}
