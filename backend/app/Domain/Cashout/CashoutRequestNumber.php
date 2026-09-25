<?php

namespace App\Domain\Cashout;

use App\Models\CashoutRequest;

final class CashoutRequestNumber
{
    /** Short human reference shared by the panel, CSV export and bank descriptions. */
    public static function of(CashoutRequest $r): string
    {
        return 'W-'.strtoupper(substr($r->public_id, -6));
    }
}
