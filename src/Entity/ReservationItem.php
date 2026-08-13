<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'reservation_items')]
#[ORM\Index(name: 'reservation_items_product_idx', columns: ['product_id'])]
final class ReservationItem
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Reservation::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'reservation_id', nullable: false, onDelete: 'CASCADE')]
    private Reservation $reservation;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'reservationItems')]
    #[ORM\JoinColumn(name: 'product_id', nullable: false, onDelete: 'RESTRICT')]
    private Product $product;

    #[ORM\Column]
    private int $quantity;

    public function __construct(Reservation $reservation, Product $product, int $quantity)
    {
        $this->reservation = $reservation;
        $this->product = $product;
        $this->quantity = $quantity;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }
}

