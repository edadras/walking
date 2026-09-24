<?php

namespace App\Models;

use App\Enums\LocationStatus;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Location extends Model
{
    use HasPublicId, SoftDeletes;

    protected $guarded = ['id', 'public_id', 'qr_secret'];

    protected $hidden = ['id', 'qr_secret'];

    /** Persian weekday keys used in opening_hours: {"sat": [["09:00","21:00"]], ...}. */
    public const DAYS = ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri'];

    protected static function booted(): void
    {
        static::creating(function (Location $l) {
            $l->qr_secret ??= Str::random(48);
        });
    }

    protected function casts(): array
    {
        return [
            'status' => LocationStatus::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_m' => 'integer',
            'opening_hours' => 'array',
            'qr_secret' => 'encrypted',
        ];
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class);
    }

    public function rotateQrSecret(): void
    {
        $this->forceFill(['qr_secret' => Str::random(48)])->save();
    }

    /** No hours configured = always open. Times are in Asia/Tehran (local business hours). */
    public function isOpenAt(CarbonInterface $at): bool
    {
        $hours = $this->opening_hours;
        if (empty($hours)) {
            return true;
        }
        $local = $at->copy()->setTimezone(config('walk.panel_timezone', 'Asia/Tehran'));
        $day = strtolower($local->format('D'));
        $hm = $local->format('H:i');
        foreach ($hours[$day] ?? [] as [$open, $close]) {
            if ($hm >= $open && $hm < $close) {
                return true;
            }
        }

        return false;
    }
}
