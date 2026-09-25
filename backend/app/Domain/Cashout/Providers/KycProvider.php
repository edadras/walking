<?php

namespace App\Domain\Cashout\Providers;

/**
 * Automated identity checks. Every method returns null when the check could not be
 * made (provider off, outage), so callers fall back to manual review instead of guessing.
 */
interface KycProvider
{
    public function name(): string;

    /** Shahkar: is this mobile number registered to this national code? */
    public function mobileMatches(string $nationalCode, string $mobile): ?bool;

    /**
     * Civil registry name similarity for national code + birth date.
     *
     * @return array{first: int, last: int}|null percentages
     */
    public function nameSimilarity(string $nationalCode, string $jalaliBirthDate, string $firstName, string $lastName): ?array;

    /** Does this Sheba belong to the person with this national code (and birth date)? */
    public function ibanMatches(string $iban, string $nationalCode, string $jalaliBirthDate): ?bool;

    /** @return array{status: string, owners: list<string>, bank: string}|null */
    public function ibanInfo(string $iban): ?array;
}
