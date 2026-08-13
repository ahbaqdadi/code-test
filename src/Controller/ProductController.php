<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateProductRequest;
use App\Dto\Response\ApiProblemResponse;
use App\Dto\Response\ProductListResponse;
use App\Dto\Response\ProductResponse;
use App\Entity\Product;
use App\Service\ProductService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/products')]
final class ProductController extends AbstractController
{
    public function __construct(private readonly ProductService $products)
    {
    }

    #[Route('', name: 'product_create', methods: ['POST'])]
    #[OA\Post(summary: 'Create a product with its initial stock', tags: ['Products'])]
    #[OA\Response(
        response: 201,
        description: 'Product created.',
        content: new OA\JsonContent(ref: new Model(type: ProductResponse::class)),
    )]
    #[OA\Response(
        response: 409,
        description: 'The SKU already exists.',
        content: new OA\MediaType(
            mediaType: 'application/problem+json',
            schema: new OA\Schema(ref: new Model(type: ApiProblemResponse::class)),
        ),
    )]
    #[OA\Response(
        response: 422,
        description: 'Request validation failed.',
        content: new OA\MediaType(
            mediaType: 'application/problem+json',
            schema: new OA\Schema(ref: new Model(type: ApiProblemResponse::class)),
        ),
    )]
    public function create(
        #[MapRequestPayload(acceptFormat: 'json')]
        CreateProductRequest $payload,
    ): JsonResponse {
        return new JsonResponse(
            $this->products->create($payload->sku, $payload->name, $payload->stock),
            201,
        );
    }

    #[Route('', name: 'product_list', methods: ['GET'])]
    #[OA\Get(summary: 'List products with current available stock', tags: ['Products'])]
    #[OA\Response(
        response: 200,
        description: 'Product collection.',
        content: new OA\JsonContent(ref: new Model(type: ProductListResponse::class)),
    )]
    public function list(): JsonResponse
    {
        return new JsonResponse(['products' => $this->products->list()]);
    }

    #[Route('/{id}', name: 'product_get', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    #[OA\Get(summary: 'Get a product with current available stock', tags: ['Products'])]
    #[OA\Response(
        response: 200,
        description: 'Product details.',
        content: new OA\JsonContent(ref: new Model(type: ProductResponse::class)),
    )]
    #[OA\Response(
        response: 404,
        description: 'Product not found.',
        content: new OA\MediaType(
            mediaType: 'application/problem+json',
            schema: new OA\Schema(ref: new Model(type: ApiProblemResponse::class)),
        ),
    )]
    public function get(#[MapEntity(id: 'id')] Product $product): JsonResponse
    {
        return new JsonResponse($this->products->get($product));
    }
}
