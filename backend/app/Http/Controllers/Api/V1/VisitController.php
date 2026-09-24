<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sponsor\VisitService;
use App\Http\Controllers\Controller;
use App\Http\Presenters\SponsorPresenter;
use App\Models\Campaign;
use App\Models\Location;
use App\Models\UserCoupon;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitController extends Controller
{
    public function __construct(private readonly VisitService $visits, private readonly SponsorPresenter $present) {}

    public function index(Request $request): JsonResponse
    {
        $list = Visit::query()->where('user_id', $request->user()->id)->with(['campaign', 'location'])->latest('id')->limit(50)->get();

        return response()->json(['data' => $list->map(fn (Visit $v) => $this->present->visit($v))->values()]);
    }

    public function show(Request $request, Visit $visit): JsonResponse
    {
        return $this->respond($this->owned($request, $visit));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'campaign_id' => ['required', 'string', 'size:26'],
            'location_id' => ['required', 'string', 'size:26'],
            ...$this->fixRules(),
        ]);
        $campaign = Campaign::query()->where('public_id', $data['campaign_id'])->firstOrFail();
        $location = Location::query()->where('public_id', $data['location_id'])->firstOrFail();

        $visit = $this->visits->start($request->user(), $request->attributes->get('device'), $campaign, $location, $this->fix($data));

        return $this->respond($visit, $visit->wasRecentlyCreated ? 201 : 200);
    }

    public function ping(Request $request, Visit $visit): JsonResponse
    {
        $data = $request->validate($this->fixRules());

        return $this->respond($this->visits->ping($this->owned($request, $visit), $this->fix($data)));
    }

    public function qr(Request $request, Visit $visit): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:200']]);

        return $this->respond($this->visits->submitQr($this->owned($request, $visit), $data['token']));
    }

    private function owned(Request $request, Visit $visit): Visit
    {
        abort_unless($visit->user_id === $request->user()->id, 404);

        return $visit;
    }

    private function respond(Visit $visit, int $status = 200): JsonResponse
    {
        $visit->loadMissing(['campaign', 'location']);
        $coupon = UserCoupon::query()->where('user_id', $visit->user_id)->where('source_type', 'visit')->where('source_id', $visit->id)->with('coupon.sponsor')->first();

        return response()->json(['data' => $this->present->visit($visit, $coupon)], $status);
    }

    private function fixRules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0', 'max:5000'],
            'mock' => ['sometimes', 'boolean'],
        ];
    }

    private function fix(array $data): array
    {
        return ['lat' => (float) $data['lat'], 'lng' => (float) $data['lng'], 'accuracy' => (float) $data['accuracy'], 'mock' => (bool) ($data['mock'] ?? false)];
    }
}
