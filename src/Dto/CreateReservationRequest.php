<?php

declare(strict_types=1);

namespace App\Dto;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class CreateReservationRequest
{
    /** @param list<ReservationItemRequest> $items */
    public function __construct(
        #[Assert\Range(
            notInRangeMessage: 'Must be between {{ min }} and {{ max }}.',
            min: 1,
            max: 3600,
        )]
        #[OA\Property(property: 'ttl_seconds', example: 900)]
        public int $ttlSeconds,
        #[Assert\Count(
            min: 1,
            max: 100,
            minMessage: 'Must contain at least one item.',
            maxMessage: 'Must contain no more than {{ limit }} items.',
        )]
        #[Assert\Valid]
        public array $items,
    ) {
    }

    #[Assert\Callback]
    public function validateUniqueProducts(ExecutionContextInterface $context): void
    {
        $seen = [];
        foreach ($this->items as $index => $item) {
            $productId = strtolower($item->productId);
            if (isset($seen[$productId])) {
                $context
                    ->buildViolation('Each product may appear only once.')
                    ->atPath(sprintf('items[%d].productId', $index))
                    ->addViolation();

                return;
            }
            $seen[$productId] = true;
        }
    }

    /** @return list<array{product_id: string, quantity: int}> */
    public function normalizedItems(): array
    {
        $items = array_map(static fn (ReservationItemRequest $item): array => [
            'product_id' => strtolower($item->productId),
            'quantity' => $item->quantity,
        ], $this->items);
        usort($items, static fn (array $left, array $right): int => $left['product_id'] <=> $right['product_id']);

        return $items;
    }

    /**
     * @throws \JsonException
     */
    public function requestHash(): string
    {
        return hash('sha256', json_encode([
            'items' => $this->normalizedItems(),
            'ttl_seconds' => $this->ttlSeconds,
        ], JSON_THROW_ON_ERROR));
    }
}
