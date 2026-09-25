<?php

namespace App\Domain\Cashout\Providers;

/** No inquiry service: support verifies identities and accounts by hand in the panel. */
class ManualKycProvider implements KycProvider
{
    public function name(): string
    {
        return 'manual';
    }

    public function mobileMatches(string $nationalCode, string $mobile): ?bool
    {
        return null;
    }

    public function nameSimilarity(string $nationalCode, string $jalaliBirthDate, string $firstName, string $lastName): ?array
    {
        return null;
    }

    public function ibanMatches(string $iban, string $nationalCode, string $jalaliBirthDate): ?bool
    {
        return null;
    }

    public function ibanInfo(string $iban): ?array
    {
        return null;
    }
}
