<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\Reservation;
use App\Entity\ReservationItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Product> */
final class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function add(Product $product, bool $flush = false): void
    {
        $this->getEntityManager()->persist($product);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /** @return list<Product> */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('product')
            ->orderBy('product.createdAt', 'ASC')
            ->addOrderBy('product.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Product rows are locked in UUID order so every reservation transaction
     * acquires locks consistently and avoids multi-product deadlocks.
     *
     * @param list<string> $ids
     * @return list<Product>
     */
    public function findByIdsForUpdate(array $ids): array
    {
        return $this->createQueryBuilder('product')
            ->where('product.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('product.id', 'ASC')
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getResult();
    }

    /** @return list<Product> */
    public function findForReservationForUpdate(Reservation $reservation): array
    {
        return $this->createQueryBuilder('product')
            ->innerJoin(ReservationItem::class, 'item', 'WITH', 'item.product = product')
            ->where('item.reservation = :reservation')
            ->setParameter('reservation', $reservation)
            ->orderBy('product.id', 'ASC')
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getResult();
    }

    /**
     * @param list<string> $productIds
     * @return array<string, int> keyed by product UUID
     */
    public function allocatedQuantities(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(item.product) AS productId', 'SUM(item.quantity) AS allocated')
            ->from(ReservationItem::class, 'item')
            ->innerJoin('item.reservation', 'reservation')
            ->where('IDENTITY(item.product) IN (:productIds)')
            ->andWhere('(reservation.status = :confirmed OR (reservation.status = :active AND reservation.expiresAt > CURRENT_TIMESTAMP()))')
            ->setParameter('productIds', $productIds)
            ->setParameter('confirmed', Reservation::STATUS_CONFIRMED)
            ->setParameter('active', Reservation::STATUS_ACTIVE)
            ->groupBy('item.product')
            ->getQuery()
            ->getArrayResult();

        $allocated = [];
        foreach ($rows as $row) {
            $allocated[(string) $row['productId']] = (int) $row['allocated'];
        }

        return $allocated;
    }
}

