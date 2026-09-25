<?php

namespace App\Domain\Cashout\Providers;

use App\Models\CashoutRequest;
use LogicException;

/** Transfers are made in the bank's corporate portal from the CSV export; finance records the reference. */
class ManualPayoutProvider implements PayoutProvider
{
    public function name(): string
    {
        return 'manual';
    }

    public function automatic(): bool
    {
        return false;
    }

    public function send(CashoutRequest $request, string $trackId): array
    {
        throw new LogicException('Manual payouts are not sent through an API.');
    }

    public function status(string $trackId): ?array
    {
        return null;
    }
}
