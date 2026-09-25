<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Wallet\WalletSummary;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PointTransactionResource;
use App\Models\PointTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WalletController extends Controller
{
    public function show(Request $request, WalletSummary $summary): JsonResponse
    {
        return response()->json(['data' => $summary->for($request->user())]);
    }

    public function transactions(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'filter' => ['sometimes', 'in:all,earned,spent,purchase,reward,sponsor,adjustment,cashout'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
        ]);

        $groups = fn (string $group) => array_values(array_map(fn (TransactionType $t) => $t->value, array_filter(TransactionType::cases(), fn (TransactionType $t) => $t->group() === $group)));

        $query = PointTransaction::query()->where('user_id', $request->user()->id);
        match ($data['filter'] ?? 'all') {
            'earned' => $query->where('amount', '>', 0),
            'spent' => $query->where('amount', '<', 0),
            'purchase' => $query->where('type', TransactionType::Purchase),
            'reward' => $query->whereIn('type', $groups('reward')),
            'sponsor' => $query->whereIn('type', $groups('sponsor')),
            'adjustment' => $query->whereIn('type', $groups('adjustment')),
            'cashout' => $query->whereIn('type', $groups('cashout')),
            default => null,
        };

        return PointTransactionResource::collection(
            $query->orderByDesc('id')->cursorPaginate($request->integer('per_page', 20))
        );
    }
}
