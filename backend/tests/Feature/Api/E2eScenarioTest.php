<?php

namespace Tests\Feature\Api;

use App\Enums\ProductType;
use App\Models\Product;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesProducts;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

/** The exact backend path the emulator test (integration_test/app_flow_test.dart) drives in CI. */
class E2eScenarioTest extends TestCase
{
    use CreatesProducts, RefreshDatabase, SignsDeviceRequests;

    public function test_seeded_e2e_account_can_sign_in_buy_and_cash_out(): void
    {
        config(['walk.loadtest.otp_code' => '12345']);
        $this->seed(PlatformSeeder::class);
        // CI seeds the full DemoSeeder; here only the product the scenario buys.
        $this->product(['slug' => 'plant-a-tree', 'type' => ProductType::Service, 'point_price' => 500]);
        $this->artisan('e2e:prepare', ['phone' => '09990000001'])->assertSuccessful();

        $this->registerDevice()->assertSuccessful();
        $this->signedJson('POST', '/api/v1/auth/otp/request', ['phone' => '09990000001'])->assertOk();
        $this->token = $this->signedJson('POST', '/api/v1/auth/otp/verify', ['phone' => '09990000001', 'code' => '12345'])->assertOk()->json('data.token');

        $this->authedJson('GET', '/api/v1/wallet')->assertJsonPath('data.available', 60000);

        $tree = Product::query()->where('slug', 'plant-a-tree')->firstOrFail();
        $this->signedJson('POST', '/api/v1/orders', ['items' => [['product_id' => $tree->public_id, 'quantity' => 1]]], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $this->authedJson('GET', '/api/v1/wallet')->assertJsonPath('data.available', 59500);

        $account = $this->authedJson('GET', '/api/v1/cashout')->assertJsonPath('data.blockers', [])->json('data.bank_accounts.0.id');
        $this->signedJson('POST', '/api/v1/cashout/otp')->assertOk();
        $this->signedJson('POST', '/api/v1/cashout/requests', ['bank_account_id' => $account, 'points' => 10000, 'code' => '12345'], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertCreated()->assertJsonPath('data.status', 'pending');
    }
}
