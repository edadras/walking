<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * First-party product analytics. Event names are whitelisted and properties are
 * reduced to short scalar values; keys that could carry personal data are dropped.
 */
class AnalyticsController extends Controller
{
    public const EVENTS = [
        'app_open', 'walking_started', 'walking_completed', 'reward_received', 'challenge_joined',
        'coupon_claimed', 'coupon_used', 'ad_impression', 'ad_click', 'store_view', 'product_view',
        'purchase', 'sponsor_visit', 'permission_prompt', 'permission_result', 'onboarding_completed',
    ];

    private const BLOCKED_KEYS = ['phone', 'mobile', 'email', 'name', 'lat', 'lng', 'latitude', 'longitude', 'address', 'token', 'code'];

    public function store(Request $request): Response
    {
        $data = $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:50'],
            'events.*.name' => ['required', 'string', 'in:'.implode(',', self::EVENTS)],
            'events.*.occurred_at' => ['required', 'date'],
            'events.*.properties' => ['nullable', 'array', 'max:10'],
        ]);

        $now = CarbonImmutable::now();
        $user = $request->user();
        $device = $request->attributes->get('device');

        $rows = [];
        foreach ($data['events'] as $event) {
            $at = CarbonImmutable::parse($event['occurred_at'])->utc();
            if ($at->gt($now->addMinutes(5)) || $at->lt($now->subDays(7))) {
                continue;
            }
            $rows[] = [
                'name' => $event['name'],
                'user_id' => $user->id,
                'device_id' => $device?->id,
                'properties' => json_encode($this->sanitize($event['properties'] ?? []), JSON_UNESCAPED_UNICODE),
                'occurred_at' => $at->toDateTimeString(),
                'created_at' => $now->toDateTimeString(),
            ];
        }

        if ($rows !== []) {
            AnalyticsEvent::query()->insert($rows);
        }

        return response()->noContent();
    }

    /** @return array<string, scalar> */
    private function sanitize(array $properties): array
    {
        $clean = [];
        foreach ($properties as $key => $value) {
            $key = (string) $key;
            if (! preg_match('/^[a-z_]{1,32}$/', $key) || in_array($key, self::BLOCKED_KEYS, true)) {
                continue;
            }
            if (is_bool($value) || is_int($value) || is_float($value)) {
                $clean[$key] = $value;
            } elseif (is_string($value)) {
                $clean[$key] = mb_substr($value, 0, 64);
            }
        }

        return $clean;
    }
}
