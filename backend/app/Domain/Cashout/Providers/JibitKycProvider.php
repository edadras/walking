<?php

namespace App\Domain\Cashout\Providers;

use Illuminate\Support\Facades\Log;
use Throwable;

/** Jibit "ide" inquiry services: `/ide/v1/services/matching`, `/identity/similarity`, `/ibans`. */
class JibitKycProvider implements KycProvider
{
    public function __construct(private readonly JibitClient $client) {}

    public function name(): string
    {
        return 'jibit';
    }

    public function mobileMatches(string $nationalCode, string $mobile): ?bool
    {
        return $this->matched(['nationalCode' => $nationalCode, 'mobileNumber' => $mobile]);
    }

    public function nameSimilarity(string $nationalCode, string $jalaliBirthDate, string $firstName, string $lastName): ?array
    {
        $r = $this->call(fn () => $this->client->get('ide', '/ide/v1/services/identity/similarity', [
            'nationalCode' => $nationalCode, 'birthDate' => $jalaliBirthDate, 'firstName' => $firstName, 'lastName' => $lastName,
        ]));

        return $r === null ? null : ['first' => (int) ($r['firstNameSimilarityPercentage'] ?? 0), 'last' => (int) ($r['lastNameSimilarityPercentage'] ?? 0)];
    }

    public function ibanMatches(string $iban, string $nationalCode, string $jalaliBirthDate): ?bool
    {
        return $this->matched(['iban' => $iban, 'nationalCode' => $nationalCode, 'birthDate' => $jalaliBirthDate]);
    }

    public function ibanInfo(string $iban): ?array
    {
        $r = $this->call(fn () => $this->client->get('ide', '/ide/v1/ibans', ['value' => $iban]));
        if ($r === null || ! isset($r['ibanInfo'])) {
            return null;
        }
        $info = $r['ibanInfo'];

        return [
            'status' => (string) ($info['status'] ?? 'UNKNOWN'),
            'bank' => (string) ($info['bank'] ?? ''),
            'owners' => array_map(fn (array $o) => trim(($o['firstName'] ?? '').' '.($o['lastName'] ?? '')), $info['owners'] ?? []),
        ];
    }

    private function matched(array $query): ?bool
    {
        $r = $this->call(fn () => $this->client->get('ide', '/ide/v1/services/matching', $query));

        return $r === null ? null : (bool) ($r['matched'] ?? false);
    }

    private function call(callable $fn): ?array
    {
        try {
            $response = $fn();
            if ($response->successful()) {
                return $response->json();
            }
            Log::warning('jibit inquiry failed', ['status' => $response->status(), 'code' => $response->json('code')]);
        } catch (Throwable $e) {
            Log::warning('jibit inquiry error', ['error' => $e->getMessage()]);
        }

        return null;
    }
}
