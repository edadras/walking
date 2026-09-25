<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Proxies map tiles from a keyed provider. Signed-in users only (so the key
 * can't be borrowed through us), coordinates are validated, and tiles are
 * cached on disk and marked cacheable for the device and any edge cache.
 */
class MapTileController extends Controller
{
    public function __invoke(int $z, int $x, int $y): Response
    {
        $upstream = config('walk.map.upstream');
        abort_unless($upstream, 404);
        abort_unless($z >= 0 && $z <= 19 && $x >= 0 && $y >= 0 && $x < 2 ** $z && $y < 2 ** $z, 404);

        $days = config('walk.map.cache_days');
        $body = Cache::store('file')->remember("tile:{$z}:{$x}:{$y}", now()->addDays($days), function () use ($upstream, $z, $x, $y) {
            $response = Http::withHeaders(self::headers())->timeout(8)
                ->get(str_replace(['{z}', '{x}', '{y}'], [$z, $x, $y], $upstream));

            // Failures aren't cached (remember() skips null), so a provider hiccup heals itself.
            return $response->successful() ? base64_encode($response->body()) : null;
        });
        abort_if($body === null, 502);

        $bytes = base64_decode($body);

        return response($bytes, 200, [
            'Content-Type' => str_starts_with($bytes, "\x89PNG") ? 'image/png' : 'image/jpeg',
            'Cache-Control' => 'public, max-age='.($days * 86400),
        ]);
    }

    /** @return array<string, string> */
    public static function headers(): array
    {
        $headers = [];
        foreach (array_filter(array_map('trim', explode(';', (string) config('walk.map.upstream_headers')))) as $pair) {
            [$name, $value] = array_map('trim', explode(':', $pair, 2)) + [1 => ''];
            if ($name !== '') {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}
