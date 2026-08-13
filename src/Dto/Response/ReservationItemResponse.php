<?php

declare(strict_types=1);

namespace App\Dto\Response;

use OpenApi\Attributes as OA;

final readonly class ReservationItemResponse
{
    public function __construct(
        #[OA\Property(property: 'product_id', format: 'uuid', example: '019ffb26-6bf7-71c1-93e7-d8672abf786b')]
        public string $productId,
        #[OA\Property(example: 'LAPTOP-001')]
        public string $sku,
        #[OA\Property(example: 'Laptop')]
        public string $name,
        #[OA\Property(minimum: 1, example: 2)]
        public int $quantity,
    ) {
    }
}
