<?php

namespace App\Domain\Weather;

use Carbon\CarbonImmutable;

/**
 * Turns a weather report into short, actionable advice for a walk and a 0–100
 * "walk index". Thresholds follow common public-health guidance (WHO UV index
 * bands, US AQI bands, apparent temperature for heat/cold stress).
 */
class WalkAdvice
{
    /**
     * @param  array<string, mixed>  $r  normalized report (without advice)
     * @return array{items: list<array{key: string, level: string, text: string}>, index: array{score: int, label: string}}
     */
    public static function for(array $r, CarbonImmutable $now): array
    {
        $c = $r['current'];
        $items = [];
        $penalty = 0;
        $add = function (string $key, string $level, string $text, int $cost) use (&$items, &$penalty) {
            $items[] = compact('key', 'level', 'text');
            $penalty += $cost;
        };

        $feels = (float) $c['feels_like_c'];
        match (true) {
            $feels >= 40 => $add('heat', 'danger', 'گرمای خطرناک؛ پیاده‌روی را به صبح زود یا بعد از غروب بگذارید.', 45),
            $feels >= 33 => $add('heat', 'warn', 'هوا گرم است؛ آب همراه داشته باشید و در سایه راه بروید.', 20),
            $feels <= -5 => $add('cold', 'danger', 'سرمای شدید؛ دست و صورت را بپوشانید و مسیر کوتاه‌تری بروید.', 35),
            $feels <= 3 => $add('cold', 'warn', 'هوا سرد است؛ لباس گرم و لایه‌ای بپوشید.', 12),
            default => null,
        };

        if ($c['temp_c'] >= 26 && $c['humidity'] >= 70) {
            $add('muggy', 'warn', 'هوا شرجی است؛ آرام‌تر راه بروید و بیشتر آب بنوشید.', 12);
        } elseif ($c['humidity'] <= 20 && $c['temp_c'] >= 20) {
            $add('dry', 'info', 'هوا خشک است؛ کمی بیشتر از همیشه آب بنوشید.', 0);
        }

        $uv = (float) $c['uv'];
        if ($c['is_day'] && $uv >= 8) {
            $add('uv', 'danger', 'اشعه فرابنفش بسیار شدید است؛ کلاه، عینک آفتابی و ضدآفتاب لازم است.', 20);
        } elseif ($c['is_day'] && $uv >= 6) {
            $add('uv', 'warn', 'اشعه فرابنفش زیاد است؛ ضدآفتاب بزنید و کلاه بگذارید.', 10);
        }

        if ($r['air'] !== null) {
            $aqi = (int) $r['air']['us_aqi'];
            match (true) {
                $aqi > 200 => $add('air', 'danger', 'هوا بسیار آلوده است؛ امروز در فضای بسته راه بروید.', 50),
                $aqi > 150 => $add('air', 'danger', 'هوا ناسالم است؛ پیاده‌روی بیرون را کوتاه کنید.', 35),
                $aqi > 100 => $add('air', 'warn', 'برای کودکان، سالمندان و بیماران تنفسی هوا ناسالم است.', 15),
                default => null,
            };
        }

        $gusts = (float) $c['wind_gusts_kmh'];
        if ($gusts >= 60) {
            $add('wind', 'danger', 'تندباد؛ مراقب اشیای در حال سقوط و درختان باشید.', 30);
        } elseif ($gusts >= 40 || (float) $c['wind_kmh'] >= 30) {
            $add('wind', 'warn', 'باد نسبتاً شدید است.', 8);
        }

        $next3 = array_slice($r['hourly'], 0, 3);
        $rainSoon = max(array_map(fn ($h) => (int) $h['precip_prob'], $next3 ?: [['precip_prob' => 0]]));
        if (in_array($c['icon'], ['thunder'], true)) {
            $add('storm', 'danger', 'رعدوبرق؛ تا پایان طوفان بیرون نروید.', 60);
        } elseif (in_array($c['icon'], ['rain', 'snow'], true)) {
            $add('rain', 'warn', 'در حال بارش است؛ کفش مناسب و چتر همراه داشته باشید.', 20);
        } elseif ($rainSoon >= 60) {
            $add('rain', 'info', "احتمال بارش در سه ساعت آینده {$rainSoon}٪ است؛ چتر بردارید.", 5);
        }

        $sunset = $r['sun']['sunset'] ? CarbonImmutable::parse($r['sun']['sunset']) : null;
        if (! $c['is_day']) {
            $add('dark', 'info', 'هوا تاریک است؛ مسیر روشن انتخاب کنید و لباس روشن بپوشید.', 5);
        } elseif ($sunset !== null && $now->lt($sunset) && $now->diffInMinutes($sunset) <= 60) {
            $add('sunset', 'info', 'کمتر از یک ساعت تا غروب مانده؛ اگر دیر برمی‌گردید چراغ همراه داشته باشید.', 0);
        }

        $score = max(0, 100 - $penalty);
        if ($items === []) {
            $items[] = ['key' => 'great', 'level' => 'good', 'text' => 'هوا برای پیاده‌روی عالی است!'];
        }
        $order = ['danger' => 0, 'warn' => 1, 'info' => 2, 'good' => 3];
        usort($items, fn ($a, $b) => $order[$a['level']] <=> $order[$b['level']]);

        return ['items' => $items, 'index' => ['score' => $score, 'label' => match (true) {
            $score >= 80 => 'عالی',
            $score >= 60 => 'خوب',
            $score >= 40 => 'متوسط',
            default => 'نامناسب',
        }]];
    }
}
