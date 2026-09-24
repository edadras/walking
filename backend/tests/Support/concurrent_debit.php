<?php

/*
 * Runs in a separate PHP process (see WalletServiceTest::test_parallel_debits_cannot_double_spend).
 * Usage: php concurrent_debit.php <user_id> <points> <idempotency_key>
 * Prints "ok" or the error code.
 */

use App\Domain\Wallet\InsufficientPoints;
use App\Domain\Wallet\WalletService;
use App\Enums\TransactionType;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $userId, $points, $key] = $argv;
usleep(random_int(0, 20000));
try {
    $app->make(WalletService::class)->debit(User::query()->findOrFail($userId), (int) $points, TransactionType::Purchase, $key, 'test');
    echo 'ok';
} catch (InsufficientPoints) {
    echo 'insufficient';
}
