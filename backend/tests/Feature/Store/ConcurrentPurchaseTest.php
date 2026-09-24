<?php

namespace Tests\Feature\Store;

use App\Domain\Wallet\WalletService;
use App\Enums\TransactionType;
use App\Models\FeatureFlag;
use App\Models\Order;
use App\Models\ProductCode;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\Process\Process;
use Tests\Concerns\CreatesProducts;
use Tests\TestCase;

/** Separate processes (separate DB connections) race for the same stock and balance. */
class ConcurrentPurchaseTest extends TestCase
{
    use CreatesProducts, DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureFlag::query()->updateOrCreate(['key' => 'store'], ['is_enabled' => true, 'rollout_percent' => 100]);
    }

    /** @return list<string> */
    private function race(array $users, string $productId, int $qty, callable $key): array
    {
        $processes = [];
        foreach ($users as $i => $user) {
            $p = new Process(['php', base_path('tests/Support/concurrent_purchase.php'), (string) $user->id, $productId, (string) $qty, $key($i)], base_path(),
                ['APP_ENV' => 'testing', 'DB_DATABASE' => config('database.connections.mysql.database'), 'DB_CONNECTION' => 'mysql', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync']);
            $p->start();
            $processes[] = $p;
        }

        return array_map(function (Process $p) {
            $p->wait();

            return trim($p->getOutput()) ?: trim($p->getErrorOutput());
        }, $processes);
    }

    private function funded(int $points): User
    {
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, $points, TransactionType::Adjustment, 'seed', 'seed');

        return $user;
    }

    public function test_last_codes_are_sold_exactly_once(): void
    {
        $product = $this->codeProduct(3, ['point_price' => 50]);
        $users = array_map(fn () => $this->funded(100), range(1, 8));

        $out = $this->race($users, $product->public_id, 1, fn ($i) => "key-$i-".str_repeat('x', 16));

        $this->assertSame(3, count(array_filter($out, fn ($o) => $o === 'ok')), implode("\n", $out));
        $this->assertSame(5, count(array_filter($out, fn ($o) => $o === 'out_of_stock')), implode("\n", $out));
        $this->assertSame(0, $product->fresh()->stock);
        $this->assertSame(3, ProductCode::query()->whereNotNull('order_item_id')->distinct()->count('order_item_id'));
        $this->assertSame(3, Order::query()->count());
        // Only the three winners were charged.
        $this->assertSame(8 * 100 - 3 * 50, (int) Wallet::query()->sum('available_balance'));
    }

    public function test_parallel_orders_cannot_overspend_one_wallet(): void
    {
        $product = $this->product(['type' => 'service', 'point_price' => 40, 'stock' => null]);
        $user = $this->funded(100);

        $out = $this->race(array_fill(0, 6, $user), $product->public_id, 1, fn ($i) => "spend-$i-".str_repeat('y', 16));

        $this->assertSame(2, count(array_filter($out, fn ($o) => $o === 'ok')), implode("\n", $out));
        $this->assertSame(20, Wallet::query()->find($user->id)->available_balance);
        $this->assertSame(2, Order::query()->count());
    }

    public function test_parallel_retries_with_one_key_create_one_order(): void
    {
        $product = $this->product(['type' => 'service', 'point_price' => 10, 'stock' => null]);
        $user = $this->funded(100);

        $this->race(array_fill(0, 6, $user), $product->public_id, 1, fn () => 'same-key-'.str_repeat('z', 16));

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(90, Wallet::query()->find($user->id)->available_balance);
    }
}
