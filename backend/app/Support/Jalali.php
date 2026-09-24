<?php

namespace App\Support;

use DateTimeInterface;

/** Gregorian ↔ Jalali (Solar Hijri) conversion (standard arithmetic algorithm). */
final class Jalali
{
    private const MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

    /** @return array{0:int,1:int,2:int} [year, month, day] */
    public static function fromGregorian(int $gy, int $gm, int $gd): array
    {
        $gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $gdm[$gm - 1];
        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        $jm = $days < 186 ? 1 + intdiv($days, 31) : 7 + intdiv($days - 186, 30);
        $jd = 1 + ($days < 186 ? $days % 31 : ($days - 186) % 30);

        return [$jy, $jm, $jd];
    }

    /** @return array{0:int,1:int,2:int} [year, month, day] */
    public static function toGregorian(int $jy, int $jm, int $jd): array
    {
        $jy += 1595;
        $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + intdiv(($jy % 33) + 3, 4) + $jd + ($jm < 7 ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
        $gy = 400 * intdiv($days, 146097);
        $days %= 146097;
        if ($days > 36524) {
            $gy += 100 * intdiv(--$days, 36524);
            $days %= 36524;
            if ($days >= 365) {
                $days++;
            }
        }
        $gy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $gy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        $gd = $days + 1;
        $leap = ($gy % 4 === 0 && $gy % 100 !== 0) || $gy % 400 === 0;
        $monthDays = [0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        for ($gm = 1; $gm <= 12 && $gd > $monthDays[$gm]; $gm++) {
            $gd -= $monthDays[$gm];
        }

        return [$gy, $gm, $gd];
    }

    /** First and last Gregorian dates (Y-m-d) of the Jalali month containing $date. */
    public static function monthRange(DateTimeInterface $date): array
    {
        [$y, $m] = self::of($date);
        [$sy, $sm, $sd] = self::toGregorian($y, $m, 1);
        [$ny, $nm] = $m === 12 ? [$y + 1, 1] : [$y, $m + 1];
        [$ey, $em, $ed] = self::toGregorian($ny, $nm, 1);
        $end = (new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $ey, $em, $ed)))->modify('-1 day');

        return [sprintf('%04d-%02d-%02d', $sy, $sm, $sd), $end->format('Y-m-d')];
    }

    /** @return array{0:int,1:int,2:int} */
    public static function of(DateTimeInterface $date): array
    {
        return self::fromGregorian((int) $date->format('Y'), (int) $date->format('n'), (int) $date->format('j'));
    }

    /** "1405-07" — used as the monthly leaderboard key. */
    public static function monthKey(DateTimeInterface $date): string
    {
        [$y, $m] = self::of($date);

        return sprintf('%04d-%02d', $y, $m);
    }

    public static function format(DateTimeInterface $date): string
    {
        [$y, $m, $d] = self::of($date);

        return Digits::toPersian($d).' '.self::MONTHS[$m - 1].' '.Digits::toPersian($y);
    }
}
