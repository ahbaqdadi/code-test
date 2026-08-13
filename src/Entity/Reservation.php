<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\Table(name: 'reservations')]
#[ORM\UniqueConstraint(name: 'reservations_idempotency_key_unique', columns: ['idempotency_key'])]
#[ORM\Index(name: 'reservations_expiry_idx', columns: ['status', 'expires_at'])]
final class Reservation
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_RELEASED = 'released';
    public const STATUS_EXPIRED = 'expired';

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\Column(name: 'idempotency_key', length: 255)]
    private string $idempotencyKey;

    #[ORM\Column(name: 'request_hash', length: 64, options: ['fixed' => true])]
    private string $requestHash;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, ReservationItem> */
    #[ORM\OneToMany(
        targetEntity: ReservationItem::class,
        mappedBy: 'reservation',
        cascade: ['persist'],
        orphanRemoval: true,
    )]
    private Collection $items;

    public function __construct(
        string $idempotencyKey,
        string $requestHash,
        \DateTimeImmutable $expiresAt,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = Uuid::v7()->toRfc4122();
        $this->idempotencyKey = $idempotencyKey;
        $this->requestHash = $requestHash;
        $this->expiresAt = $expiresAt;
        $this->createdAt = $createdAt;
        $this->updatedAt = $createdAt;
        $this->items = new ArrayCollection();
    }

    public function addItem(Product $product, int $quantity): void
    {
        $this->items->add(new ReservationItem($this, $product, $quantity));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function getRequestHash(): string
    {
        return $this->requestHash;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, ReservationItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isReleased(): bool
    {
        return $this->status === self::STATUS_RELEASED;
    }

    public function expireIfDue(\DateTimeImmutable $now): bool
    {
        if (!$this->isActive() || $this->expiresAt > $now) {
            return false;
        }

        $this->status = self::STATUS_EXPIRED;
        $this->updatedAt = $now;

        return true;
    }

    public function confirm(\DateTimeImmutable $now): void
    {
        $this->status = self::STATUS_CONFIRMED;
        $this->updatedAt = $now;
    }

    public function release(\DateTimeImmutable $now): void
    {
        $this->status = self::STATUS_RELEASED;
        $this->updatedAt = $now;
    }
}
