<?php

namespace App\Domain\Wallet;

use App\Exceptions\ApiException;

class InsufficientPoints extends ApiException
{
    public function __construct(public readonly int $available, public readonly int $required)
    {
        parent::__construct('insufficient_points', 'امتیاز کافی نداری.', 409, ['available' => $available, 'required' => $required]);
    }
}
