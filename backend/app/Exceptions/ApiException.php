<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A domain error that is safe to show to the client. `errorCode` is stable and
 * machine-readable; the message is Persian and user-facing.
 */
class ApiException extends RuntimeException
{
    /** @param array<string, mixed> $context extra non-sensitive data for the client */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 400,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public static function forbidden(string $code, string $message): self
    {
        return new self($code, $message, 403);
    }

    public static function conflict(string $code, string $message): self
    {
        return new self($code, $message, 409);
    }

    public static function unprocessable(string $code, string $message, array $context = []): self
    {
        return new self($code, $message, 422, $context);
    }
}
