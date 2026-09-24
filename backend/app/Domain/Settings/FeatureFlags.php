<?php

namespace App\Domain\Settings;

use App\Models\FeatureFlag;
use Illuminate\Support\Facades\Cache;

/**
 * Feature flags that can switch capabilities off without an app release.
 * Rollout is deterministic per user so a user doesn't flip between states.
 */
class FeatureFlags
{
    private const CACHE_KEY = 'feature_flags:all:v1';

    /** @var array<string, array<string, mixed>>|null */
    private ?array $resolved = null;

    public function enabled(string $key, ?int $userId = null, ?string $appVersion = null, ?string $platform = null): bool
    {
        $flag = $this->all()[$key] ?? null;
        if ($flag === null || ! $flag['is_enabled']) {
            return false;
        }

        if ($platform !== null && $flag['platforms'] && ! in_array($platform, explode(',', $flag['platforms']), true)) {
            return false;
        }

        if ($appVersion !== null && $flag['min_app_version'] && version_compare($appVersion, $flag['min_app_version'], '<')) {
            return false;
        }

        $percent = (int) $flag['rollout_percent'];
        if ($percent >= 100) {
            return true;
        }
        if ($userId === null || $percent <= 0) {
            return false;
        }

        return (crc32($key.':'.$userId) % 100) < $percent;
    }

    /** @return array<string, bool> resolved for a specific client */
    public function forClient(?int $userId, ?string $appVersion, ?string $platform): array
    {
        $result = [];
        foreach (array_keys($this->all()) as $key) {
            $result[$key] = $this->enabled($key, $userId, $appVersion, $platform);
        }

        return $result;
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => FeatureFlag::query()
            ->get(['key', 'is_enabled', 'rollout_percent', 'min_app_version', 'platforms'])
            ->keyBy('key')
            ->map(fn (FeatureFlag $f) => $f->only(['is_enabled', 'rollout_percent', 'min_app_version', 'platforms']))
            ->all());

        $defaults = [];
        foreach (config('walk.feature_flags', []) as $key => $definition) {
            $defaults[$key] = [
                'is_enabled' => $definition['enabled'],
                'rollout_percent' => 100,
                'min_app_version' => null,
                'platforms' => null,
            ];
        }

        return $this->resolved = array_replace($defaults, $stored);
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->resolved = null;
    }
}
