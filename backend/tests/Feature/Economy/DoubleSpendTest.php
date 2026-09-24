<?php

namespace Tests\Feature\Economy;

use App\Domain\Wallet\WalletService;
use App\Enums\TransactionType;
use App\Models\PointTransaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Real concurrency: separate PHP processes (separate DB connections) race to
 * spend the same balance. Uses committed data, so it can't run inside the
 * RefreshDatabase transaction.
 */
class DoubleSpendTest extends TestCase
{
    use DatabaseMigrations;

    public function test_parallel_debits_cannot_double_spend(): void
    {
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 100, TransactionType::SponsorReward, 'seed', 'seed');

        $processes = [];
        for ($i = 0; $i < 8; $i++) {
            $p = new Process(['php', base_path('tests/Support/concurrent_debit.php'), (string) $user->id, '30', "order:$i"], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => config('database.connections.mysql.database'), 'DB_CONNECTION' => 'mysql', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync']);
            $p->start();
            $processes[] = $p;
        }
        $outputs = array_map(function (Process $p) {
            $p->wait();

            return trim($p->getOutput()) ?: trim($p->getErrorOutput());
        }, $processes);

        $this->assertSame(3, count(array_filter($outputs, fn ($o) => $o === 'ok')), implode("\n", $outputs));
        $this->assertSame(5, count(array_filter($outputs, fn ($o) => $o === 'insufficient')), implode("\n", $outputs));
        $this->assertSame(10, Wallet::query()->find($user->id)->available_balance);
        $this->assertSame(3, PointTransaction::query()->where('type', 'purchase')->count());
    }

    public function test_parallel_retries_of_the_same_request_apply_once(): void
    {
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 100, TransactionType::SponsorReward, 'seed', 'seed');

        $processes = [];
        for ($i = 0; $i < 6; $i++) {
            $p = new Process(['php', base_path('tests/Support/concurrent_debit.php'), (string) $user->id, '30', 'order:same'], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => config('database.connections.mysql.database'), 'DB_CONNECTION' => 'mysql', 'CACHE_STORE' => 'array']);
            $p->start();
            $processes[] = $p;
        }
        foreach ($processes as $p) {
            $p->wait();
        }

        $this->assertSame(70, Wallet::query()->find($user->id)->available_balance);
        $this->assertSame(1, PointTransaction::query()->where('type', 'purchase')->count());
    }
}
