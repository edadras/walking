<?php

namespace App\Models\Concerns;

use SensitiveParameter;

/**
 * Filament app (TOTP) authentication with recovery codes. Secrets and codes
 * are stored with the `encrypted` casts on the model.
 */
trait HasTotp
{
    public function getAppAuthenticationSecret(): ?string
    {
        // A freshly created model hasn't loaded the (null) column yet.
        return array_key_exists('two_factor_secret', $this->attributes) ? $this->two_factor_secret : null;
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->forceFill(['two_factor_secret' => $secret])->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /** @return array<string>|null */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return array_key_exists('two_factor_recovery_codes', $this->attributes) ? $this->two_factor_recovery_codes : null;
    }

    /** @param array<string>|null $codes */
    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->forceFill(['two_factor_recovery_codes' => $codes])->save();
    }
}
