<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected Connection $connection;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach (['reservation_items', 'reservations', 'products', 'lock_keys'] as $table) {
                $this->connection->executeStatement('TRUNCATE TABLE '.$table);
            }
        } finally {
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    /** @return array<string, mixed> */
    protected function createProduct(string $sku, int $stock, ?string $name = null): array
    {
        $this->client->jsonRequest('POST', '/api/products', [
            'sku' => $sku,
            'name' => $name ?? $sku,
            'stock' => $stock,
        ]);
        self::assertResponseStatusCodeSame(201);

        return $this->json();
    }

    /** @param array<string, mixed> $body
     *  @return array<string, mixed>
     */
    protected function reserve(array $body, string $key): array
    {
        $this->client->jsonRequest('POST', '/api/reservations', $body, ['HTTP_IDEMPOTENCY_KEY' => $key]);

        return $this->json();
    }

    /** @return array<string, mixed> */
    protected function json(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
