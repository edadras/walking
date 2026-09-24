<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\OtpService;
use App\Domain\Settings\Settings;
use App\Domain\User\UserProvisioner;
use App\Enums\UserStatus;
use App\Exceptions\ApiException;
use App\Models\Device;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LoginWithOtp
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly UserProvisioner $users,
        private readonly Settings $settings,
        private readonly AuditLogger $audit,
    ) {}

    /** @return array{user: User, token: string, is_new_user: bool} */
    public function handle(string $phone, string $code, Device $device, ?string $timezone, ?string $referralCode): array
    {
        $this->otp->verify($phone, $code);

        return DB::transaction(function () use ($phone, $device, $timezone, $referralCode) {
            [$user, $isNew] = $this->users->findOrCreate($phone, $timezone, $referralCode);

            if ($user->status === UserStatus::Banned) {
                $this->audit->log('auth.login_blocked', $user, meta: ['device' => $device->public_id], actor: $user);

                throw ApiException::forbidden('account_banned', 'حساب شما مسدود شده است.');
            }

            $now = now();
            DB::table('device_user_links')->upsert(
                [['device_id' => $device->id, 'user_id' => $user->id, 'first_seen_at' => $now, 'last_seen_at' => $now]],
                ['device_id', 'user_id'],
                ['last_seen_at'],
            );

            // One live session per device: any token previously issued on this device (any account) dies.
            PersonalAccessToken::query()->where('device_id', $device->id)->delete();

            $token = $user->createToken(
                name: 'app:'.$device->public_id,
                expiresAt: $now->copy()->addDays($this->settings->int('auth.token_ttl_days')),
            );
            $token->accessToken->forceFill(['device_id' => $device->id])->save();

            $this->audit->log('auth.login', $user, meta: ['device' => $device->public_id, 'new_user' => $isNew], actor: $user);

            return ['user' => $user, 'token' => $token->plainTextToken, 'is_new_user' => $isNew];
        });
    }
}
