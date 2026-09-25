<?php

namespace App\Domain\Store\Exceptions;

use RuntimeException;

/** The gateway couldn't be reached or answered with an error: the outcome is unknown, retry later. */
class GatewayUnavailable extends RuntimeException {}
