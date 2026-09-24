<?php

namespace App\Domain\Gamification;

use App\Models\Level;
use App\Models\User;
use App\Models\XpTransaction;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * XP is a separate, non-monetary progress currency (levels). Awards are
 * idempotent per key; users.xp / users.level are caches of the XP log.
 */
class XpService
{
    /** @return bool true if newly awarded */
    public function award(User $user, int $amount, string $reason, string $key): bool
    {
        if ($amount <= 0) {
            return false;
        }

        try {
            DB::transaction(function () use ($user, $amount, $reason, $key) {
                XpTransaction::query()->create(['user_id' => $user->id, 'amount' => $amount, 'reason' => $reason, 'idempotency_key' => $key]);
                DB::table('users')->where('id', $user->id)->increment('xp', $amount);
            });
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        $user->refresh();
        $level = $this->levelFor($user->xp);
        if ($level !== $user->level) {
            $user->forceFill(['level' => $level])->save();
        }

        return true;
    }

    public function levelFor(int $xp): int
    {
        $level = 1;
        foreach ($this->levels() as $row) {
            if ($xp >= $row['min_xp']) {
                $level = max($level, $row['level']);
            }
        }

        return $level;
    }

    /** @return array{level:int, title:string, xp:int, current_min:int, next_min:?int} */
    public function progress(User $user): array
    {
        $levels = collect($this->levels())->keyBy('level');
        $current = $levels[$user->level] ?? ['min_xp' => 0, 'title' => ''];
        $next = $levels[$user->level + 1] ?? null;

        return [
            'level' => $user->level,
            'title' => $current['title'],
            'xp' => $user->xp,
            'current_min' => $current['min_xp'],
            'next_min' => $next['min_xp'] ?? null,
        ];
    }

    /** @return list<array{level:int, min_xp:int, title:string}> */
    private function levels(): array
    {
        return Cache::remember('levels:v1', 3600, fn () => Level::query()->orderBy('level')->get(['level', 'min_xp', 'title'])->map->only(['level', 'min_xp', 'title'])->all());
    }

    /** Level curve used by the seeder: grows ~quadratically so later levels take real effort. */
    public static function curve(int $maxLevel = 50): array
    {
        $titles = [1 => 'تازه‌کار', 5 => 'قدم‌زن', 10 => 'رهرو', 15 => 'پیاده‌رو', 20 => 'کوه‌پیما', 30 => 'جهانگرد', 40 => 'افسانه راه', 50 => 'استاد مسیر'];
        $rows = [];
        $title = $titles[1];
        for ($l = 1; $l <= $maxLevel; $l++) {
            $title = $titles[$l] ?? $title;
            $rows[] = ['level' => $l, 'min_xp' => (int) (50 * ($l - 1) ** 2 + 150 * ($l - 1)), 'title' => $title];
        }

        return $rows;
    }
}
