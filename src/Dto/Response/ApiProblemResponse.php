<?php

declare(strict_types=1);

namespace App\Dto\Response;

use OpenApi\Attributes as OA;

#[OA\Schema(description: 'RFC 9457-style error response. Some problem types include additional machine-readable fields.')]
final readonly class ApiProblemResponse
{
    public function __construct(
        #[OA\Property(example: '/problems/insufficient-stock')]
        public string $type,
        #[OA\Property(example: 'Insufficient stock. No inventory was reserved.')]
        public string $title,
        #[OA\Property(example: 409)]
        public int $status,
    ) {
    }
}
