<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;

final readonly class HealthCheckRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function check(): void
    {
        $this->connection->executeQuery('SELECT 1');
    }
}

