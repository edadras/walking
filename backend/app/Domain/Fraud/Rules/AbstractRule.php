<?php

namespace App\Domain\Fraud\Rules;

use App\Domain\Fraud\Contracts\FraudRule;

abstract class AbstractRule implements FraudRule
{
    public function key(): string
    {
        return static::KEY;
    }
}
