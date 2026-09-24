<?php

namespace App\Domain\Wallet;

use App\Models\Admin;
use App\Models\PointConversionRate;
use Illuminate\Support\Facades\Cache;

/**
 * Rial value of one point. Changing the rate appends a row; past transactions
 * keep the rate snapshot they were written with.
 */
class ConversionRate
{
    private const CACHE_KEY = 'conversion_rate:current';

    public function current(): int
    {
        return (int) Cache::remember(self::CACHE_KEY, 300, fn () => PointConversionRate::query()
            ->where('effective_from', '<=', now())
            ->latest('effective_from')
            ->latest('id')
            ->value('rial_per_point') ?? config('walk.default_rial_per_point'));
    }

    public function set(int $rialPerPoint, ?Admin $admin = null): PointConversionRate
    {
        $rate = PointConversionRate::query()->create([
            'rial_per_point' => $rialPerPoint,
            'effective_from' => now(),
            'created_by' => $admin?->id,
        ]);
        Cache::forget(self::CACHE_KEY);

        return $rate;
    }

    public function rialValue(int $points): int
    {
        return $points * $this->current();
    }
}
