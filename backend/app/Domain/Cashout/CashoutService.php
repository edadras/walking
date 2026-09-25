<?php

namespace App\Domain\Cashout;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\OtpService;
use App\Domain\Cashout\Providers\PayoutProvider;
use App\Domain\Ops\OpsAlerter;
use App\Domain\Settings\FeatureFlags;
use App\Domain\Settings\Settings;
use App\Domain\Wallet\ConversionRate;
use App\Domain\Wallet\WalletService;
use App\Enums\FraudCaseStatus;
use App\Enums\TransactionType;
use App\Enums\UserStatus;
use App\Exceptions\ApiException;
use App\Jobs\RunKycChecks;
use App\Models\Admin;
use App\Models\BankAccount;
use App\Models\CashoutRequest;
use App\Models\FraudCase;
use App\Models\User;
use App\Models\UserIdentity;
use App\Notifications\UserNotification;
use App\Support\Jalali;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        private readonly WithdrawableBalance $withdrawable,
        private readonly CashoutRisk $risk,
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
        RunKycChecks::dispatch('identity', $identity->user_id)->afterCommit();

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
            RunKycChecks::dispatch('bank_account', $previous->id)->afterCommit();

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
        RunKycChecks::dispatch('bank_account', $account->id)->afterCommit();

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
        $balance = $this->withdrawable->of($user);
        $queue = CashoutRequest::query()->where('status', CashoutRequest::PENDING)->orderBy('id')->pluck('id')->flip();

        return [
            'enabled' => $this->enabled($user),
            'phone' => $user->phone, // where the confirmation codes go
            'rial_per_point' => $this->rate->current(),
            'available_points' => $balance['available'],
            'withdrawable_points' => $balance['withdrawable'],
            'immature_points' => $balance['immature'],
            'maturity_days' => $this->settings->int('cashout.maturity_days'),
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
                ->map(fn (CashoutRequest $r) => [...$this->present($r), 'queue_position' => isset($queue[$r->id]) ? $queue[$r->id] + 1 : null])->all(),
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
        if ($user->deletion_requested_at !== null) {
            $add('deletion_requested', 'درخواست حذف حساب ثبت کرده‌ای؛ برای برداشت، اول آن را لغو کن.');
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
        $min = $this->settings->int('cashout.min_points');
        if ($this->withdrawable->of($user)['withdrawable'] < $min) {
            $add('insufficient_points', 'برای برداشت دست‌کم '.number_format($min).' امتیاز قابل برداشت لازم است؛ امتیاز پیاده‌روی و فعالیت '
                .$this->settings->int('cashout.maturity_days').' روز پس از قطعی شدن قابل برداشت می‌شود (امتیاز دعوت و تبلیغ فقط در فروشگاه).');
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
        $withdrawable = $this->withdrawable->of($user)['withdrawable'];
        if ($points > $withdrawable) {
            throw ApiException::unprocessable('not_withdrawable', 'فقط '.number_format($withdrawable).' امتیاز از موجودی‌ات قابل برداشت است.');
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
                $risk = $this->risk->assess($user);
                $request = CashoutRequest::query()->create([
                    'risk_score' => $risk['score'], 'risk_signals' => $risk['signals'],
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

    public function reviewIdentity(UserIdentity $identity, bool $ok, ?Admin $admin, ?string $reason = null): void
    {
        $identity->forceFill(['status' => $ok ? UserIdentity::VERIFIED : UserIdentity::REJECTED, 'rejection_reason' => $ok ? null : $reason,
            'reviewed_by' => $admin?->id, 'reviewed_at' => now()])->save();
        $identity->loadMissing('user');
        $this->audit->log($ok ? 'cashout.identity_verified' : 'cashout.identity_rejected', $identity->user, meta: ['reason' => $reason], actor: $admin);
        $identity->user->notify(new UserNotification('order_update', $ok ? 'هویت تأیید شد' : 'هویت تأیید نشد',
            $ok ? 'مشخصات هویتی‌ات تأیید شد.' : 'مشخصات هویتی تأیید نشد: '.$reason, ['type' => 'cashout']));
    }

    public function reviewBankAccount(BankAccount $account, bool $ok, ?Admin $admin, ?string $reason = null): void
    {
        $account->forceFill(['status' => $ok ? BankAccount::VERIFIED : BankAccount::REJECTED, 'rejection_reason' => $ok ? null : $reason,
            'reviewed_by' => $admin?->id, 'reviewed_at' => now()])->save();
        $this->audit->log($ok ? 'cashout.bank_account_verified' : 'cashout.bank_account_rejected', $account, meta: ['reason' => $reason], actor: $admin);
        $account->loadMissing('user')->user->notify(new UserNotification('order_update', $ok ? 'حساب بانکی تأیید شد' : 'حساب بانکی تأیید نشد',
            $ok ? 'حساب '.$account->bank_name.' برای برداشت تأیید شد.' : 'حساب بانکی تأیید نشد: '.$reason, ['type' => 'cashout']));
    }

    public function approve(CashoutRequest $request, Admin $admin): CashoutRequest
    {
        return DB::transaction(function () use ($request, $admin) {
            $locked = CashoutRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== CashoutRequest::PENDING) {
                throw ApiException::conflict('invalid_transition', 'این درخواست دیگر قابل تأیید نیست.');
            }
            foreach ($this->budget() as $period => $b) {
                if ($b['limit'] > 0 && $b['limit'] < $b['used'] + $locked->amount_rial) {
                    throw ApiException::conflict('budget_exceeded', 'سقف '.($period === 'daily' ? 'روزانه' : 'ماهانه').' پرداخت پر است (باقی‌مانده '
                        .number_format(max(0, $b['limit'] - $b['used'])).' ریال). درخواست در صف می‌ماند.');
                }
            }
            $locked->forceFill(['status' => CashoutRequest::APPROVED, 'approved_by' => $admin->id, 'approved_at' => now()])->save();
            $this->audit->log('cashout.approved', $locked, actor: $admin);

            return $locked;
        });
    }

    /**
     * Platform-wide payout budget, counted on approval (Tehran day and Jalali month).
     *
     * @return array{daily: array{limit: int, used: int}, monthly: array{limit: int, used: int}}
     */
    public function budget(): array
    {
        $tz = config('walk.panel_timezone');
        $now = now($tz);
        [$monthStart] = Jalali::monthRange($now);
        $used = fn ($from) => (int) CashoutRequest::query()->whereIn('status', [CashoutRequest::APPROVED, CashoutRequest::PROCESSING, CashoutRequest::PAID])
            ->where('approved_at', '>=', $from)->sum('amount_rial');

        return [
            'daily' => ['limit' => $this->settings->int('cashout.daily_budget_rial'), 'used' => $used($now->copy()->startOfDay()->utc())],
            'monthly' => ['limit' => $this->settings->int('cashout.monthly_budget_rial'), 'used' => $used(CarbonImmutable::parse($monthStart, $tz)->startOfDay()->utc())],
        ];
    }

    public function reassess(CashoutRequest $request): CashoutRequest
    {
        $risk = $this->risk->assess($request->loadMissing('user')->user);
        $request->forceFill(['risk_score' => $risk['score'], 'risk_signals' => $risk['signals']])->save();

        return $request;
    }

    /**
     * Sends an approved payout through the settlement API. The sender plays the
     * "second pair of eyes", so it can't be the approver either.
     */
    public function sendToBank(CashoutRequest $request, Admin $admin): CashoutRequest
    {
        $payouts = app(PayoutProvider::class);
        if (! $payouts->automatic()) {
            throw ApiException::conflict('payout_manual', 'انتقال خودکار بانکی فعال نیست؛ از خروجی CSV و «ثبت واریز» استفاده کن.');
        }
        $request = DB::transaction(function () use ($request, $admin, $payouts) {
            $locked = CashoutRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== CashoutRequest::APPROVED) {
                throw ApiException::conflict('invalid_transition', 'فقط درخواست تأییدشده قابل ارسال به بانک است.');
            }
            if ($locked->approved_by === $admin->id) {
                throw ApiException::forbidden('four_eyes', 'ارسال به بانک باید توسط مدیری غیر از تأییدکننده انجام شود.');
            }
            $locked->forceFill(['status' => CashoutRequest::PROCESSING, 'payout_provider' => $payouts->name(), 'payout_track_id' => (string) Str::uuid(),
                'payout_state' => 'sending', 'payout_error' => null, 'sent_by' => $admin->id, 'sent_at' => now()])->save();
            $this->audit->log('cashout.sent_to_bank', $locked, new: ['provider' => $payouts->name(), 'track_id' => $locked->payout_track_id], actor: $admin);

            return $locked;
        });
        // Outside the lock: a slow bank must not hold the row. A lost response is recovered by syncPayouts().
        try {
            $result = $payouts->send($request->load('bankAccount'), $request->payout_track_id);
        } catch (\Throwable $e) {
            report($e);
            $result = ['state' => 'processing', 'reference' => null, 'error' => null];
        }

        return $this->applyPayoutResult($request, $result);
    }

    /** Follows transfers in flight; run every few minutes by the scheduler. */
    public function syncPayouts(): int
    {
        $payouts = app(PayoutProvider::class);
        $n = 0;
        foreach (CashoutRequest::query()->where('status', CashoutRequest::PROCESSING)->whereNotNull('payout_track_id')->get() as $request) {
            try {
                $result = $payouts->status($request->payout_track_id);
            } catch (\Throwable $e) {
                report($e);

                continue;
            }
            if ($result === null) {
                // The provider never received it: after a safe delay, hand it back to finance.
                if ($request->sent_at->lt(now()->subMinutes(30))) {
                    $result = ['state' => 'failed', 'reference' => null, 'error' => 'not_received'];
                } else {
                    continue;
                }
            }
            $this->applyPayoutResult($request, $result);
            $n++;
        }

        return $n;
    }

    /** @param array{state: string, reference: ?string, error: ?string} $result */
    private function applyPayoutResult(CashoutRequest $request, array $result): CashoutRequest
    {
        if ($result['state'] === 'transferred') {
            return $this->finalizePaid($request, $request->loadMissing('sender')->sender ?? Admin::query()->findOrFail($request->sent_by), (string) ($result['reference'] ?: $request->payout_track_id), CashoutRequest::PROCESSING);
        }
        if ($result['state'] === 'failed') {
            $request->forceFill(['status' => CashoutRequest::APPROVED, 'payout_state' => 'failed', 'payout_error' => mb_substr((string) $result['error'], 0, 120),
                'sent_by' => null])->save();
            $this->audit->log('cashout.transfer_failed', $request, meta: ['error' => $result['error']]);
            app(OpsAlerter::class)->alert('cashout-transfer-failed:'.$request->id, 'انتقال بانکی برداشت ناموفق بود',
                CashoutRequestNumber::of($request).': '.$result['error'].'. درخواست به «تأییدشده» برگشت؛ دوباره ارسال یا رد کنید.', 'cashout.manage');

            return $request;
        }
        $request->forceFill(['payout_state' => 'processing'])->save();

        return $request;
    }

    /** Four eyes: whoever approved can't also confirm the money left the bank. */
    public function markPaid(CashoutRequest $request, Admin $admin, string $bankReference): CashoutRequest
    {
        return $this->finalizePaid($request, $admin, $bankReference, CashoutRequest::APPROVED);
    }

    private function finalizePaid(CashoutRequest $request, Admin $admin, string $bankReference, string $from): CashoutRequest
    {
        $request = DB::transaction(function () use ($request, $admin, $bankReference, $from) {
            $locked = CashoutRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== $from) {
                throw ApiException::conflict('invalid_transition', 'فقط درخواست تأییدشده قابل ثبت واریز است.');
            }
            if ($locked->approved_by === $admin->id) {
                throw ApiException::forbidden('four_eyes', 'ثبت واریز باید توسط مدیری غیر از تأییدکننده انجام شود.');
            }
            $locked->forceFill(['status' => CashoutRequest::PAID, 'paid_by' => $admin->id, 'paid_at' => now(), 'bank_reference' => $bankReference,
                'payout_state' => $locked->payout_track_id ? 'transferred' : null])->save();
            $this->audit->log('cashout.paid', $locked, new: ['bank_reference' => $bankReference, 'amount_rial' => $locked->amount_rial], actor: $admin);

            return $locked;
        });
        $request->loadMissing('user')->user->notify(new UserNotification('order_update', 'واریز انجام شد',
            number_format($request->amount_rial).' ریال به حسابت واریز شد. کد پیگیری: '.$bankReference, ['type' => 'cashout']));

        return $request;
    }

    public function reject(CashoutRequest $request, Admin $admin, string $reason): CashoutRequest
    {
        // Not while the bank is moving the money: wait for the transfer result first.
        return $this->close($request, CashoutRequest::REJECTED, $reason, $admin, [CashoutRequest::PENDING, CashoutRequest::APPROVED]);
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
            $closed->loadMissing('user')->user->notify(new UserNotification('order_update', 'درخواست برداشت رد شد',
                'امتیازها به کیف پولت برگشت. دلیل: '.$reason, ['type' => 'cashout']));
        }

        return $closed;
    }
}
