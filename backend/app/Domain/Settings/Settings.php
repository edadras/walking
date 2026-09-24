<?php

namespace App\Domain\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Runtime business settings. DB rows (admin-editable) override the defaults in
 * config/walk.php. The whole set is cached as one small map.
 */
class Settings
{
    private const CACHE_KEY = 'settings:all:v1';

    /** @var array<string, mixed>|null */
    private ?array $resolved = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->all());

        $defaults = array_map(fn (array $d) => $d['value'], config('walk.settings', []));

        return $this->resolved = array_replace($defaults, $stored);
    }

    /** Settings flagged as public (safe to ship to the app via /config). */
    public function public(): array
    {
        $public = [];
        foreach (config('walk.settings', []) as $key => $definition) {
            if ($definition['public'] ?? false) {
                $public[$key] = $this->get($key);
            }
        }

        return $public;
    }

    public function set(string $key, mixed $value, ?int $adminId = null): void
    {
        $definition = config("walk.settings.$key");

        Setting::query()->updateOrCreate(['key' => $key], [
            'value' => $value,
            'group' => $definition['group'] ?? explode('.', $key)[0],
            'description' => $definition['description'] ?? null,
            'is_public' => $definition['public'] ?? false,
            'updated_by' => $adminId,
        ]);

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->resolved = null;
    }
}
