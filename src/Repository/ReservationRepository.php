<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Reservation> */
final class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    public function add(Reservation $reservation): void
    {
        $this->getEntityManager()->persist($reservation);
    }

    public function findOneByIdempotencyKey(string $key): ?Reservation
    {
        return $this->createQueryBuilder('reservation')
            ->where('reservation.idempotencyKey = :key')
            ->setParameter('key', $key)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findForUpdate(string $id): ?Reservation
    {
        return $this->createQueryBuilder('reservation')
            ->where('reservation.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }

    public function loadItems(Reservation $reservation): void
    {
        $this->createQueryBuilder('reservation')
            ->addSelect('item', 'product')
            ->leftJoin('reservation.items', 'item')
            ->leftJoin('item.product', 'product')
            ->where('reservation = :reservation')
            ->setParameter('reservation', $reservation)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function expireDue(\DateTimeImmutable $now): int
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->update(Reservation::class, 'reservation')
            ->set('reservation.status', ':expired')
            ->set('reservation.updatedAt', ':now')
            ->where('reservation.status = :active')
            ->andWhere('reservation.expiresAt <= :now')
            ->setParameter('expired', Reservation::STATUS_EXPIRED)
            ->setParameter('active', Reservation::STATUS_ACTIVE)
            ->setParameter('now', $now, Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->execute();
    }
}
