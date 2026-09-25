<?php

namespace App\Domain\Cashout\Providers;

use App\Domain\Cashout\CashoutRequestNumber;
use App\Models\CashoutRequest;
use Illuminate\Http\Client\Response;

/**
 * Jibit "cobank" settlement: `POST /cobank/v1/orders/settlement` with our own
 * `recordTrackId` (so a retried send can't pay twice) and
 * `GET /cobank/v1/orders/settlement/{trackId}` to follow it.
 */
class JibitPayoutProvider implements PayoutProvider
{
    private const DONE = ['TRANSFERRED'];

    private const FAILED = ['FAILED', 'CANCELLED', 'FAILED_WRONG', 'TRANSFERRED_REVERTED'];

    public function __construct(private readonly JibitClient $client, private readonly ?string $sourceIban, private readonly string $transferType = 'NORMAL') {}

    public function name(): string
    {
        return 'jibit';
    }

    public function automatic(): bool
    {
        return true;
    }

    public function send(CashoutRequest $request, string $trackId): array
    {
        $body = array_filter([
            'recordTrackId' => $trackId,
            'destinationIban' => $request->bankAccount->iban,
            'amount' => $request->amount_rial,
            'transferType' => $this->transferType, // NORMAL = Paya, RTGS = Satna
            'sourceIban' => $this->sourceIban,
            'requestDescription' => 'برداشت گام‌یار '.CashoutRequestNumber::of($request),
        ], fn ($v) => $v !== null);

        return $this->map($this->client->post('cobank', '/cobank/v1/orders/settlement', $body));
    }

    public function status(string $trackId): ?array
    {
        $response = $this->client->get('cobank', '/cobank/v1/orders/settlement/'.$trackId);

        return $response->status() === 404 ? null : $this->map($response);
    }

    private function map(Response $response): array
    {
        if (! $response->successful()) {
            // A rejected request never reached the bank.
            return ['state' => $response->serverError() ? 'processing' : 'failed', 'reference' => null, 'error' => (string) ($response->json('code') ?? 'http_'.$response->status())];
        }
        $record = collect($response->json('records') ?? [])->firstWhere('recordType', 'PRIME') ?? ($response->json('records.0') ?? []);
        $state = (string) ($record['state'] ?? 'RECEIVED');

        return [
            'state' => in_array($state, self::DONE, true) ? 'transferred' : (in_array($state, self::FAILED, true) ? 'failed' : 'processing'),
            'reference' => $record['bankReferenceNumber'] ?? $record['referenceNumber'] ?? $response->json('referenceNumber'),
            'error' => $record['failReason'] ?? (in_array($state, self::FAILED, true) ? $state : null),
        ];
    }
}
