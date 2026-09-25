<?php

namespace App\Domain\Cashout;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\OtpService;
use App\Domain\Settings\FeatureFlags;
use App\Domain\Settings\Settings;
use App\Domain\Wallet\ConversionRate;
use App\Domain\Wallet\WalletService;
use App\Enums\FraudCaseStatus;
use App\Enums\TransactionType;
use App\Enums\UserStatus;
use App\Exceptions\ApiException;
use App\Models\Admin;
use App\Models\BankAccount;
use App\Models\CashoutRequest;
use App\Models\FraudCase;
use App\Models\User;
use App\Models\UserIdentity;
use App\Notifications\UserNotification;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Points → rial payouts.
 *
 *   identity (national code, name, birth date) ─┐  every step needs a fresh
 *   bank account (Sheba in the same name)  ─────┤  SMS code (purpose "cashout")
 *   request (within limits) ────────────────────┘
 *        │  points are debited from the ledger immediately (can't be spent twice)
 *        ▼
 *   admin A approves ──► admin B (≠ A) records the bank transfer reference ──► paid
 *        └──── any admin rejects (or the user cancels while pending) ──► points refunded
 */
class CashoutService
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly WalletService $wallet,
        private readonly ConversionRate $rate,
        private readonly Settings $settings,
        private readonly FeatureFlags $flags,
        private readonly AuditLogger $audit,
    ) {}

    public function enabled(User $user): bool
    {
        return $this->flags->enabled('cashout', $user->id);
    }

    private function guard(User $user): void
    {
        if (! $this->enabled($user)) {
            throw ApiException::forbidden('feature_disabled', 'برداشت نقدی در حال حاضر فعال نیست.');
        }
    }

    /** Step-up confirmation: a separate SMS code only valid for payout actions. */
    public function confirm(User $user, string $code): void
    {
        $this->otp->verify($user->phone, $code, 'cashout');
    }

    // ---- Identity -----------------------------------------------------------

    public function submitIdentity(User $user, array $data, string $otpCode): UserIdentity
    {
        $this->guard($user);
        $code = IranianId::nationalCode($data['national_code'])
            ?? throw ApiException::unprocessable('national_code_invalid', 'کد ملی معتبر نیست.');
        $birth = CarbonImmutable::parse($data['birth_date']);
        if ($birth->diffInYears(now()) < $this->settings->int('cashout.min_age_years')) {
            throw ApiException::unprocessable('age_restricted', 'برداشت نقدی برای کاربران زیر '.$this->settings->int('cashout.min_age_years').' سال ممکن نیست.');
        }
        $existing = UserIdentity::query()->find($user->id);
        if ($existing?->status === UserIdentity::VERIFIED) {
            throw ApiException::conflict('identity_locked', 'هویت تو تأیید شده و قابل تغییر نیست. برای اصلاح با پشتیبانی تماس بگیر.');
        }
        $this->confirm($user, $otpCode);

        $hash = UserIdentity::hashNationalCode($code);
        $taken = UserIdentity::query()->where('national_code_hash', $hash)->where('user_id', '!=', $user->id)->exists();
        if ($taken) {
            // One person, one payout account.
            $this->audit->log('cashout.national_code_reused', $user);
            throw ApiException::conflict('national_code_taken', 'این کد ملی قبلاً برای حساب دیگری ثبت شده است.');
        }

        $identity = UserIdentity::query()->updateOrCreate(['user_id' => $user->id], [
            'first_name' => trim($data['first_name']), 'last_name' => trim($data['last_name']),
            'national_code' => $code, 'national_code_hash' => $hash, 'birth_date' => $birth->toDateString(),
            'status' => UserIdentity::PENDING, 'rejection_reason' => null, 'reviewed_by' => null, 'reviewed_at' => null, 'submitted_at' => now(),
        ]);
        $this->audit->log('cashout.identity_submitted', $user);

        return $identity;
    }

    // ---- Bank accounts -------------------------------------------------------

    public function addBankAccount(User $user, string $sheba, string $otpCode): BankAccount
    {
        $this->guard($user);
        $identity = UserIdentity::query()->find($user->id);
        if ($identity === null || $identity->status === UserIdentity::REJECTED) {
            throw ApiException::unprocessable('identity_required', 'اول مشخصات هویتی‌ات را ثبت کن.');
        }
        $iban = IranianId::sheba($sheba) ?? throw ApiException::unprocessable('sheba_invalid', 'شماره شبا معتبر نیست.');
        if (BankAccount::query()->where('user_id', $user->id)->count() >= 3) {
            throw ApiException::conflict('bank_account_limit', 'حداکثر ۳ حساب بانکی می‌توانی ثبت کنی.');
        }
        $this->confirm($user, $otpCode);

        $hash = BankAccount::hashIban($iban);
        $previous = BankAccount::withTrashed()->where('iban_hash', $hash)->first();
        if ($previous !== null && ($previous->user_id !== $user->id || ! $previous->trashed())) {
            $this->audit->log('cashout.iban_reused', $user);
            throw ApiException::conflict('sheba_taken', 'این شماره شبا قبلاً ثبت شده است.');
        }
        if ($previous !== null) {
            // Re-adding an account the user removed: reviewed again from scratch.
            $previous->restore();
            $previous->forceFill(['holder_name' => $identity->fullName(), 'status' => BankAccount::PENDING, 'rejection_reason' => null,
                'reviewed_by' => null, 'reviewed_at' => null])->save();
            $this->audit->log('cashout.bank_account_added', $previous);

            return $previous;
        }

        try {
            $account = BankAccount::query()->create([
                'user_id' => $user->id, 'iban' => $iban, 'iban_hash' => $hash, 'iban_last4' => substr($iban, -4),
                'bank_name' => IranianId::bankName($iban),
                // Must be the identity holder's own account; support checks the bank's owner name against it.
                'holder_name' => $identity->fullName(),
                'status' => BankAccount::PENDING,
            ]);
        } catch (UniqueConstraintViolationException) {
            $this->audit->log('cashout.iban_reused', $user);
            throw ApiException::conflict('sheba_taken', 'این شماره شبا قبلاً ثبت شده است.');
        }
        $this->audit->log('cashout.bank_account_added', $account);

        return $account;
    }

    public function removeBankAccount(User $user, BankAccount $account): void
    {
        abort_unless($account->user_id === $user->id, 404);
        if (CashoutRequest::query()->where('bank_account_id', $account->id)->whereIn('status', CashoutRequest::OPEN)->exists()) {
            throw ApiException::conflict('bank_account_in_use', 'تا وقتی درخواست برداشت باز داری، این حساب قابل حذف نیست.');
        }
        $account->delete();
    }

    // ---- Requests -----------------------------------------------------------

    /** Everything the app needs to render the cash-out screen, including why it's not possible yet. */
    public function overview(User $user): array
    {
        $identity = UserIdentity::query()->find($user->id);
        $accounts = BankAccount::query()->where('user_id', $user->id)->orderBy('id')->get();
        $limits = $this->limits($user);

        return [
            'enabled' => $this->enabled($user),
            'rial_per_point' => $this->rate->current(),
            'available_points' => $user->wallet?->available_balance ?? 0,
            'limits' => $limits,
            'blockers' => $this->blockers($user, $identity, $accounts),
            'identity' => $identity === null ? null : [
                'first_name' => $identity->first_name, 'last_name' => $identity->last_name,
                'national_code' => IranianId::mask($identity->national_code, 3),
                'birth_date' => $identity->birth_date->toDateString(),
                'status' => $identity->status, 'rejection_reason' => $identity->rejection_reason,
            ],
            'bank_accounts' => $accounts->map(fn (BankAccount $a) => [
                'id' => $a->public_id, 'iban' => $a->masked(), 'bank_name' => $a->bank_name, 'holder_name' => $a->holder_name,
                'status' => $a->status, 'rejection_reason' => $a->rejection_reason,
            ])->values()->all(),
            'requests' => CashoutRequest::query()->where('user_id', $user->id)->with('bankAccount')->latest('id')->limit(20)->get()
                ->map(fn (CashoutRequest $r) => $this->present($r))->all(),
        ];
    }

    public function present(CashoutRequest $r): array
    {
        return [
            'id' => $r->public_id, 'points' => $r->points, 'amount_rial' => $r->amount_rial, 'status' => $r->status,
            'status_label' => CashoutRequest::LABELS[$r->status], 'bank' => $r->bankAccount?->bank_name, 'iban' => $r->bankAccount?->masked(),
            'bank_reference' => $r->bank_reference, 'rejection_reason' => $r->rejection_reason,
            'created_at' => $r->created_at->toIso8601String(), 'paid_at' => $r->paid_at?->toIso8601String(),
        ];
    }

    /** @return array{min: int, max: int, window_max: int, window_used: int, window_left: int} */
    public function limits(User $user): array
    {
        $windowMax = $this->settings->int('cashout.max_points_per_30_days');
        $used = (int) CashoutRequest::query()->where('user_id', $user->id)
            ->whereIn('status', [CashoutRequest::PENDING, CashoutRequest::APPROVED, CashoutRequest::PAID])
            ->where('created_at', '>=', now()->subDays(30))->sum('points');

        return [
            'min' => $this->settings->int('cashout.min_points'),
            'max' => $this->settings->int('cashout.max_points_per_request'),
            'window_max' => $windowMax,
            'window_used' => $used,
            'window_left' => max(0, $windowMax - $used),
        ];
    }

    /** @return list<array{code: string, message: string}> */
    private function blockers(User $user, ?UserIdentity $identity, $accounts): array
    {
        $b = [];
        $add = function (string $code, string $message) use (&$b) {
            $b[] = compact('code', 'message');
        };
        if (! $this->enabled($user)) {
            $add('feature_disabled', 'برداشت نقدی در حال حاضر فعال نیست.');
        }
        if ($user->status !== UserStatus::Active) {
            $add('account_restricted', 'حساب تو محدود شده است.');
        }
        $minAge = $this->settings->int('cashout.min_account_age_days');
        if ($user->created_at->gt(now()->subDays($minAge))) {
            $add('account_too_new', 'برداشت از '.$minAge.' روز پس از ثبت‌نام ممکن است.');
        }
        if ($identity?->status !== UserIdentity::VERIFIED) {
            $add('identity_unverified', $identity === null ? 'مشخصات هویتی را ثبت کن.' : 'مشخصات هویتی‌ات هنوز تأیید نشده است.');
        }
        if (! $accounts->contains(fn (BankAccount $a) => $a->status === BankAccount::VERIFIED)) {
            $add('bank_account_unverified', 'یک حساب بانکی تأییدشده لازم است.');
        }
        if (FraudCase::query()->where('user_id', $user->id)->whereIn('status', [FraudCaseStatus::Open, FraudCaseStatus::Flagged])->exists()) {
            $add('under_review', 'حساب تو در حال بررسی است؛ پس از پایان بررسی می‌توانی برداشت کنی.');
        }
        if (CashoutRequest::query()->where('user_id', $user->id)->whereIn('status', CashoutRequest::OPEN)->exists()) {
            $add('request_open', 'یک درخواست برداشت در حال بررسی داری.');
        }

        return $b;
    }

    public function request(User $user, string $bankAccountId, int $points, string $otpCode, string $key): CashoutRequest
    {
        $this->guard($user);
        if ($existing = CashoutRequest::query()->where('user_id', $user->id)->where('idempotency_key', $key)->first()) {
            return $existing;
        }
        $account = BankAccount::query()->where('user_id', $user->id)->where('public_id', $bankAccountId)->first()
            ?? throw ApiException::unprocessable('bank_account_invalid', 'حساب بانکی انتخاب‌شده معتبر نیست.');
        $blockers = $this->blockers($user, UserIdentity::query()->find($user->id), BankAccount::query()->where('user_id', $user->id)->get());
        if ($blockers !== []) {
            throw ApiException::conflict($blockers[0]['code'], $blockers[0]['message']);
        }
        if ($account->status !== BankAccount::VERIFIED) {
            throw ApiException::unprocessable('bank_account_unverified', 'این حساب هنوز تأیید نشده است.');
        }
        $limits = $this->limits($user);
        if ($points < $limits['min'] || $points > $limits['max']) {
            throw ApiException::unprocessable('amount_out_of_range', 'مقدار برداشت باید بین '.number_format($limits['min']).' و '.number_format($limits['max']).' امتیاز باشد.');
        }
        if ($points > $limits['window_left']) {
            throw ApiException::unprocessable('window_limit', 'در ۳۰ روز اخیر حداکثر '.number_format($limits['window_left']).' امتیاز دیگر می‌توانی برداشت کنی.');
        }
        $this->confirm($user, $otpCode);

        try {
            $request = DB::transaction(function () use ($user, $account, $points, $key) {
                User::query()->whereKey($user->id)->lockForUpdate()->first(); // one request at a time per user
                if (CashoutRequest::query()->where('user_id', $user->id)->whereIn('status', CashoutRequest::OPEN)->exists()) {
                    throw ApiException::conflict('request_open', 'یک درخواست برداشت در حال بررسی داری.');
                }
                $rate = $this->rate->current();
                $request = CashoutRequest::query()->create([
                    'user_id' => $user->id, 'bank_account_id' => $account->id, 'points' => $points,
                    'rial_per_point' => $rate, 'amount_rial' => $points * $rate, 'status' => CashoutRequest::PENDING, 'idempotency_key' => $key,
                ]);
                // Throws InsufficientPoints → nothing is created.
                $tx = $this->wallet->debit($user, $points, TransactionType::Cashout, 'cashout:'.$request->public_id, 'برداشت نقدی به حساب '.$account->bank_name, $request);
                $request->forceFill(['debit_transaction_id' => $tx->id])->save();

                return $request;
            });
        } catch (UniqueConstraintViolationException) {
            return CashoutRequest::query()->where('user_id', $user->id)->where('idempotency_key', $key)->firstOrFail();
        }
        $this->audit->log('cashout.requested', $request, new: ['points' => $points, 'amount_rial' => $request->amount_rial]);

        return $request;
    }

    public function cancel(User $user, CashoutRequest $request): CashoutRequest
    {
        abort_unless($request->user_id === $user->id, 404);

        return $this->close($request, CashoutRequest::CANCELLED, 'لغو توسط کاربر', null, [CashoutRequest::PENDING]);
    }

    // ---- Admin ----------------------------------------------------------------

    public function reviewIdentity(UserIdentity $identity, bool $ok, Admin $admin, ?string $reason = null): void
    {
        $identity->forceFill(['status' => $ok ? UserIdentity::VERIFIED : UserIdentity::REJECTED, 'rejection_reason' => $ok ? null : $reason,
            'reviewed_by' => $admin->id, 'reviewed_at' => now()])->save();
        $this->audit->log($ok ? 'cashout.identity_verified' : 'cashout.identity_rejected', $identity->user, meta: ['reason' => $reason], actor: $admin);
        $identity->user->notify(new UserNotification('order_update', $ok ? 'هویت تأیید شد' : 'هویت تأیید نشد',
            $ok ? 'مشخصات هویتی‌ات تأیید شد.' : 'مشخصات هویتی تأیید نشد: '.$reason, ['type' => 'cashout']));
    }

    public function reviewBankAccount(BankAccount $account, bool $ok, Admin $admin, ?string $reason = null): void
    {
        $account->forceFill(['status' => $ok ? BankAccount::VERIFIED : BankAccount::REJECTED, 'rejection_reason' => $ok ? null : $reason,
            'reviewed_by' => $admin->id, 'reviewed_at' => now()])->save();
        $this->audit->log($ok ? 'cashout.bank_account_verified' : 'cashout.bank_account_rejected', $account, meta: ['reason' => $reason], actor: $admin);
        $account->user->notify(new UserNotification('order_update', $ok ? 'حساب بانکی تأیید شد' : 'حساب بانکی تأیید نشد',
            $ok ? 'حساب '.$account->bank_name.' برای برداشت تأیید شد.' : 'حساب بانکی تأیید نشد: '.$reason, ['type' => 'cashout']));
    }

    public function approve(CashoutRequest $request, Admin $admin): CashoutRequest
    {
        return DB::transaction(function () use ($request, $admin) {
            $locked = CashoutRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== CashoutRequest::PENDING) {
                throw ApiException::conflict('invalid_transition', 'این درخواست دیگر قابل تأیید نیست.');
            }
            $locked->forceFill(['status' => CashoutRequest::APPROVED, 'approved_by' => $admin->id, 'approved_at' => now()])->save();
            $this->audit->log('cashout.approved', $locked, actor: $admin);

            return $locked;
        });
    }

    /** Four eyes: whoever approved can't also confirm the money left the bank. */
    public function markPaid(CashoutRequest $request, Admin $admin, string $bankReference): CashoutRequest
    {
        $request = DB::transaction(function () use ($request, $admin, $bankReference) {
            $locked = CashoutRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== CashoutRequest::APPROVED) {
                throw ApiException::conflict('invalid_transition', 'فقط درخواست تأییدشده قابل ثبت واریز است.');
            }
            if ($locked->approved_by === $admin->id) {
                throw ApiException::forbidden('four_eyes', 'ثبت واریز باید توسط مدیری غیر از تأییدکننده انجام شود.');
            }
            $locked->forceFill(['status' => CashoutRequest::PAID, 'paid_by' => $admin->id, 'paid_at' => now(), 'bank_reference' => $bankReference])->save();
            $this->audit->log('cashout.paid', $locked, new: ['bank_reference' => $bankReference, 'amount_rial' => $locked->amount_rial], actor: $admin);

            return $locked;
        });
        $request->user->notify(new UserNotification('order_update', 'واریز انجام شد',
            number_format($request->amount_rial).' ریال به حسابت واریز شد. کد پیگیری: '.$bankReference, ['type' => 'cashout']));

        return $request;
    }

    public function reject(CashoutRequest $request, Admin $admin, string $reason): CashoutRequest
    {
        return $this->close($request, CashoutRequest::REJECTED, $reason, $admin, CashoutRequest::OPEN);
    }

    /** Closes an open request and gives the points back through the ledger. */
    private function close(CashoutRequest $request, string $status, string $reason, ?Admin $admin, array $from): CashoutRequest
    {
        $closed = DB::transaction(function () use ($request, $status, $reason, $admin, $from) {
            $locked = CashoutRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, $from, true)) {
                throw ApiException::conflict('invalid_transition', 'این درخواست دیگر قابل تغییر نیست.');
            }
            $tx = $this->wallet->credit($locked->user, $locked->points, TransactionType::Refund, 'refund:cashout:'.$locked->public_id,
                'بازگشت امتیاز برداشت', $locked, ['reason' => $reason]);
            $locked->forceFill(['status' => $status, 'rejection_reason' => $reason, 'rejected_by' => $admin?->id, 'refund_transaction_id' => $tx->id])->save();
            $this->audit->log('cashout.'.$status, $locked, meta: ['reason' => $reason], actor: $admin);

            return $locked;
        });
        if ($admin !== null) {
            $closed->user->notify(new UserNotification('order_update', 'درخواست برداشت رد شد',
                'امتیازها به کیف پولت برگشت. دلیل: '.$reason, ['type' => 'cashout']));
        }

        return $closed;
    }
}
