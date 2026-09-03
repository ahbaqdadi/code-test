<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class ReservationApiTest extends ApiTestCase
{
    public function testMultiProductReservationIsAllOrNothing(): void
    {
        $laptop = $this->createProduct('LAPTOP', 10);
        $monitor = $this->createProduct('MONITOR', 0);

        $problem = $this->reserve([
            'ttl_seconds' => 300,
            'items' => [
                ['product_id' => $laptop['id'], 'quantity' => 2],
                ['product_id' => $monitor['id'], 'quantity' => 1],
            ],
        ], 'all-or-nothing');

        self::assertResponseStatusCodeSame(409);
        self::assertSame('/problems/insufficient-stock', $problem['type']);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM reservations'));

        $this->client->request('GET', '/api/products/'.$laptop['id']);
        self::assertResponseIsSuccessful();
        self::assertSame(10, $this->json()['available_stock']);
    }

    public function testRetryReturnsTheSameReservationAndRejectsKeyReuseWithDifferentInput(): void
    {
        $product = $this->createProduct('MOUSE', 5);
        $body = [
            'ttl_seconds' => 120,
            'items' => [['product_id' => $product['id'], 'quantity' => 2]],
        ];

        $first = $this->reserve($body, 'checkout-123');
        self::assertResponseStatusCodeSame(201);

        $second = $this->reserve($body, 'checkout-123');
        self::assertResponseStatusCodeSame(201);
        self::assertSame('true', $this->client->getResponse()->headers->get('Idempotency-Replayed'));
        self::assertSame($first['id'], $second['id']);
        self::assertSame($first, $second);
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM reservations'));

        $problem = $this->reserve([
            'ttl_seconds' => 120,
            'items' => [['product_id' => $product['id'], 'quantity' => 1]],
        ], 'checkout-123');
        self::assertResponseStatusCodeSame(409);
        self::assertSame('/problems/idempotency-key-reused', $problem['type']);
    }

    public function testIdempotencyKeysAreCaseSensitive(): void
    {
        $product = $this->createProduct('CASE-SENSITIVE-KEYS', 2);
        $body = [
            'ttl_seconds' => 120,
            'items' => [['product_id' => $product['id'], 'quantity' => 1]],
        ];

        $first = $this->reserve($body, 'Checkout-Attempt');
        self::assertResponseStatusCodeSame(201);

        $second = $this->reserve($body, 'checkout-attempt');
        self::assertResponseStatusCodeSame(201);
        self::assertNotSame($first['id'], $second['id']);
        self::assertNull($this->client->getResponse()->headers->get('Idempotency-Replayed'));
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM reservations'));
    }

    public function testExpiredReservationStopsHoldingStock(): void
    {
        $product = $this->createProduct('KEYBOARD', 1);
        $expired = $this->reserve([
            'ttl_seconds' => 1,
            'items' => [['product_id' => $product['id'], 'quantity' => 1]],
        ], 'short-lived');
        self::assertResponseStatusCodeSame(201);

        sleep(2);

        $replacement = $this->reserve([
            'ttl_seconds' => 120,
            'items' => [['product_id' => $product['id'], 'quantity' => 1]],
        ], 'replacement');
        self::assertResponseStatusCodeSame(201);
        self::assertSame('active', $replacement['status']);

        $this->client->request('GET', '/api/reservations/'.$expired['id']);
        self::assertResponseIsSuccessful();
        self::assertSame('expired', $this->json()['status']);
    }

    public function testConfirmPermanentlyConsumesStockAndReleaseReturnsIt(): void
    {
        $product = $this->createProduct('HEADSET', 2);
        $confirmed = $this->reserve([
            'ttl_seconds' => 120,
            'items' => [['product_id' => $product['id'], 'quantity' => 1]],
        ], 'will-confirm');

        $this->client->request('POST', '/api/reservations/'.$confirmed['id'].'/confirm');
        self::assertResponseIsSuccessful();
        self::assertSame('confirmed', $this->json()['status']);

        $released = $this->reserve([
            'ttl_seconds' => 120,
            'items' => [['product_id' => $product['id'], 'quantity' => 1]],
        ], 'will-release');
        self::assertResponseStatusCodeSame(201);
        $this->client->request('POST', '/api/reservations/'.$released['id'].'/release');
        self::assertResponseIsSuccessful();
        self::assertSame('released', $this->json()['status']);

        $this->client->request('GET', '/api/products/'.$product['id']);
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->json()['available_stock']);
    }

    public function testRequestValidationIsReturnedAsProblemJson(): void
    {
        $this->client->jsonRequest(
            'POST',
            '/api/reservations',
            ['items' => []],
            ['HTTP_IDEMPOTENCY_KEY' => 'invalid-body-test'],
        );

        self::assertResponseStatusCodeSame(422);
        self::assertSame('application/problem+json', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertArrayHasKey('ttl_seconds', $this->json()['errors']);
    }

    public function testIdempotencyKeyHeaderIsMappedAndValidated(): void
    {
        $product = $this->createProduct('HEADER-DTO', 1);
        $this->client->jsonRequest('POST', '/api/reservations', [
            'ttl_seconds' => 60,
            'items' => [['product_id' => $product['id'], 'quantity' => 1]],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('Idempotency-Key', $this->json()['errors']);
    }
}
