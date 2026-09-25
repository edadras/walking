<?php

namespace App\Domain\Cashout;

use App\Domain\Cashout\Providers\KycProvider;
use App\Domain\Settings\Settings;
use App\Models\BankAccount;
use App\Models\UserIdentity;
use App\Support\Jalali;

/**
 * Runs the automated inquiries and records what they said. With
 * `cashout.kyc_auto_approve` on, a fully passing check verifies without a human;
 * a failing or unavailable check always leaves the decision to support.
 */
class KycChecks
{
    public function __construct(
        private readonly KycProvider $provider,
        private readonly Settings $settings,
        private readonly CashoutService $cashout,
    ) {}

    public function enabled(): bool
    {
        return $this->provider->name() !== 'manual';
    }

    private static function jalali(\DateTimeInterface $date): string
    {
        [$y, $m, $d] = Jalali::of($date);

        return sprintf('%04d%02d%02d', $y, $m, $d);
    }

    /** +98912… → 0912… (the domestic format inquiry services use). */
    private static function localMobile(string $phone): string
    {
        return preg_replace('/^\+98/', '0', $phone) ?? $phone;
    }

    public function identity(UserIdentity $identity): string
    {
        $birth = self::jalali($identity->birth_date);
        $mobile = $this->provider->mobileMatches($identity->national_code, self::localMobile($identity->loadMissing('user')->user->phone));
        $names = $this->provider->nameSimilarity($identity->national_code, $birth, $identity->first_name, $identity->last_name);
        $min = $this->settings->int('cashout.kyc_name_similarity_min');

        $result = match (true) {
            $mobile === false, $names !== null && min($names) < $min => 'failed',
            $mobile === null, $names === null => 'unavailable',
            default => 'passed',
        };
        $identity->forceFill(['auto_result' => $result, 'auto_checked_at' => now(), 'auto_checks' => [
            'provider' => $this->provider->name(), 'mobile_matches' => $mobile, 'name_similarity' => $names, 'min_similarity' => $min,
        ]])->save();

        if ($result === 'passed' && $identity->status === UserIdentity::PENDING && $this->settings->bool('cashout.kyc_auto_approve')) {
            $this->cashout->reviewIdentity($identity, true, null);
        }

        return $result;
    }

    public function bankAccount(BankAccount $account): string
    {
        $identity = UserIdentity::query()->find($account->user_id);
        if ($identity === null) {
            return 'unavailable';
        }
        $matches = $this->provider->ibanMatches($account->iban, $identity->national_code, self::jalali($identity->birth_date));
        $info = $this->provider->ibanInfo($account->iban);
        $active = $info === null ? null : $info['status'] === 'ACTIVE';

        $result = match (true) {
            $matches === false, $active === false => 'failed',
            $matches === null, $active === null => 'unavailable',
            default => 'passed',
        };
        $account->forceFill(['auto_result' => $result, 'auto_checked_at' => now(), 'auto_checks' => [
            'provider' => $this->provider->name(), 'owner_matches' => $matches, 'account_status' => $info['status'] ?? null, 'owners' => $info['owners'] ?? null,
        ]])->save();

        if ($result === 'passed' && $account->status === BankAccount::PENDING && $identity->status === UserIdentity::VERIFIED
            && $this->settings->bool('cashout.kyc_auto_approve')) {
            $this->cashout->reviewBankAccount($account, true, null);
        }

        return $result;
    }
}
