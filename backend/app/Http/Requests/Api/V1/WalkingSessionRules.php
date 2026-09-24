<?php

namespace App\Http\Requests\Api\V1;

/** Shared validation rules for one walking session (single and batch endpoints). */
final class WalkingSessionRules
{
    /** @return array<string, list<mixed>> */
    public static function rules(string $prefix = ''): array
    {
        $p = $prefix;

        return [
            "{$p}client_session_id" => ['required', 'uuid'],
            "{$p}sequence" => ['required', 'integer', 'min:1'],
            "{$p}kind" => ['required', 'in:passive,active'],
            "{$p}source" => ['sometimes', 'in:step_counter,health_connect'],
            "{$p}started_at" => ['required', 'date'],
            "{$p}ended_at" => ['required', 'date'],
            "{$p}raw_steps" => ['required', 'integer', 'min:0', 'max:200000'],
            "{$p}buckets" => ['required', 'array', 'min:1', 'max:400'],
            "{$p}buckets.*.started_at" => ['required', 'date'],
            "{$p}buckets.*.duration_s" => ['required', 'integer', 'min:1', 'max:3600'],
            "{$p}buckets.*.steps" => ['required', 'integer', 'min:0', 'max:21600'],
            "{$p}buckets.*.detector_steps" => ['nullable', 'integer', 'min:0', 'max:21600'],
            "{$p}buckets.*.accel_std" => ['nullable', 'numeric', 'min:0', 'max:999'],
            "{$p}buckets.*.accel_peak_hz" => ['nullable', 'numeric', 'min:0', 'max:99'],
            "{$p}buckets.*.activity_type" => ['nullable', 'in:walking,running,vehicle,bicycle,still,unknown'],
            "{$p}buckets.*.activity_confidence" => ['nullable', 'integer', 'min:0', 'max:100'],
            "{$p}buckets.*.speed_mps" => ['nullable', 'numeric', 'min:0', 'max:999'],
            "{$p}buckets.*.gps_accuracy_m" => ['nullable', 'integer', 'min:0', 'max:65000'],
            "{$p}gps" => ['nullable', 'array'],
            "{$p}gps.points" => ['required_with:'.$p.'gps', 'integer', 'min:0', 'max:100000'],
            "{$p}gps.distance_m" => ['required_with:'.$p.'gps', 'numeric', 'min:0', 'max:500000'],
            "{$p}gps.avg_accuracy_m" => ['nullable', 'numeric', 'min:0', 'max:10000'],
            "{$p}gps.max_speed_mps" => ['nullable', 'numeric', 'min:0', 'max:1000'],
            "{$p}gps.jumps" => ['nullable', 'integer', 'min:0', 'max:100000'],
            "{$p}gps.mock_detected" => ['nullable', 'boolean'],
            "{$p}motion" => ['nullable', 'array'],
            "{$p}motion.mock_location" => ['nullable', 'boolean'],
            "{$p}motion.transitions" => ['nullable', 'array', 'max:200'],
            "{$p}motion.transitions.*.type" => ['required', 'in:walking,running,vehicle,bicycle,still'],
            "{$p}motion.transitions.*.at" => ['required', 'date'],
        ];
    }

    /** Only the keys defined above (drops anything else the client sent). */
    public static function only(array $input): array
    {
        $keys = ['client_session_id', 'sequence', 'kind', 'source', 'started_at', 'ended_at', 'raw_steps', 'buckets', 'gps', 'motion'];

        return array_intersect_key($input, array_flip($keys));
    }
}
