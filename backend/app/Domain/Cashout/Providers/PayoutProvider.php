<?php

namespace App\Domain\Cashout\Providers;

use App\Models\CashoutRequest;

/**
 * Bank transfer of an approved payout. States: processing | transferred | failed.
 *
 * @phpstan-type PayoutState array{state: string, reference: ?string, error: ?string}
 */
interface PayoutProvider
{
    public function name(): string;

    /** Whether transfers can be sent automatically (false = manual CSV + reference entry). */
    public function automatic(): bool;

    /** @return array{state: string, reference: ?string, error: ?string} */
    public function send(CashoutRequest $request, string $trackId): array;

    /** @return array{state: string, reference: ?string, error: ?string}|null null = not known to the provider */
    public function status(string $trackId): ?array;
}
