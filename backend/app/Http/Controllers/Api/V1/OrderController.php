<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Store\OrderService;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Presenters\StorePresenter;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly StorePresenter $present) {}

    public function index(Request $request): JsonResponse
    {
        $page = Order::query()->where('user_id', $request->user()->id)->with('items')->orderByDesc('id')->cursorPaginate(20);

        return response()->json([
            'data' => collect($page->items())->map(fn (Order $o) => $this->present->order($o))->values(),
            'meta' => ['next_cursor' => $page->nextCursor()?->encode()],
        ]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return response()->json(['data' => $this->present->order($order->load(['items.codes', 'items.userCoupon', 'items.product.images', 'history']), full: true)]);
    }

    public function store(Request $request, OrderService $orders): JsonResponse
    {
        $key = (string) $request->header('Idempotency-Key');
        if (! preg_match('/^[A-Za-z0-9-]{16,64}$/', $key)) {
            throw ApiException::unprocessable('idempotency_key_required', 'درخواست نامعتبر است.');
        }
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:10'],
            'items.*.product_id' => ['required', 'string', 'size:26'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'address_id' => ['nullable', 'string', 'size:26'],
            'payment_mode' => ['nullable', 'in:points,money'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        // Prices come from the database; the client only says what and how many.
        [$order, $created] = $orders->place($request->user(), $data['items'], $key, $data['address_id'] ?? null, $data['payment_mode'] ?? 'points', $data['note'] ?? null);

        return response()->json(['data' => $this->present->order($order->load(['items.codes', 'items.userCoupon', 'items.product.images', 'history']), full: true)], $created ? 201 : 200);
    }

    public function cancel(Request $request, Order $order, OrderService $orders): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        $order = $orders->cancelByUser($request->user(), $order);

        return response()->json(['data' => $this->present->order($order->load(['items.codes', 'items.userCoupon', 'items.product.images', 'history']), full: true)]);
    }
}
