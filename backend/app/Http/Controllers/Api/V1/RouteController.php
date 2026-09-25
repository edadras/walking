<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Map\RouteMap;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RouteController extends Controller
{
    public function __construct(private readonly RouteMap $map) {}

    /** POST /routes — the line of a finished walk; stored only if the user shares routes. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'points' => ['required', 'array', 'min:2', 'max:5000'],
            'points.*' => ['array', 'size:3'],
            'points.*.0' => ['numeric', 'between:-90,90'],
            'points.*.1' => ['numeric', 'between:-180,180'],
            'points.*.2' => ['integer', 'min:1500000000000'],
        ]);
        $track = $this->map->submit($request->user(), $data['points']);

        return response()->json(['data' => [
            'stored' => $track !== null,
            'published' => (bool) $track?->published,
            'visible_until' => $track?->visible_until?->toIso8601String(),
        ]], $track === null ? 200 : 201);
    }

    /** GET /public/map/tracks?bbox=minLng,minLat,maxLng,maxLat — anonymous lines, no identities. */
    public function tracks(Request $request): JsonResponse
    {
        $request->validate(['bbox' => ['required', 'string', 'regex:/^-?\d+(\.\d+)?(,-?\d+(\.\d+)?){3}$/']]);
        [$minLng, $minLat, $maxLng, $maxLat] = array_map('floatval', explode(',', $request->string('bbox')));
        abort_if($maxLat <= $minLat || $maxLng <= $minLng || $maxLat - $minLat > 5 || $maxLng - $minLng > 5, 422, 'محدوده نقشه معتبر نیست.');

        // Snapped to ~1 km so nearby viewers share a cache entry.
        $snap = fn (float $v, bool $up) => ($up ? ceil($v * 100) : floor($v * 100)) / 100;
        $key = sprintf('map:tracks:%.2f:%.2f:%.2f:%.2f', $snap($minLat, false), $snap($minLng, false), $snap($maxLat, true), $snap($maxLng, true));
        $tracks = Cache::remember($key, 60, fn () => $this->map->visible($snap($minLat, false), $snap($minLng, false), $snap($maxLat, true), $snap($maxLng, true)));

        return response()->json(['data' => $tracks])->header('Cache-Control', 'public, max-age=60');
    }
}
