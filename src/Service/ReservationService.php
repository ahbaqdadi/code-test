<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Entity\Reservation;
use App\Entity\ReservationItem;
use App\Http\ApiProblemException;
use App\Repository\ProductRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Lock\LockFactory;
use Throwable;

final readonly class ReservationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductRepository      $products,
        private ReservationRepository  $reservations,
        private LockFactory            $lockFactory,
        private ClockInterface         $clock,
    ) {
    }

    /** @param list<array{product_id: string, quantity: int}> $items */
    public function reserve(array $items, int $ttlSeconds, string $idempotencyKey, string $requestHash): ReservationResult
    {
        $lock = $this->lockFactory->createLock('reservation-idempotency-'.$idempotencyKey, 300.0);
        $lock->acquire(true);

        try {
            return $this->transactional(function () use ($items, $ttlSeconds, $idempotencyKey, $requestHash): ReservationResult {
                $existing = $this->reservations->findOneByIdempotencyKey($idempotencyKey);
                if ($existing !== null) {
                    if (!hash_equals($existing->getRequestHash(), $requestHash)) {
                        throw new ApiProblemException(
                            409,
                            'This idempotency key was already used with a different request.',
                            '/problems/idempotency-key-reused',
                        );
                    }

                    $existing = $this->lockedReservation($existing->getId());
                    $existing->expireIfDue($this->now());
                    $this->reservations->loadItems($existing);

                    return new ReservationResult($this->format($existing), true);
                }

                $productIds = array_column($items, 'product_id');
                sort($productIds, SORT_STRING);

                $products = $this->products->findByIdsForUpdate($productIds);
                if (count($products) !== count($productIds)) {
                    $found = array_map(static fn (Product $product): string => $product->getId(), $products);
                    throw new ApiProblemException(
                        422,
                        'One or more products do not exist.',
                        '/problems/product-not-found',
                        ['missing_product_ids' => array_values(array_diff($productIds, $found))],
                    );
                }

                $allocated = $this->products->allocatedQuantities($productIds);
                $requested = [];
                foreach ($items as $item) {
                    $requested[$item['product_id']] = $item['quantity'];
                }

                $shortages = [];
                foreach ($products as $product) {
                    $id = $product->getId();
                    $available = $product->getStock() - ($allocated[$id] ?? 0);
                    if ($requested[$id] > $available) {
                        $shortages[] = [
                            'product_id' => $id,
                            'sku' => $product->getSku(),
                            'requested' => $requested[$id],
                            'available' => $available,
                        ];
                    }
                }

                if ($shortages !== []) {
                    throw new ApiProblemException(
                        409,
                        'Insufficient stock. No inventory was reserved.',
                        '/problems/insufficient-stock',
                        ['shortages' => $shortages],
                    );
                }

                $now = $this->now();
                $reservation = new Reservation(
                    $idempotencyKey,
                    $requestHash,
                    $now->modify(sprintf('+%d seconds', $ttlSeconds)),
                    $now,
                );
                $productsById = [];
                foreach ($products as $product) {
                    $productsById[$product->getId()] = $product;
                }
                foreach ($items as $item) {
                    $reservation->addItem($productsById[$item['product_id']], $item['quantity']);
                }
                $this->reservations->add($reservation);

                return new ReservationResult($this->format($reservation), false);
            });
        } finally {
            $lock->release();
        }
    }

    /** @return array<string, mixed>
     * @throws Throwable
     */
    public function get(Reservation $reservation): array
    {
        return $this->transactional(function () use ($reservation): array {
            $reservation = $this->lockedReservation($reservation->getId());
            $reservation->expireIfDue($this->now());
            $this->reservations->loadItems($reservation);

            return $this->format($reservation);
        });
    }

    /** @return array<string, mixed>
     * @throws Throwable
     */
    public function confirm(Reservation $reservation): array
    {
        return $this->transactional(function () use ($reservation): array {
            $reservation = $this->lockedReservation($reservation->getId());
            $now = $this->now();
            $reservation->expireIfDue($now);

            if ($reservation->isConfirmed()) {
                $this->reservations->loadItems($reservation);

                return $this->format($reservation);
            }
            if (!$reservation->isActive()) {
                throw new ApiProblemException(409, 'Only an active reservation can be confirmed.', '/problems/invalid-reservation-state');
            }

            $this->products->findForReservationForUpdate($reservation);
            $reservation->confirm($now);
            $this->reservations->loadItems($reservation);

            return $this->format($reservation);
        });
    }

    /** @return array<string, mixed>
     * @throws Throwable
     */
    public function release(Reservation $reservation): array
    {
        return $this->transactional(function () use ($reservation): array {
            $reservation = $this->lockedReservation($reservation->getId());
            $now = $this->now();
            $reservation->expireIfDue($now);

            if ($reservation->isReleased()) {
                $this->reservations->loadItems($reservation);

                return $this->format($reservation);
            }
            if (!$reservation->isActive()) {
                throw new ApiProblemException(409, 'Only an active reservation can be released.', '/problems/invalid-reservation-state');
            }

            $reservation->release($now);
            $this->reservations->loadItems($reservation);

            return $this->format($reservation);
        });
    }

    public function expireDue(): int
    {
        return $this->reservations->expireDue($this->now());
    }

    private function lockedReservation(string $id): Reservation
    {
        return $this->reservations->findForUpdate($id)
            ?? throw new ApiProblemException(404, 'Reservation not found.', '/problems/reservation-not-found');
    }

    /** @return array<string, mixed> */
    private function format(Reservation $reservation): array
    {
        $items = array_map(
            static fn (ReservationItem $item): array => [
                'product_id' => $item->getProduct()->getId(),
                'sku' => $item->getProduct()->getSku(),
                'name' => $item->getProduct()->getName(),
                'quantity' => $item->getQuantity(),
            ],
            $reservation->getItems()->toArray(),
        );
        usort($items, static fn (array $left, array $right): int => $left['product_id'] <=> $right['product_id']);

        return [
            'id' => $reservation->getId(),
            'status' => $reservation->getStatus(),
            'expires_at' => $this->date($reservation->getExpiresAt()),
            'created_at' => $this->date($reservation->getCreatedAt()),
            'updated_at' => $this->date($reservation->getUpdatedAt()),
            'items' => $items,
        ];
    }

    /** @template T
     * @param callable(): T $operation
     * @return T
     * @throws Throwable
     */
    private function transactional(callable $operation): mixed
    {
        $this->entityManager->beginTransaction();

        try {
            $result = $operation();
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $result;
        } catch (Throwable $exception) {
            $this->entityManager->rollback();
            $this->entityManager->clear();

            throw $exception;
        }
    }

    private function date(\DateTimeImmutable $value): string
    {
        return $value->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
    }

    private function now(): \DateTimeImmutable
    {
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));

        return $now->setTime(
            (int) $now->format('H'),
            (int) $now->format('i'),
            (int) $now->format('s'),
        );
    }
}
