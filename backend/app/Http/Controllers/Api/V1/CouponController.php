<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sponsor\CouponService;
use App\Enums\CouponStatus;
use App\Enums\SponsorStatus;
use App\Enums\UserCouponStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Presenters\SponsorPresenter;
use App\Models\Coupon;
use App\Models\UserCoupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function __construct(private readonly SponsorPresenter $present) {}

    /** ?tab=mine (default) | available */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($request->query('tab') === 'available') {
            $coupons = Coupon::query()
                ->where('status', CouponStatus::Active)->where('claimable', true)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->where(fn ($q) => $q->whereNull('usage_limit')->orWhereColumn('claimed_count', '<', 'usage_limit'))
                ->where(fn ($q) => $q->whereNull('sponsor_id')->orWhereHas('sponsor', fn ($s) => $s->where('status', SponsorStatus::Approved)))
                ->with('sponsor')->orderBy('point_cost')->limit(100)->get();

            return response()->json(['data' => $coupons->map(fn (Coupon $c) => $this->present->couponOffer($c))->values()]);
        }

        $mine = UserCoupon::query()->where('user_id', $user->id)->with('coupon.sponsor')
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [UserCouponStatus::Available->value])
            ->latest('id')->limit(200)->get();

        return response()->json(['data' => $mine->map(fn (UserCoupon $u) => $this->present->userCoupon($u))->values()]);
    }

    public function show(Request $request, UserCoupon $userCoupon): JsonResponse
    {
        abort_unless($userCoupon->user_id === $request->user()->id, 404);

        return response()->json(['data' => $this->present->userCoupon($userCoupon->load('coupon.sponsor'))]);
    }

    public function claim(Request $request, Coupon $coupon, CouponService $service): JsonResponse
    {
        abort_unless($coupon->status === CouponStatus::Active, 404);
        $key = (string) $request->header('Idempotency-Key');
        if (! preg_match('/^[A-Za-z0-9-]{16,64}$/', $key)) {
            throw ApiException::unprocessable('idempotency_key_required', 'درخواست نامعتبر است.');
        }
        $userCoupon = $service->claim($request->user(), $coupon->load('sponsor'), $key);

        return response()->json(['data' => $this->present->userCoupon($userCoupon->load('coupon.sponsor'))], $userCoupon->wasRecentlyCreated ? 201 : 200);
    }
}
