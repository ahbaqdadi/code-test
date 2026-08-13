<?php

declare(strict_types=1);

namespace App\Dto\Response;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

final readonly class ProductListResponse
{
    /** @param list<ProductResponse> $products */
    public function __construct(
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: ProductResponse::class)))]
        public array $products,
    ) {
    }
}
