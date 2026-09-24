<?php

namespace App\Domain\User;

use App\Domain\Audit\AuditLogger;
use App\Domain\Leaderboard\LeaderboardService;
use App\Domain\Settings\Settings;
use App\Enums\NotificationCategory;
use App\Exceptions\ApiException;
use App\Models\AccountDeletionRequest;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProfileService
{
    public function __construct(
        private readonly Settings $settings,
        private readonly UserProvisioner $provisioner,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $data validated */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            if (array_key_exists('display_name', $data)) {
                $user->display_name = $data['display_name'];
            }

            if (isset($data['timezone']) && $data['timezone'] !== $user->timezone) {
                $this->changeTimezone($user, $data['timezone']);
            }
            $user->save();

            $profileFields = array_intersect_key($data, array_flip(['birth_year', 'gender', 'height_cm', 'weight_kg']));
            if ($profileFields !== []) {
                $user->profile->fill($profileFields)->save();
            }

            return $user->refresh();
        });
    }

    /** @param array<string, mixed> $data validated */
    public function updateSettings(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            if (array_key_exists('leaderboard_visible', $data)) {
                $user->forceFill(['leaderboard_visible' => $data['leaderboard_visible']])->save();
                $boards = app(LeaderboardService::class);
                $data['leaderboard_visible']
                    ? $boards->sync($user, now($user->timezone)->toDateString())
                    : $boards->forget($user);
            }

            $profileFields = array_intersect_key($data, array_flip([
                'daily_step_goal', 'water_goal_ml', 'water_reminder_enabled', 'water_reminder_interval_min',
                'quiet_hours_start', 'quiet_hours_end',
            ]));
            if ($profileFields !== []) {
                $user->profile->fill($profileFields)->save();
            }

            return $user->refresh();
        });
    }

    public function updateAvatar(User $user, UploadedFile $file): User
    {
        $path = $file->storeAs('avatars', $user->public_id.'-'.now()->timestamp.'.'.$file->extension(), 'public');

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }
        $user->forceFill(['avatar_path' => $path])->save();

        return $user;
    }

    /** @return array<string, bool> category => push_enabled, with defaults for categories never touched */
    public function notificationPreferences(User $user): array
    {
        $stored = $user->notificationPreferences()->pluck('push_enabled', 'category')->all();

        $result = [];
        foreach (NotificationCategory::cases() as $category) {
            $result[$category->value] = $category->isMandatory() ? true : (bool) ($stored[$category->value] ?? true);
        }

        return $result;
    }

    /** @param array<string, bool> $preferences */
    public function updateNotificationPreferences(User $user, array $preferences): array
    {
        foreach ($preferences as $category => $enabled) {
            $case = NotificationCategory::from($category);
            if ($case->isMandatory()) {
                continue;
            }
            NotificationPreference::query()->updateOrCreate(
                ['user_id' => $user->id, 'category' => $case->value],
                ['push_enabled' => $enabled],
            );
        }

        return $this->notificationPreferences($user);
    }

    public function requestDeletion(User $user): AccountDeletionRequest
    {
        $existing = AccountDeletionRequest::query()->where('user_id', $user->id)->where('status', 'pending')->first();
        if ($existing) {
            return $existing;
        }

        $request = AccountDeletionRequest::query()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'requested_at' => now(),
            'scheduled_for' => now()->addDays($this->settings->int('account.deletion_grace_days')),
        ]);
        $user->forceFill(['deletion_requested_at' => now()])->save();
        $this->audit->log('account.deletion_requested', $user, actor: $user);

        return $request;
    }

    public function cancelDeletion(User $user): void
    {
        AccountDeletionRequest::query()->where('user_id', $user->id)->where('status', 'pending')->update(['status' => 'cancelled']);
        $user->forceFill(['deletion_requested_at' => null])->save();
        $this->audit->log('account.deletion_cancelled', $user, actor: $user);
    }

    private function changeTimezone(User $user, string $timezone): void
    {
        $cooldown = $this->settings->int('security.timezone_change_cooldown_days');
        if ($user->timezone_changed_at !== null && $user->timezone_changed_at->gt(now()->subDays($cooldown))) {
            throw ApiException::unprocessable('timezone_change_too_soon', "منطقه زمانی را فقط هر {$cooldown} روز یک بار می‌توان تغییر داد.");
        }

        $old = $user->timezone;
        $user->timezone = $this->provisioner->validTimezone($timezone);
        $user->timezone_changed_at = now();
        $this->audit->log('user.timezone_changed', $user, ['timezone' => $old], ['timezone' => $user->timezone], actor: $user);
    }
}
