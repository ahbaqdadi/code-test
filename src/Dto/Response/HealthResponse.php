<?php

declare(strict_types=1);

namespace App\Dto\Response;

use OpenApi\Attributes as OA;

final readonly class HealthResponse
{
    public function __construct(
        #[OA\Property(enum: ['ok'], example: 'ok')]
        public string $status,
    ) {
    }
}
