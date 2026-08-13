<?php

declare(strict_types=1);

namespace App\Service;

final readonly class ReservationResult
{
    /** @param array<string, mixed> $reservation */
    public function __construct(
        public array $reservation,
        public bool $replayed,
    ) {
    }
}

