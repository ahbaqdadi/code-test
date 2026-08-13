<?php

declare(strict_types=1);

namespace App\Dto\Response;

use OpenApi\Attributes as OA;

final readonly class ProductResponse
{
    public function __construct(
        #[OA\Property(format: 'uuid', example: '019ffb26-6bf7-71c1-93e7-d8672abf786b')]
        public string $id,
        #[OA\Property(example: 'LAPTOP-001')]
        public string $sku,
        #[OA\Property(example: 'Laptop')]
        public string $name,
        #[OA\Property(minimum: 0, example: 10)]
        public int $stock,
        #[OA\Property(property: 'available_stock', minimum: 0, example: 8)]
        public int $availableStock,
        #[OA\Property(property: 'created_at', format: 'date-time', example: '2026-08-13T12:00:00.000000Z')]
        public string $createdAt,
    ) {
    }
}
