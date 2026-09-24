<?php

namespace Tests\Feature\Store;

use App\Domain\Sponsor\CouponService;
use App\Domain\Store\OrderService;
use App\Domain\Wallet\WalletService;
use App\Enums\AdminRole;
use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Enums\TransactionType;
use App\Enums\UserCouponStatus;
use App\Exceptions\ApiException;
use App\Models\Admin;
use App\Models\Order;
use App\Models\PointTransaction;
use App\Models\User;
use App\Models\UserCoupon;
use Carbon\CarbonImmutable;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesProducts;
use Tests\Concerns\CreatesSponsorOffers;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

class StoreApiTest extends TestCase
{
    use CreatesProducts, CreatesSponsorOffers, RefreshDatabase, SignsDeviceRequests;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 12:00:00', 'Asia/Tehran')->utc());
        $this->seed(PlatformSeeder::class);
    }

    private function fund(User $user, int $points): void
    {
        app(WalletService::class)->credit($user, $points, TransactionType::Adjustment, 'fund:'.Str::uuid(), 'test');
    }

    private function buy(array $items, array $extra = [], ?string $key = null)
    {
        return $this->signedJson('POST', '/api/v1/orders', ['items' => $items, ...$extra], ['Idempotency-Key' => $key ?? (string) Str::uuid()]);
    }

    private function address(): string
    {
        return $this->authedJson('POST', '/api/v1/addresses', ['recipient' => 'مریم', 'phone' => '09121234567', 'province' => 'تهران', 'city' => 'تهران', 'line' => 'خیابان آزادی، پلاک ۱', 'postal_code' => '1234567890'])
            ->assertCreated()->assertJsonPath('data.is_default', true)->json('data.id');
    }

    public function test_catalogue_browsing(): void
    {
        $this->loginAs();
        $this->product(['name' => 'کوله‌پشتی', 'point_price' => 900]);
        $this->product(['name' => 'بطری آب', 'point_price' => 300]);
        $this->product(['name' => 'پنهان', 'is_active' => false]);

        $this->authedJson('GET', '/api/v1/store/categories')->assertOk()->assertJsonPath('data.0.id', 'gift-cards');
        $list = $this->authedJson('GET', '/api/v1/store/products?sort=price_asc')->assertOk();
        $this->assertSame(['بطری آب', 'کوله‌پشتی'], array_column($list->json('data'), 'name'));
        $this->authedJson('GET', '/api/v1/store/products?q=بطری')->assertJsonCount(1, 'data');
        $slug = $list->json('data.0.slug');
        $this->authedJson('GET', "/api/v1/store/products/{$slug}")->assertOk()->assertJsonPath('data.needs_address', true);
    }

    public function test_physical_purchase_is_atomic_idempotent_and_uses_server_prices(): void
    {
        $user = $this->loginAs();
        $this->fund($user, 500);
        $product = $this->product(['point_price' => 200, 'stock' => 5]);

        $this->buy([['product_id' => $product->public_id, 'quantity' => 1]])->assertStatus(422)->assertJsonPath('error.code', 'address_required');
        $address = $this->address();

        $key = (string) Str::uuid();
        $first = $this->buy([['product_id' => $product->public_id, 'quantity' => 2]], ['address_id' => $address, 'unit_point_price' => 1], $key)
            ->assertCreated()->assertJsonPath('data.total_points', 400)->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.shipping_address.recipient', 'مریم');
        $this->buy([['product_id' => $product->public_id, 'quantity' => 2]], ['address_id' => $address], $key)->assertOk()->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertSame(100, $user->wallet->fresh()->available_balance);
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame(1, Order::query()->count());

        // Not enough points: nothing changes.
        $this->buy([['product_id' => $product->public_id, 'quantity' => 1]], ['address_id' => $address])->assertStatus(409)->assertJsonPath('error.code', 'insufficient_points');
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame(1, Order::query()->count());
    }

    public function test_digital_codes_are_delivered_once_and_only_to_the_buyer(): void
    {
        $user = $this->loginAs('09121111111');
        $this->fund($user, 1000);
        $product = $this->codeProduct(2, ['point_price' => 150]);
        $this->assertSame(2, $product->stock);

        $id = $this->buy([['product_id' => $product->public_id, 'quantity' => 2]])->assertCreated()
            ->assertJsonPath('data.status', 'delivered')->json('data.id');
        $codes = collect($this->authedJson('GET', "/api/v1/orders/{$id}")->json('data.items.0.codes'))->pluck('code');
        $this->assertCount(2, $codes);
        $this->assertCount(2, $codes->unique());
        $this->assertSame(0, $product->fresh()->stock);

        $this->buy([['product_id' => $product->public_id, 'quantity' => 1]])->assertStatus(409)->assertJsonPath('error.code', 'out_of_stock');

        $this->device = null;
        $this->deviceKey = null;
        $this->loginAs('09122222222');
        $this->authedJson('GET', "/api/v1/orders/{$id}")->assertNotFound();
    }

    public function test_coupon_products_issue_a_sponsor_coupon(): void
    {
        $user = $this->loginAs();
        $this->fund($user, 500);
        $coupon = $this->coupon($this->sponsor());
        $product = $this->product(['type' => ProductType::Coupon, 'coupon_id' => $coupon->id, 'stock' => null, 'point_price' => 120]);

        $res = $this->buy([['product_id' => $product->public_id, 'quantity' => 1]])->assertCreated()->assertJsonPath('data.status', 'delivered');
        $this->assertNotNull($res->json('data.items.0.coupon_id'));
        $this->assertSame(1, UserCoupon::query()->where('user_id', $user->id)->count());
    }

    public function test_limits_levels_and_flags(): void
    {
        $user = $this->loginAs();
        $this->fund($user, 5000);
        $address = $this->address();
        $once = $this->product(['max_per_user' => 1]);
        $elite = $this->product(['min_level' => 10]);

        $this->buy([['product_id' => $once->public_id, 'quantity' => 1]], ['address_id' => $address])->assertCreated();
        $this->buy([['product_id' => $once->public_id, 'quantity' => 1]], ['address_id' => $address])->assertStatus(409)->assertJsonPath('error.code', 'limit_reached');
        $this->buy([['product_id' => $elite->public_id, 'quantity' => 1]], ['address_id' => $address])->assertStatus(422)->assertJsonPath('error.code', 'level_required');
        $this->buy([['product_id' => $once->public_id, 'quantity' => 1]], ['address_id' => $address, 'payment_mode' => 'money'])->assertStatus(422)->assertJsonPath('error.code', 'payment_unavailable');
        $this->signedJson('POST', '/api/v1/orders', ['items' => [['product_id' => $once->public_id, 'quantity' => 1]]])->assertStatus(422)->assertJsonPath('error.code', 'idempotency_key_required');
    }

    public function test_user_cancel_and_admin_fulfilment_with_refund(): void
    {
        $user = $this->loginAs();
        $this->fund($user, 1000);
        $address = $this->address();
        $product = $this->product(['point_price' => 300, 'stock' => 4]);

        $a = $this->buy([['product_id' => $product->public_id, 'quantity' => 1]], ['address_id' => $address])->json('data.id');
        $this->signedJson('POST', "/api/v1/orders/{$a}/cancel")->assertOk()->assertJsonPath('data.status', 'refunded');
        $this->assertSame(1000, $user->wallet->fresh()->available_balance);
        $this->assertSame(4, $product->fresh()->stock);

        $b = Order::query()->where('public_id', $this->buy([['product_id' => $product->public_id, 'quantity' => 1]], ['address_id' => $address])->json('data.id'))->sole();
        $admin = Admin::factory()->role(AdminRole::StoreManager)->create();
        $orders = app(OrderService::class);
        $orders->transition($b, OrderStatus::Processing, $admin);
        $this->signedJson('POST', "/api/v1/orders/{$b->public_id}/cancel")->assertStatus(409);
        $orders->transition($b->fresh(), OrderStatus::Shipped, $admin, tracking: 'PKG123');
        $this->authedJson('GET', "/api/v1/orders/{$b->public_id}")->assertJsonPath('data.tracking_code', 'PKG123')
            ->assertJsonPath('data.history.2.status', 'shipped');

        $orders->transition($b->fresh(), OrderStatus::Refunded, $admin, 'کالا آسیب دید');
        $this->assertSame(1000, $user->wallet->fresh()->available_balance);
        $this->assertSame(2, PointTransaction::query()->where('user_id', $user->id)->where('type', TransactionType::Refund)->count());

        $this->expectException(ApiException::class);
        $orders->transition($b->fresh(), OrderStatus::Shipped, $admin);
    }

    public function test_used_coupon_orders_cannot_be_refunded(): void
    {
        $user = $this->loginAs();
        $this->fund($user, 500);
        $sponsor = $this->sponsor();
        $product = $this->product(['type' => ProductType::Coupon, 'coupon_id' => $this->coupon($sponsor)->id, 'stock' => null]);
        $id = $this->buy([['product_id' => $product->public_id, 'quantity' => 1]])->json('data.id');
        $uc = UserCoupon::query()->where('user_id', $user->id)->sole();
        app(CouponService::class)->redeem($this->sponsorUser($sponsor), $uc->code);

        try {
            app(OrderService::class)->transition(Order::query()->where('public_id', $id)->sole(), OrderStatus::Refunded, Admin::factory()->create());
            $this->fail('refund of a used coupon');
        } catch (ApiException $e) {
            $this->assertSame('coupon_used', $e->errorCode);
        }
        $this->assertSame(UserCouponStatus::Used, $uc->fresh()->status);
    }

    public function test_address_book(): void
    {
        $this->loginAs();
        $first = $this->address();
        $second = $this->authedJson('POST', '/api/v1/addresses', ['recipient' => 'علی', 'phone' => '09120000000', 'province' => 'فارس', 'city' => 'شیراز', 'line' => 'زند', 'postal_code' => '7134567890', 'is_default' => true])->json('data.id');
        $list = $this->authedJson('GET', '/api/v1/addresses')->json('data');
        $this->assertSame([$second, $first], array_column($list, 'id'));
        $this->assertSame([true, false], array_column($list, 'is_default'));
        $this->authedJson('POST', '/api/v1/addresses', ['recipient' => 'x', 'phone' => '123', 'province' => 'a', 'city' => 'b', 'line' => 'c', 'postal_code' => '1'])->assertStatus(422);
        $this->authedJson('DELETE', "/api/v1/addresses/{$first}")->assertOk();
        $this->authedJson('GET', '/api/v1/addresses')->assertJsonCount(1, 'data');
    }
}
