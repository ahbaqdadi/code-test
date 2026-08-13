<?php

declare(strict_types=1);

namespace App\Dto;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReservationItemRequest
{
    public function __construct(
        #[Assert\Uuid(message: 'Must be a valid UUID.')]
        #[OA\Property(property: 'product_id', format: 'uuid', example: '019ffb26-6bf7-71c1-93e7-d8672abf786b')]
        public string $productId,
        #[Assert\Range(
            notInRangeMessage: 'Must be between {{ min }} and {{ max }}.',
            min: 1,
            max: 1_000_000,
        )]
        #[OA\Property(example: 2)]
        public int $quantity,
    ) {
    }
}
