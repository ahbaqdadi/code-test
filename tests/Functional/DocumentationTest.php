<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class DocumentationTest extends ApiTestCase
{
    public function testOpenApiDocumentDescribesTheReservationContract(): void
    {
        $this->client->request('GET', '/api/doc.json');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $document = $this->json();

        self::assertSame('3.0.0', $document['openapi']);
        self::assertSame('Inventory Reservation API', $document['info']['title']);
        self::assertArrayHasKey('/api/reservations', $document['paths']);
        self::assertArrayHasKey('/api/reservations/{id}/confirm', $document['paths']);
        self::assertTrue($document['paths']['/api/reservations']['post']['parameters'][0]['required']);
        self::assertSame('Idempotency-Key', $document['paths']['/api/reservations']['post']['parameters'][0]['name']);
        self::assertSame(
            '#/components/schemas/ReservationResponse',
            $document['paths']['/api/reservations']['post']['responses']['201']['content']['application/json']['schema']['$ref'],
        );
        self::assertArrayHasKey('CreateReservationRequest', $document['components']['schemas']);
        self::assertArrayHasKey('ApiProblemResponse', $document['components']['schemas']);
    }

    public function testSwaggerUiUsesLocallyInstalledAssets(): void
    {
        $this->client->request('GET', '/api/doc');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('<title>Inventory Reservation API</title>', $html);
        self::assertStringContainsString('/bundles/nelmioapidoc/swagger-ui/swagger-ui.css', $html);
    }
}
