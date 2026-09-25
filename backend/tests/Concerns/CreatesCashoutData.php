<?php

namespace Tests\Concerns;

/** Valid-by-checksum Iranian national codes and Sheba numbers for tests. */
trait CreatesCashoutData
{
    protected function nationalCode(string $first9 = '001234567'): string
    {
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $first9[$i] * (10 - $i);
        }
        $r = $sum % 11;

        return $first9.($r < 2 ? $r : 11 - $r);
    }

    /** IR + check digits + bank code (3) + 19-digit account part. */
    protected function sheba(string $bank = '017', string $account = '0000000123456789012'): string
    {
        $bban = $bank.$account;
        $numeric = $bban.'182700';
        $mod = 0;
        foreach (str_split($numeric, 7) as $chunk) {
            $mod = (int) (($mod.$chunk) % 97);
        }

        return 'IR'.str_pad((string) (98 - $mod), 2, '0', STR_PAD_LEFT).$bban;
    }
}
