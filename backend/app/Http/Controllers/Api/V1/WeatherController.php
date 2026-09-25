<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Weather\WeatherService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeatherController extends Controller
{
    /** GET /weather?lat=&lng= — coordinates are snapped to a ~5 km grid and not stored. */
    public function __invoke(Request $request, WeatherService $weather): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $report = $weather->at((float) $data['lat'], (float) $data['lng']);
        abort_if($report === null, 503, 'اطلاعات آب‌وهوا فعلاً در دسترس نیست.');

        return response()->json(['data' => $report])->header('Cache-Control', 'private, max-age=600');
    }
}
