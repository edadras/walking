<?php

namespace App\Domain\User;

use App\Domain\Audit\AuditLogger;
use App\Domain\Cashout\CashoutService;
use App\Domain\Leaderboard\LeaderboardService;
use App\Domain\Settings\Settings;
use App\Enums\OrderStatus;
use App\Enums\UserStatus;
use App\Models\AccountDeletionRequest;
use App\Models\ActivitySample;
use App\Models\Address;
use App\Models\AnalyticsEvent;
use App\Models\BankAccount;
use App\Models\CashoutRequest;
use App\Models\FriendChallenge;
use App\Models\FriendChallengeMember;
use App\Models\Friendship;
use App\Models\NotificationPreference;
use App\Models\Order;
use App\Models\OrganizationMember;
use App\Models\PersonalAccessToken;
use App\Models\SupportMessage;
use App\Models\User;
use App\Models\UserIdentity;
use App\Models\UserProfile;
use App\Models\WalkingSession;
use App\Models\WaterLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Carries out a deletion request once its grace period is over.
 *
 * The account is anonymised rather than hard-deleted: the points ledger, orders,
 * fraud history and payouts must stay consistent and auditable, but nothing left
 * behind identifies the person. Payout identity (national code, Sheba) is financial
 * record-keeping and is kept, encrypted, for `cashout.kyc_retention_days` after the
 * last payout, then erased by {@see purgeExpiredKyc()}.
 */
class AccountPurger
{
    public function __construct(
        private readonly Settings $settings,
        private readonly AuditLogger $audit,
        private readonly CashoutService $cashout,
    ) {}

    /** Why this account can't be erased yet (retried on the next run), or null. */
    public function deferReason(User $user): ?string
    {
        if (CashoutRequest::query()->where('user_id', $user->id)->whereIn('status', [CashoutRequest::APPROVED, CashoutRequest::PROCESSING])->exists()) {
            return 'payout_in_progress';
        }
        if (Order::query()->where('user_id', $user->id)->whereIn('status', [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped])->exists()) {
            return 'order_in_progress';
        }

        return null;
    }

    public function purge(AccountDeletionRequest $request): bool
    {
        $user = User::withTrashed()->findOrFail($request->user_id);
        if ($this->deferReason($user) !== null) {
            return false;
        }

        // A payout the finance team hasn't approved yet is simply withdrawn (points go back, then vanish with the account).
        foreach (CashoutRequest::query()->where('user_id', $user->id)->where('status', CashoutRequest::PENDING)->get() as $open) {
            $this->cashout->cancel($user, $open);
        }

        DB::transaction(function () use ($user, $request) {
            PersonalAccessToken::query()->where('tokenable_type', $user->getMorphClass())->where('tokenable_id', $user->id)->delete();
            // Device links are kept on purpose: they are how a banned device is recognised on a new account.

            Address::query()->where('user_id', $user->id)->delete();
            WaterLog::query()->where('user_id', $user->id)->delete();
            NotificationPreference::query()->where('user_id', $user->id)->delete();
            DB::table('notifications')->where('notifiable_type', $user->getMorphClass())->where('notifiable_id', $user->id)->delete();
            Friendship::query()->where('user_low_id', $user->id)->orWhere('user_high_id', $user->id)->delete();
            FriendChallengeMember::query()->where('user_id', $user->id)->delete();
            OrganizationMember::query()->where('user_id', $user->id)->delete();
            FriendChallenge::query()->where('creator_id', $user->id)->delete();
            ActivitySample::query()->whereIn('walking_session_id', WalkingSession::query()->where('user_id', $user->id)->select('id'))->delete();
            AnalyticsEvent::query()->where('user_id', $user->id)->update(['user_id' => null]);
            // Step totals stay for fraud/economy statistics; routes and motion traces go.
            WalkingSession::query()->where('user_id', $user->id)->update(['gps_summary' => null, 'motion_summary' => null]);
            UserProfile::query()->where('user_id', $user->id)->update(['birth_year' => null, 'gender' => null, 'height_cm' => null, 'weight_kg' => null]);
            SupportMessage::query()->where('author_type', $user->getMorphClass())->where('author_id', $user->id)->update(['body' => '[حذف‌شده به درخواست کاربر]']);
            Order::query()->where('user_id', $user->id)->whereNotNull('shipping_address')->update(['shipping_address' => json_encode(['redacted' => true])]);

            if (CashoutRequest::query()->where('user_id', $user->id)->exists()) {
                // Keep for retention; nothing new can be requested on a deleted account.
                UserIdentity::query()->whereKey($user->id)->update(['retain_until' => now()->addDays($this->settings->int('cashout.kyc_retention_days'))]);
            } else {
                BankAccount::withTrashed()->where('user_id', $user->id)->forceDelete();
                UserIdentity::query()->whereKey($user->id)->delete();
            }

            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $user->forceFill([
                'phone' => 'del-'.$user->id,
                'phone_verified_at' => null,
                'display_name' => null,
                'avatar_path' => null,
                'referral_code' => strtoupper(Str::random(12)),
                'status' => UserStatus::Deleted,
                'status_reason' => 'account_deleted',
                'leaderboard_visible' => false,
            ])->save();
            $user->delete();

            $request->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
        });
        app(LeaderboardService::class)->forget($user);
        $this->audit->log('account.deleted', $user, meta: ['request_id' => $request->id]);

        return true;
    }

    /** Erases payout identities of deleted accounts whose retention period has ended. */
    public function purgeExpiredKyc(): int
    {
        $n = 0;
        foreach (UserIdentity::query()->whereNotNull('retain_until')->where('retain_until', '<=', now())->get() as $identity) {
            DB::transaction(function () use ($identity) {
                // Requests keep the bank name and last 4 digits for the books; the full Sheba goes.
                BankAccount::withTrashed()->where('user_id', $identity->user_id)->get()->each(fn (BankAccount $a) => $a->forceFill([
                    'iban' => '', 'iban_hash' => hash('sha256', 'purged|'.$a->id), 'holder_name' => '—',
                ])->save());
                $identity->delete();
            });
            $n++;
        }

        return $n;
    }
}
