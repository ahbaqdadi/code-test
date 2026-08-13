<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Response\ApiProblemResponse;
use App\Dto\Response\HealthResponse;
use App\Repository\HealthCheckRepository;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    #[Route('/health', name: 'health', methods: ['GET'])]
    #[OA\Get(summary: 'Check application and database health', tags: ['System'])]
    #[OA\Response(
        response: 200,
        description: 'The application and database are healthy.',
        content: new OA\JsonContent(ref: new Model(type: HealthResponse::class)),
    )]
    #[OA\Response(
        response: 500,
        description: 'The health check failed.',
        content: new OA\MediaType(
            mediaType: 'application/problem+json',
            schema: new OA\Schema(ref: new Model(type: ApiProblemResponse::class)),
        ),
    )]
    public function __invoke(HealthCheckRepository $health): JsonResponse
    {
        $health->check();

        return new JsonResponse(['status' => 'ok']);
    }
}
