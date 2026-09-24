<?php

namespace App\Domain\User;

use App\Domain\Settings\Settings;
use App\Enums\UserStatus;
use App\Models\User;

class UserProvisioner
{
    public function __construct(private readonly Settings $settings) {}

    /** @return array{0: User, 1: bool} user and whether it was just created */
    public function findOrCreate(string $phone, ?string $timezone, ?string $referralCode): array
    {
        $user = User::query()->where('phone', $phone)->first();
        if ($user !== null) {
            return [$user, false];
        }

        $referrer = $referralCode
            ? User::query()->where('referral_code', strtoupper($referralCode))->where('status', UserStatus::Active)->first()
            : null;

        $user = User::query()->create([
            'phone' => $phone,
            'phone_verified_at' => now(),
            'status' => UserStatus::Active,
            'referral_code' => $this->uniqueReferralCode(),
            'referred_by_id' => $referrer?->id,
            'timezone' => $this->validTimezone($timezone),
            'locale' => 'fa',
        ]);

        $user->profile()->create([
            'daily_step_goal' => $this->settings->int('activity.default_daily_goal'),
            'water_goal_ml' => $this->settings->int('health.default_water_goal_ml'),
        ]);

        return [$user, true];
    }

    public function validTimezone(?string $timezone): string
    {
        return $timezone && in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'Asia/Tehran';
    }

    private function uniqueReferralCode(): string
    {
        // Unambiguous alphabet (no 0/O/1/I).
        do {
            $code = '';
            for ($i = 0; $i < 7; $i++) {
                $code .= 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'[random_int(0, 31)];
            }
        } while (User::query()->where('referral_code', $code)->exists());

        return $code;
    }
}
