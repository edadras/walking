<?php

namespace App\Console\Commands;

use App\Domain\Store\PaymentGateway;
use App\Domain\Store\PaymentService;
use Illuminate\Console\Command;

class SweepPayments extends Command
{
    protected $signature = 'payments:sweep';

    protected $description = 'Re-verify abandoned rial payments; fulfil the paid ones and release the rest';

    public function handle(): int
    {
        if (! app()->bound(PaymentGateway::class)) {
            return self::SUCCESS;
        }
        $this->info('Settled '.app(PaymentService::class)->sweep().' payments');

        return self::SUCCESS;
    }
}
