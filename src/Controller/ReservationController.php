<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateReservationRequest;
use App\Dto\IdempotencyKey;
use App\Dto\Response\ApiProblemResponse;
use App\Dto\Response\ReservationResponse;
use App\Entity\Reservation;
use App\Http\Attribute\MapIdempotencyKey;
use App\Service\ReservationService;
use JsonException;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/reservations')]
final class ReservationController extends AbstractController
{
    public function __construct(private readonly ReservationService $reservations)
    {
    }

    /**
     * @throws JsonException
     */
    #[Route('', name: 'reservation_create', methods: ['POST'])]
    #[OA\Post(
        description: 'The complete request succeeds or no inventory is reserved. Reuse the same Idempotency-Key when retrying an identical request.',
        summary: 'Atomically reserve one or more products',
        tags: ['Reservations'],
    )]
    #[OA\Parameter(
        name: 'Idempotency-Key',
        description: 'Unique key for this logical reservation attempt.',
        in: 'header',
        required: true,
        schema: new OA\Schema(type: 'string', minLength: 1, maxLength: 255),
        example: 'checkout-attempt-7f58aab1',
    )]
    #[OA\Response(
        response: 201,
        description: 'Reservation created, or the original result replayed.',
        headers: [
            new OA\Header(
                header: 'Idempotency-Replayed',
                description: 'Present with value true when an earlier result was replayed.',
                schema: new OA\Schema(type: 'string', enum: ['true']),
            ),
        ],
        content: new OA\JsonContent(ref: new Model(type: ReservationResponse::class)),
    )]
    #[OA\Response(
        response: 409,
        description: 'Insufficient stock, or the idempotency key was reused with different input.',
        content: new OA\MediaType(
            mediaType: 'application/problem+json',
            schema: new OA\Schema(ref: new Model(type: ApiProblemResponse::class)),
        ),
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation failed, a product does not exist, or the idempotency header is missing.',
        content: new OA\MediaType(
            mediaType: 'application/problem+json',
            schema: new OA\Schema(ref: new Model(type: ApiProblemResponse::class)),
        ),
    )]
    public function create(
        #[MapRequestPayload(acceptFormat: 'json')]
        CreateReservationRequest $payload,
        #[MapIdempotencyKey]
        IdempotencyKey $idempotencyKey,
    ): JsonResponse {
        $result = $this->reservations->reserve(
            $payload->normalizedItems(),
            $payload->ttlSeconds,
            $idempotencyKey->value,
            $payload->requestHash(),
        );

        return new JsonResponse(
            $result->reservation,
            201,
            $result->replayed ? ['Idempotency-Replayed' => 'true'] : [],
        );
    }

    #[Route('/{id}', name: 'reservation_get', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    #[OA\Get(summary: 'Get reservation details', tags: ['Reservations'])]
    #[OA\Response(
        response: 200,
        description: 'Reservation details. An elapsed active reservation is materialized as expired.',
        content: new OA\JsonContent(ref: new Model(type: ReservationResponse::class)),
    )]
    #[OA\Response(
        response: 404,
        description: 'Reservation not found.',
        content: new OA\MediaType(
            mediaType: 'application/problem+json',
            schema: new OA\Schema(ref: new Model(type: ApiProblemResponse::class)),
        ),
    )]
    public function get(#[MapEntity(id: 'id')] Reservation $reservation): JsonResponse
    {
        return new JsonResponse($this->reservations->get($reservation));
    }

    #[Route('/{id}/confirm', name: 'reservation_confirm', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[OA\Post(summary: 'Confirm an active reservation as sold', tags: ['Reservations'])]
    #[OA\Response(
        response: 200,
        description: 'Reservation confirmed, including an idempotent repeated confirmation.',
        content: new OA\JsonContent(ref: new Model(type: ReservationResponse::class)),
    )]
    #[OA\Response(
        response: 404,
        description: 'Reservation not found.',
        content: new OA\MediaType(
            mediaType: 'application/problem+json',
            schema: new OA\Schema(ref: new Model(type: ApiProblemResponse::class)),
        ),
    )]
    #[OA\Response(
        response: 409,
        description: 'The reservation is released or expired.',
        content: new OA\MediaType(
            mediaType: 'application/problem+json',
            schema: new OA\Schema(ref: new Model(type: ApiProblemResponse::class)),
        ),
    )]
    public function confirm(#[MapEntity(id: 'id')] Reservation $reservation): JsonResponse
    {
        return new JsonResponse($this->reservations->confirm($reservation));
    }

    #[Route('/{id}/release', name: 'reservation_release', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[OA\Post(summary: 'Release inventory from an active reservation', tags: ['Reservations'])]
    #[OA\Response(
        response: 200,
        description: 'Reservation released, including an idempotent repeated release.',
        content: new OA\JsonContent(ref: new Model(type: ReservationResponse::class)),
    )]
    #[OA\Response(
        response: 404,
        description: 'Reservation not found.',
        content: new OA\MediaType(
            mediaType: 'application/problem+json',
            schema: new OA\Schema(ref: new Model(type: ApiProblemResponse::class)),
        ),
    )]
    #[OA\Response(
        response: 409,
        description: 'The reservation is confirmed or expired.',
        content: new OA\MediaType(
            mediaType: 'application/problem+json',
            schema: new OA\Schema(ref: new Model(type: ApiProblemResponse::class)),
        ),
    )]
    public function release(#[MapEntity(id: 'id')] Reservation $reservation): JsonResponse
    {
        return new JsonResponse($this->reservations->release($reservation));
    }
}
