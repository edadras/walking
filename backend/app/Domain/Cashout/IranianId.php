<?php

namespace App\Domain\Cashout;

use App\Support\Digits;

/** Checks for Iranian national codes and Sheba (IR IBAN) numbers. */
final class IranianId
{
    /** کد ملی: 10 digits, check digit = weighted sum rule; repeated digits are invalid. */
    public static function nationalCode(string $value): ?string
    {
        $code = Digits::toLatin(preg_replace('/\D/u', '', Digits::toLatin($value)) ?? '');
        if (! preg_match('/^\d{10}$/', $code) || preg_match('/^(\d)\1{9}$/', $code)) {
            return null;
        }
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $code[$i] * (10 - $i);
        }
        $r = $sum % 11;
        $check = (int) $code[9];

        return ($r < 2 && $check === $r) || ($r >= 2 && $check === 11 - $r) ? $code : null;
    }

    /** Normalised "IR" + 24 digits when the ISO 13616 mod-97 check passes. */
    public static function sheba(string $value): ?string
    {
        $v = strtoupper(preg_replace('/[\s-]/u', '', Digits::toLatin($value)) ?? '');
        if (preg_match('/^\d{24}$/', $v)) {
            $v = 'IR'.$v;
        }
        if (! preg_match('/^IR\d{24}$/', $v)) {
            return null;
        }
        // Move country + check digits to the end, letters → numbers (I=18, R=27), mod 97 must be 1.
        $numeric = substr($v, 4).'1827'.substr($v, 2, 2);
        $mod = 0;
        foreach (str_split($numeric, 7) as $chunk) {
            $mod = (int) (($mod.$chunk) % 97);
        }

        return $mod === 1 ? $v : null;
    }

    /** Bank from the 3-digit code after the check digits (IRkk BBB…). */
    public static function bankName(string $sheba): string
    {
        return self::BANKS[substr($sheba, 4, 3)] ?? 'بانک نامشخص';
    }

    private const BANKS = [
        '010' => 'بانک مرکزی', '011' => 'صنعت و معدن', '012' => 'ملت', '013' => 'رفاه کارگران', '014' => 'مسکن',
        '015' => 'سپه', '016' => 'کشاورزی', '017' => 'ملی ایران', '018' => 'تجارت', '019' => 'صادرات ایران',
        '020' => 'توسعه صادرات', '021' => 'پست بانک', '022' => 'توسعه تعاون', '053' => 'کارآفرین', '054' => 'پارسیان',
        '055' => 'اقتصاد نوین', '056' => 'سامان', '057' => 'پاسارگاد', '058' => 'سرمایه', '059' => 'سینا',
        '060' => 'مهر ایران', '061' => 'شهر', '062' => 'آینده', '064' => 'گردشگری', '066' => 'دی',
        '069' => 'ایران زمین', '070' => 'رسالت', '078' => 'خاورمیانه',
    ];

    public static function mask(string $value, int $visible = 4): string
    {
        return str_repeat('•', max(0, strlen($value) - $visible)).substr($value, -$visible);
    }
}
