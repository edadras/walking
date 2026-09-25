<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\OtpService;
use App\Domain\Cashout\CashoutService;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\CashoutRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Points → bank payout. Every write is device-signed and confirmed with a cash-out SMS code. */
class CashoutController extends Controller
{
    public function __construct(private readonly CashoutService $cashout) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->cashout->overview($request->user()->load('wallet'))]);
    }

    /** Sends a code valid only for cash-out steps, to the account's own number. */
    public function otp(Request $request, OtpService $otp): JsonResponse
    {
        $user = $request->user();
        if (! $this->cashout->enabled($user)) {
            throw ApiException::forbidden('feature_disabled', 'برداشت نقدی در حال حاضر فعال نیست.');
        }

        return response()->json(['data' => $otp->request($user->phone, $request->attributes->get('device'), $request->ip(), 'cashout')]);
    }

    public function identity(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[\x{0600}-\x{06FF}\x{200C}\s]+$/u'],
            'last_name' => ['required', 'string', 'min:2', 'max:60', 'regex:/^[\x{0600}-\x{06FF}\x{200C}\s]+$/u'],
            'national_code' => ['required', 'string', 'max:20'],
            'birth_date' => ['required', 'date_format:Y-m-d', 'before:today', 'after:1900-01-01'],
            'code' => ['required', 'string', 'max:10'],
        ], [
            'first_name.regex' => 'نام را با حروف فارسی و مطابق کارت ملی وارد کن.',
            'last_name.regex' => 'نام خانوادگی را با حروف فارسی و مطابق کارت ملی وارد کن.',
        ]);
        $this->cashout->submitIdentity($request->user(), $data, $data['code']);

        return $this->show($request);
    }

    public function addBankAccount(Request $request): JsonResponse
    {
        $data = $request->validate(['sheba' => ['required', 'string', 'max:40'], 'code' => ['required', 'string', 'max:10']]);
        $this->cashout->addBankAccount($request->user(), $data['sheba'], $data['code']);

        return response()->json($this->show($request)->getData(true), 201);
    }

    public function removeBankAccount(Request $request, BankAccount $bankAccount): JsonResponse
    {
        $this->cashout->removeBankAccount($request->user(), $bankAccount);

        return $this->show($request);
    }

    public function store(Request $request): JsonResponse
    {
        $key = (string) $request->header('Idempotency-Key');
        if (! preg_match('/^[A-Za-z0-9-]{16,64}$/', $key)) {
            throw ApiException::unprocessable('idempotency_key_required', 'درخواست نامعتبر است.');
        }
        $data = $request->validate([
            'bank_account_id' => ['required', 'string', 'size:26'],
            'points' => ['required', 'integer', 'min:1'],
            'code' => ['required', 'string', 'max:10'],
        ]);
        $cashout = $this->cashout->request($request->user(), $data['bank_account_id'], (int) $data['points'], $data['code'], $key);

        return response()->json(['data' => $this->cashout->present($cashout->load('bankAccount'))], $cashout->wasRecentlyCreated ? 201 : 200);
    }

    public function cancel(Request $request, CashoutRequest $cashoutRequest): JsonResponse
    {
        $cashout = $this->cashout->cancel($request->user(), $cashoutRequest);

        return response()->json(['data' => $this->cashout->present($cashout->load('bankAccount'))]);
    }
}
