<?php

/*
 * Separate PHP process for ConcurrentPurchaseTest.
 * Usage: php concurrent_purchase.php <user_id> <product_public_id> <quantity> <idempotency_key>
 * Prints "ok" or the error code.
 */

use App\Domain\Store\OrderService;
use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $userId, $productId, $qty, $key] = $argv;
usleep(random_int(0, 20000));
try {
    $app->make(OrderService::class)->place(User::query()->findOrFail($userId), [['product_id' => $productId, 'quantity' => (int) $qty]], $key);
    echo 'ok';
} catch (ApiException $e) {
    echo $e->errorCode;
}
