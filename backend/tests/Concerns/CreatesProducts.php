<?php

namespace Tests\Concerns;

use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCode;
use Illuminate\Support\Str;

trait CreatesProducts
{
    protected function product(array $overrides = []): Product
    {
        $category = Category::query()->firstOrCreate(['slug' => 'gift-cards'], ['name' => 'کارت هدیه']);
        $name = $overrides['name'] ?? 'کارت هدیه '.Str::random(4);

        return Product::query()->create([
            'category_id' => $category->id, 'name' => $name, 'slug' => Str::slug(Str::random(10)), 'type' => ProductType::Physical,
            'point_price' => 100, 'stock' => 10, 'is_active' => true, ...$overrides,
        ]);
    }

    protected function codeProduct(int $codes, array $overrides = []): Product
    {
        $p = $this->product(['type' => ProductType::DigitalCode, 'stock' => 0, ...$overrides]);
        foreach (range(1, $codes) as $i) {
            $code = 'GIFT-'.$p->id.'-'.$i;
            ProductCode::query()->create(['product_id' => $p->id, 'code' => $code, 'code_hash' => ProductCode::hashOf($code)]);
        }
        $p->syncCodeStock();

        return $p->fresh();
    }
}
