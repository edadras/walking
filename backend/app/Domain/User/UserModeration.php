<?php

namespace App\Domain\User;

use App\Domain\Audit\AuditLogger;
use App\Enums\DeviceStatus;
use App\Enums\UserStatus;
use App\Models\Admin;
use App\Models\Device;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Every moderation decision goes through here so it is always audited with a reason. */
class UserModeration
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function setStatus(User $user, UserStatus $status, string $reason, Admin $admin): void
    {
        DB::transaction(function () use ($user, $status, $reason, $admin) {
            $old = $user->status;
            $user->forceFill(['status' => $status, 'status_reason' => $status === UserStatus::Active ? null : $reason])->save();

            if ($status !== UserStatus::Active) {
                PersonalAccessToken::query()->where('tokenable_type', $user->getMorphClass())->where('tokenable_id', $user->id)->delete();
            }

            $this->audit->log('user.status_changed', $user, ['status' => $old->value], ['status' => $status->value], ['reason' => $reason], $admin);
        });
    }

    public function setDeviceStatus(Device $device, DeviceStatus $status, string $reason, Admin $admin): void
    {
        DB::transaction(function () use ($device, $status, $reason, $admin) {
            $old = $device->status;
            $device->forceFill(['status' => $status])->save();

            if ($status !== DeviceStatus::Active) {
                PersonalAccessToken::query()->where('device_id', $device->id)->delete();
            }

            $this->audit->log('device.status_changed', $device, ['status' => $old->value], ['status' => $status->value], ['reason' => $reason], $admin);
        });
    }
}
