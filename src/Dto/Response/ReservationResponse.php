<?php

declare(strict_types=1);

namespace App\Dto\Response;

use App\Entity\Reservation;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

final readonly class ReservationResponse
{
    /** @param list<ReservationItemResponse> $items */
    public function __construct(
        #[OA\Property(format: 'uuid', example: '019ffb26-6bf7-71c1-93e7-d8672abf786b')]
        public string $id,
        #[OA\Property(
            example: Reservation::STATUS_ACTIVE,
            enum: [
                Reservation::STATUS_ACTIVE,
                Reservation::STATUS_CONFIRMED,
                Reservation::STATUS_RELEASED,
                Reservation::STATUS_EXPIRED,
            ],
        )]
        public string $status,
        #[OA\Property(property: 'expires_at', format: 'date-time', example: '2026-08-13T12:15:00.000000Z')]
        public string $expiresAt,
        #[OA\Property(property: 'created_at', format: 'date-time', example: '2026-08-13T12:00:00.000000Z')]
        public string $createdAt,
        #[OA\Property(property: 'updated_at', format: 'date-time', example: '2026-08-13T12:00:00.000000Z')]
        public string $updatedAt,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: ReservationItemResponse::class)))]
        public array $items,
    ) {
    }
}
