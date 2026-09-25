<?php

namespace Tests\Feature\Store;

use App\Domain\Settings\FeatureFlags;
use App\Domain\Store\Exceptions\GatewayUnavailable;
use App\Domain\Store\PaymentGateway;
use App\Domain\Store\ZarinpalGateway;
use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Models\FeatureFlag;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PointTransaction;
use Carbon\CarbonImmutable;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesProducts;
use Tests\Concerns\SignsDeviceRequests;
use Tests\Fakes\FakeGateway;
use Tests\TestCase;

class RialPaymentTest extends TestCase
{
    use CreatesProducts, RefreshDatabase, SignsDeviceRequests;

    private FakeGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 12:00:00', 'Asia/Tehran')->utc());
        $this->seed(PlatformSeeder::class);
        foreach (['store', 'money_payment'] as $flag) {
            FeatureFlag::query()->updateOrCreate(['key' => $flag], ['is_enabled' => true, 'rollout_percent' => 100]);
        }
        app(FeatureFlags::class)->flush();
        $this->gateway = new FakeGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);
    }

    private function buy(string $productId, int $qty = 1)
    {
        return $this->signedJson('POST', '/api/v1/orders', ['items' => [['product_id' => $productId, 'quantity' => $qty]], 'payment_mode' => 'money'], ['Idempotency-Key' => (string) Str::uuid()]);
    }

    private function bankReturn(string $authority, string $status = 'OK')
    {
        return $this->get("/payments/zarinpal/callback?Authority={$authority}&Status={$status}");
    }

    public function test_paid_order_is_verified_with_our_amount_then_delivered(): void
    {
        $this->loginAs();
        $card = $this->codeProduct(3, ['rial_price' => 500_000]);

        $this->authedJson('GET', "/api/v1/store/products/{$card->slug}")->assertJsonPath('data.rial_price', 500_000);
        $res = $this->buy($card->public_id, 2)->assertCreated()
            ->assertJsonPath('data.status', 'awaiting_payment')->assertJsonPath('data.total_rial', 1_000_000)
            ->assertJsonPath('data.payment.status', 'pending');
        $authority = $this->gateway->requested[0]['authority'];
        $res->assertJsonPath('data.payment.pay_url', 'https://pay.test/'.$authority);
        $this->assertSame(1, $card->fresh()->stock, 'stock is held while paying');
        $this->assertSame(0, PointTransaction::query()->count(), 'no points involved');

        // A tampered "OK" without a real payment is not enough.
        $this->bankReturn($authority)->assertOk()->assertSee('پرداخت انجام نشد');
        $this->assertSame(OrderStatus::Cancelled, Order::query()->sole()->status);
        $this->assertSame(3, $card->fresh()->stock, 'released');

        // Second attempt, actually paid.
        $id = $this->buy($card->public_id, 2)->json('data.id');
        $authority = $this->gateway->requested[1]['authority'];
        $this->gateway->verified[$authority] = 1_000_000;
        $this->bankReturn($authority)->assertOk()->assertSee('پرداخت با موفقیت انجام شد')->assertSee('gamyar://app/orders/'.$id, false);
        $this->bankReturn($authority)->assertOk()->assertSee('پرداخت با موفقیت انجام شد');

        $order = $this->authedJson('GET', "/api/v1/orders/{$id}")->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.payment.status', 'paid')->assertJsonPath('data.payment.ref_id', 'REF'.substr($authority, -3))
            ->assertJsonPath('data.payment.pay_url', null);
        $this->assertCount(2, $order->json('data.items.0.codes'));
        $this->assertSame(1, Payment::query()->where('status', 'paid')->count());
    }

    public function test_verification_uses_the_stored_amount_not_the_request(): void
    {
        $this->loginAs();
        $p = $this->product(['type' => ProductType::Service, 'rial_price' => 90_000]);
        $this->buy($p->public_id);
        $authority = $this->gateway->requested[0]['authority'];
        $this->gateway->verified[$authority] = 1_000; // paid less than due

        $this->bankReturn($authority)->assertSee('پرداخت انجام نشد');
        $this->assertSame(OrderStatus::Cancelled, Order::query()->sole()->status);
    }

    public function test_lost_callback_is_recovered_by_the_sweep_and_unknown_outcomes_wait(): void
    {
        $this->loginAs();
        $p = $this->product(['type' => ProductType::Service, 'rial_price' => 90_000, 'stock' => 5]);
        $paid = $this->buy($p->public_id)->json('data.id');
        $abandoned = $this->buy($p->public_id)->json('data.id');
        $this->gateway->verified[$this->gateway->requested[0]['authority']] = 90_000;

        $this->travel(25)->minutes();
        $this->gateway->down = true;
        $this->artisan('payments:sweep')->assertSuccessful();
        $this->assertSame(2, Payment::query()->where('status', 'pending')->count(), 'never release while the gateway is unreachable');

        $this->gateway->down = false;
        $this->artisan('payments:sweep')->assertSuccessful();
        $this->assertSame(OrderStatus::Paid, Order::query()->where('public_id', $paid)->sole()->status);
        $this->assertSame(OrderStatus::Cancelled, Order::query()->where('public_id', $abandoned)->sole()->status);
        $this->assertSame(4, $p->fresh()->stock);
    }

    public function test_user_can_cancel_before_paying_but_not_after(): void
    {
        $this->loginAs();
        $p = $this->product(['type' => ProductType::Service, 'rial_price' => 90_000, 'stock' => 5]);
        $id = $this->buy($p->public_id)->json('data.id');
        $this->signedJson('POST', "/api/v1/orders/{$id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(5, $p->fresh()->stock);
        $this->assertSame('cancelled', Payment::query()->sole()->status);

        // Paying after cancelling: recorded for a manual refund, never delivered.
        $authority = $this->gateway->requested[0]['authority'];
        $this->gateway->verified[$authority] = 90_000;
        $this->bankReturn($authority)->assertOk();
        $this->assertSame('cancelled', Payment::query()->sole()->status, 'already settled rows are left alone');

        $id = $this->buy($p->public_id)->json('data.id');
        $authority = $this->gateway->requested[1]['authority'];
        $this->gateway->verified[$authority] = 90_000;
        $this->bankReturn($authority);
        $this->signedJson('POST', "/api/v1/orders/{$id}/cancel")->assertStatus(409);
    }

    public function test_guard_rails(): void
    {
        $this->loginAs();
        $pointsOnly = $this->product();
        $this->buy($pointsOnly->public_id)->assertStatus(422)->assertJsonPath('error.code', 'payment_unavailable');

        $p = $this->product(['type' => ProductType::Service, 'rial_price' => 90_000, 'stock' => 1]);
        $this->gateway->down = true;
        $this->buy($p->public_id)->assertStatus(503)->assertJsonPath('error.code', 'payment_gateway_unavailable');
        $this->assertSame(1, $p->fresh()->stock, 'a failed start gives the stock back');

        $this->bankReturn('nonexistent123')->assertNotFound();
        $this->get('/payments/zarinpal/callback?Authority=../../x')->assertNotFound();

        FeatureFlag::query()->where('key', 'money_payment')->update(['is_enabled' => false]);
        app(FeatureFlags::class)->flush();
        $this->gateway->down = false;
        $this->buy($p->public_id)->assertStatus(422);
    }

    public function test_zarinpal_adapter_speaks_the_v4_api(): void
    {
        Http::fake([
            'api.zarinpal.com/pg/v4/payment/request.json' => Http::response(['data' => ['code' => 100, 'message' => 'Success', 'authority' => 'A0000000000000000000000000000abc', 'fee_type' => 'Merchant', 'fee' => 100], 'errors' => []]),
            'api.zarinpal.com/pg/v4/payment/verify.json' => Http::sequence()
                ->push(['data' => ['code' => 100, 'ref_id' => 201, 'card_pan' => '502229******5995'], 'errors' => []])
                ->push(['data' => ['code' => 101, 'ref_id' => 201], 'errors' => []])
                ->push(['data' => [], 'errors' => ['code' => -51, 'message' => 'Session is not valid']]),
        ]);
        $zp = new ZarinpalGateway('merchant-uuid');

        $opened = $zp->request(1_000_000, 'https://api.test/cb', 'سفارش', '09121234567');
        $this->assertSame('https://www.zarinpal.com/pg/StartPay/A0000000000000000000000000000abc', $opened['url']);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'request.json') && $r['merchant_id'] === 'merchant-uuid'
            && $r['amount'] === 1_000_000 && $r['currency'] === 'IRR' && $r['callback_url'] === 'https://api.test/cb' && $r['metadata'] === ['mobile' => '09121234567']);

        $this->assertSame(['ref_id' => '201', 'card_pan' => '502229******5995'], $zp->verify(1_000_000, $opened['authority']));
        $this->assertSame('201', $zp->verify(1_000_000, $opened['authority'])['ref_id'], '101 = already verified');
        $this->assertNull($zp->verify(1_000_000, $opened['authority']));

        Http::fake(['*' => Http::response('oops', 502)]);
        $this->expectException(GatewayUnavailable::class);
        (new ZarinpalGateway('m', sandbox: true))->verify(1, 'x');
    }
}
