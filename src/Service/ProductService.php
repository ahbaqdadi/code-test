<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Http\ApiProblemException;
use App\Repository\ProductRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Clock\ClockInterface;

final readonly class ProductService
{
    public function __construct(
        private ProductRepository $products,
        private ClockInterface    $clock,
    ) {
    }

    /** @return array<string, mixed> */
    public function create(string $sku, string $name, int $stock): array
    {
        $product = new Product($sku, $name, $stock, $this->now());

        try {
            $this->products->add($product, true);
        } catch (UniqueConstraintViolationException) {
            throw new ApiProblemException(409, 'A product with this SKU already exists.', '/problems/duplicate-sku');
        }

        return $this->format($product, $stock);
    }

    /** @return array<string, mixed> */
    public function get(Product $product): array
    {
        $allocated = $this->products->allocatedQuantities([$product->getId()]);

        return $this->format($product, $product->getStock() - ($allocated[$product->getId()] ?? 0));
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        $products = $this->products->findAllOrdered();
        $allocated = $this->products->allocatedQuantities(
            array_map(static fn (Product $product): string => $product->getId(), $products),
        );

        return array_map(
            fn (Product $product): array => $this->format(
                $product,
                $product->getStock() - ($allocated[$product->getId()] ?? 0),
            ),
            $products,
        );
    }

    /** @return array<string, mixed> */
    private function format(Product $product, int $availableStock): array
    {
        return [
            'id' => $product->getId(),
            'sku' => $product->getSku(),
            'name' => $product->getName(),
            'stock' => $product->getStock(),
            'available_stock' => $availableStock,
            'created_at' => $this->date($product->getCreatedAt()),
        ];
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
