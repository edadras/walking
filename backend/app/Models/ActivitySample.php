<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivitySample extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'duration_s' => 'integer',
            'steps' => 'integer',
            'detector_steps' => 'integer',
            'accel_std' => 'float',
            'accel_peak_hz' => 'float',
            'activity_confidence' => 'integer',
            'speed_mps' => 'float',
            'gps_accuracy_m' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WalkingSession::class, 'walking_session_id');
    }
}
