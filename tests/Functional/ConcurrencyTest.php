<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class ConcurrencyTest extends ApiTestCase
{
    public function testParallelRequestsCannotOversellOneUnit(): void
    {
        $product = $this->createProduct('ONE-ONLY', 1);
        $outcomes = $this->runWorkers($product['id'], static fn (int $index): string => 'parallel-'.$index);

        self::assertSame(1, count(array_filter($outcomes, static fn (string $outcome): bool => str_starts_with($outcome, 'created:'))), json_encode($outcomes));
        self::assertSame(7, count(array_filter($outcomes, static fn (string $outcome): bool => $outcome === '409')), json_encode($outcomes));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT SUM(quantity) FROM reservation_items'));
    }

    public function testParallelRetriesCreateOnlyOneReservation(): void
    {
        $product = $this->createProduct('RETRY-ONLY', 1);
        $outcomes = $this->runWorkers($product['id'], static fn (int $_index): string => 'same-retry-key');

        $reservationIds = array_map(
            static fn (string $outcome): string => substr($outcome, strlen('created:')),
            array_filter($outcomes, static fn (string $outcome): bool => str_starts_with($outcome, 'created:')),
        );
        self::assertCount(8, $reservationIds, json_encode($outcomes));
        self::assertCount(1, array_unique($reservationIds));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM reservations'));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT SUM(quantity) FROM reservation_items'));
    }

    /** @param callable(int): string $keyForIndex
     *  @return list<string>
     */
    private function runWorkers(string $productId, callable $keyForIndex): array
    {
        $processes = [];
        $startAt = microtime(true) + 0.5;

        for ($index = 0; $index < 8; ++$index) {
            $items = [['product_id' => $productId, 'quantity' => 1]];
            $canonical = json_encode(['items' => $items, 'ttl_seconds' => 300], JSON_THROW_ON_ERROR);
            $payload = base64_encode(json_encode([
                'items' => $items,
                'ttl_seconds' => 300,
                'key' => $keyForIndex($index),
                'hash' => hash('sha256', $canonical),
            ], JSON_THROW_ON_ERROR));

            $pipes = [];
            $process = proc_open(
                [PHP_BINARY, dirname(__DIR__).'/Support/reserve_once.php', $payload, (string) $startAt],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                dirname(__DIR__, 2),
            );
            self::assertIsResource($process);
            $processes[] = [$process, $pipes];
        }

        $outcomes = [];
        foreach ($processes as [$process, $pipes]) {
            $outcomes[] = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $error);
        }

        return $outcomes;
    }
}
