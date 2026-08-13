<?php

declare(strict_types=1);

namespace App\Dto;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateProductRequest
{
    #[Assert\NotBlank(message: 'Must be a non-empty string.')]
    #[Assert\Length(max: 64, maxMessage: 'Must contain at most {{ limit }} characters.')]
    #[OA\Property(example: 'LAPTOP-001')]
    public string $sku;

    #[Assert\NotBlank(message: 'Must be a non-empty string.')]
    #[Assert\Length(max: 255, maxMessage: 'Must contain at most {{ limit }} characters.')]
    #[OA\Property(example: 'Laptop')]
    public string $name;

    public function __construct(
        string $sku,
        string $name,
        #[Assert\Range(
            notInRangeMessage: 'Must be between {{ min }} and {{ max }}.',
            min: 0,
            max: 2_147_483_647,
        )]
        #[OA\Property(example: 10)]
        public int $stock,
    ) {
        $this->sku = trim($sku);
        $this->name = trim($name);
    }
}
