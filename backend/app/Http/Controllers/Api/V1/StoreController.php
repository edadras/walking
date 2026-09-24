<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Presenters\StorePresenter;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function __construct(private readonly StorePresenter $present) {}

    public function categories(): JsonResponse
    {
        $categories = Category::query()->where('is_active', true)->with('parent')->orderBy('sort')->orderBy('name')->get();

        return response()->json(['data' => $categories->map(fn (Category $c) => $this->present->category($c))->values()]);
    }

    public function products(Request $request): JsonResponse
    {
        $data = $request->validate(['category' => ['nullable', 'string', 'max:100'], 'q' => ['nullable', 'string', 'max:60'], 'sort' => ['nullable', 'in:featured,price_asc,price_desc,newest']]);

        $query = Product::query()->where('is_active', true)->with(['images', 'category', 'sponsor']);
        if (! empty($data['category'])) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $data['category'])->orWhereHas('parent', fn ($p) => $p->where('slug', $data['category'])));
        }
        if (! empty($data['q'])) {
            $query->where('name', 'like', '%'.addcslashes($data['q'], '%_\\').'%');
        }
        match ($data['sort'] ?? 'featured') {
            'price_asc' => $query->orderBy('point_price')->orderBy('id'),
            'price_desc' => $query->orderByDesc('point_price')->orderBy('id'),
            'newest' => $query->orderByDesc('id'),
            default => $query->orderBy('sort')->orderByDesc('sold_count')->orderBy('id'),
        };
        $page = $query->cursorPaginate(20);

        return response()->json([
            'data' => collect($page->items())->map(fn (Product $p) => $this->present->product($p))->values(),
            'meta' => ['next_cursor' => $page->nextCursor()?->encode()],
        ]);
    }

    public function product(Product $product): JsonResponse
    {
        abort_unless($product->is_active, 404);

        return response()->json(['data' => $this->present->product($product->load(['images', 'category', 'sponsor']), full: true)]);
    }
}
