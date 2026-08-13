<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

final class ApiProblemException extends RuntimeException
{
    /** @param array<string, mixed> $details */
    public function __construct(
        public readonly int $statusCode,
        string $message,
        public readonly string $type = 'about:blank',
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }
}

