<?php

namespace App\Domain\Map;

use App\Domain\Settings\Settings;
use App\Enums\SessionKind;
use App\Enums\SessionStatus;
use App\Models\RouteTrack;
use App\Models\User;
use App\Models\WalkingSession;
use Carbon\CarbonImmutable;

/**
 * The public map of walks: each sharing user's routes in their own colour, so people can
 * "draw" with their feet. Built for privacy first:
 *
 *  - opt-in per user (off by default); turning it off removes their lines at once
 *  - no names, avatars or ids are ever published, only a colour and a line
 *  - a line appears only after the walk is over and verified (never a live position)
 *  - the first and last stretch of each walk is cut off, so homes and workplaces don't show
 *  - a line is visible for 24 h after its last point, then deleted from the database
 */
class RouteMap
{
    public const COLORS = ['#E5484D', '#F76B15', '#FFB224', '#30A46C', '#12A594', '#0090FF', '#3E63DD', '#8E4EC6', '#D6409F', '#AB4ABA', '#46A758', '#E54666'];

    private const MAX_SPEED_MPS = 15.0; // above this between two fixes it's a GPS jump or a vehicle

    public function __construct(private readonly Settings $settings) {}

    /**
     * @param  list<array{0: float, 1: float, 2: int}>  $raw  [lat, lng, epoch ms]
     */
    public function submit(User $user, array $raw): ?RouteTrack
    {
        if (! $user->share_route) {
            return null; // never stored without consent
        }
        usort($raw, fn ($a, $b) => $a[2] <=> $b[2]);
        $clean = $this->dropJumps($raw);
        $trimmed = $this->trimEnds($clean, (float) $this->settings->int('map.privacy_trim_m'));
        if (count($trimmed) < 2) {
            return null;
        }
        $line = self::simplify(array_map(fn ($p) => [round((float) $p[0], 6), round((float) $p[1], 6)], $trimmed), 4.0);
        $lats = array_column($line, 0);
        $lngs = array_column($line, 1);
        $last = CarbonImmutable::createFromTimestampMs((int) end($clean)[2]);

        $track = RouteTrack::query()->create([
            'user_id' => $user->id,
            'color' => $user->routeColor(),
            'points' => $line,
            'point_count' => count($line),
            'min_lat' => min($lats), 'max_lat' => max($lats),
            'min_lng' => min($lngs), 'max_lng' => max($lngs),
            'first_point_at' => CarbonImmutable::createFromTimestampMs((int) $clean[0][2]),
            'last_point_at' => $last,
            'visible_until' => $last->addHours($this->settings->int('map.visible_hours')),
            'published' => false,
        ]);

        return $this->settle($track);
    }

    /** Publishes a line once the walk it was recorded on is verified; drops it if rejected. */
    public function settle(RouteTrack $track): ?RouteTrack
    {
        if ($track->published) {
            return $track;
        }
        $session = WalkingSession::query()
            ->where('user_id', $track->user_id)
            ->where('kind', SessionKind::Active)
            ->where('started_at', '<=', $track->first_point_at->copy()->addMinutes(2))
            ->where('ended_at', '>=', $track->last_point_at->copy()->subMinutes(2))
            ->orderByDesc('id')
            ->first();
        if ($session === null) {
            return $track;
        }
        if ($session->status === SessionStatus::Rejected) {
            $track->delete();

            return null;
        }
        $track->walking_session_id = $session->id;
        $track->published = in_array($session->status, [SessionStatus::Verified, SessionStatus::PartiallyVerified], true);
        $track->save();

        return $track;
    }

    public function settleForSession(WalkingSession $session): void
    {
        RouteTrack::query()->where('user_id', $session->user_id)->where('published', false)
            ->where('first_point_at', '>=', $session->started_at->copy()->subMinutes(2))
            ->where('last_point_at', '<=', $session->ended_at->copy()->addMinutes(2))
            ->get()->each(fn (RouteTrack $t) => $this->settle($t));
    }

    /**
     * Lines visible now inside a bounding box. Anonymous: colour + points + time left.
     *
     * @return list<array{color: string, points: list<array{0: float, 1: float}>, expires_in: int}>
     */
    public function visible(float $minLat, float $minLng, float $maxLat, float $maxLng, int $limit = 800): array
    {
        $now = now();

        return RouteTrack::query()
            ->where('published', true)->where('visible_until', '>', $now)
            ->where('max_lat', '>=', $minLat)->where('min_lat', '<=', $maxLat)
            ->where('max_lng', '>=', $minLng)->where('min_lng', '<=', $maxLng)
            ->orderByDesc('last_point_at')->limit($limit)
            ->get(['color', 'points', 'visible_until'])
            ->map(fn (RouteTrack $t) => ['color' => $t->color, 'points' => $t->points, 'expires_in' => (int) $now->diffInSeconds($t->visible_until)])
            ->all();
    }

    /** Expired lines, and lines whose walk never arrived, are deleted (not just hidden). */
    public function prune(): int
    {
        return RouteTrack::query()->where('visible_until', '<', now())
            ->orWhere(fn ($q) => $q->where('published', false)->where('created_at', '<', now()->subDays($this->settings->int('activity.offline_max_age_days') + 1)))
            ->delete();
    }

    /** @param list<array> $pts */
    private function dropJumps(array $pts): array
    {
        $out = [];
        foreach ($pts as $p) {
            $prev = end($out);
            if ($prev !== false) {
                $seconds = max(0.001, ($p[2] - $prev[2]) / 1000);
                if (self::meters($prev, $p) / $seconds > self::MAX_SPEED_MPS) {
                    continue;
                }
            }
            $out[] = $p;
        }

        return $out;
    }

    /** Removes the first and last $meters of the line (privacy zone around start and finish). */
    private function trimEnds(array $pts, float $meters): array
    {
        $cut = function (array $line) use ($meters): array {
            $walked = 0.0;
            foreach ($line as $i => $p) {
                if ($i > 0) {
                    $walked += self::meters($line[$i - 1], $p);
                }
                if ($walked >= $meters) {
                    return array_slice($line, $i);
                }
            }

            return [];
        };

        return array_reverse($cut(array_reverse($cut($pts))));
    }

    public static function meters(array $a, array $b): float
    {
        $r = 6371000.0;
        $dLat = deg2rad($b[0] - $a[0]);
        $dLng = deg2rad($b[1] - $a[1]);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($a[0])) * cos(deg2rad($b[0])) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($h)));
    }

    /**
     * Douglas–Peucker on a local flat projection (fine at walking scale).
     *
     * @param  list<array{0: float, 1: float}>  $pts
     * @return list<array{0: float, 1: float}>
     */
    public static function simplify(array $pts, float $toleranceM): array
    {
        if (count($pts) < 3) {
            return $pts;
        }
        $lat0 = deg2rad($pts[0][0]);
        $xy = array_map(fn ($p) => [deg2rad($p[1]) * cos($lat0) * 6371000, deg2rad($p[0]) * 6371000], $pts);
        $keep = array_fill(0, count($pts), false);
        $keep[0] = $keep[count($pts) - 1] = true;
        $stack = [[0, count($pts) - 1]];
        while ($stack !== []) {
            [$s, $e] = array_pop($stack);
            $max = 0.0;
            $idx = -1;
            for ($i = $s + 1; $i < $e; $i++) {
                $d = self::segmentDistance($xy[$i], $xy[$s], $xy[$e]);
                if ($d > $max) {
                    $max = $d;
                    $idx = $i;
                }
            }
            if ($idx !== -1 && $max > $toleranceM) {
                $keep[$idx] = true;
                $stack[] = [$s, $idx];
                $stack[] = [$idx, $e];
            }
        }

        return array_values(array_filter($pts, fn ($i) => $keep[$i], ARRAY_FILTER_USE_KEY));
    }

    private static function segmentDistance(array $p, array $a, array $b): float
    {
        [$dx, $dy] = [$b[0] - $a[0], $b[1] - $a[1]];
        $len2 = $dx * $dx + $dy * $dy;
        $t = $len2 > 0 ? max(0, min(1, (($p[0] - $a[0]) * $dx + ($p[1] - $a[1]) * $dy) / $len2)) : 0;

        return hypot($p[0] - ($a[0] + $t * $dx), $p[1] - ($a[1] + $t * $dy));
    }
}
