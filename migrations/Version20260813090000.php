<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\Migrations\AbstractMigration;

final class Version20260813090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create products, reservations, and reservation items';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'This migration requires MySQL.');

        $this->addSql(<<<'SQL'
            CREATE TABLE products (
                id CHAR(36) NOT NULL,
                sku VARCHAR(64) NOT NULL,
                name VARCHAR(255) NOT NULL,
                stock INTEGER NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY(id),
                CONSTRAINT products_sku_unique UNIQUE (sku),
                CONSTRAINT products_stock_non_negative CHECK (stock >= 0)
            ) ENGINE = InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE reservations (
                id CHAR(36) NOT NULL,
                idempotency_key VARCHAR(255) NOT NULL,
                request_hash CHAR(64) NOT NULL,
                status VARCHAR(16) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY(id),
                CONSTRAINT reservations_idempotency_key_unique UNIQUE (idempotency_key),
                CONSTRAINT reservations_status_valid CHECK (status IN ('active', 'confirmed', 'released', 'expired'))
            ) ENGINE = InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE reservation_items (
                reservation_id CHAR(36) NOT NULL,
                product_id CHAR(36) NOT NULL,
                quantity INTEGER NOT NULL,
                PRIMARY KEY(reservation_id, product_id),
                CONSTRAINT reservation_items_quantity_positive CHECK (quantity > 0),
                CONSTRAINT reservation_items_reservation_fk FOREIGN KEY (reservation_id) REFERENCES reservations (id) ON DELETE CASCADE,
                CONSTRAINT reservation_items_product_fk FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT
            ) ENGINE = InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
            SQL);
        $this->addSql('CREATE INDEX reservations_expiry_idx ON reservations (status, expires_at)');
        $this->addSql('CREATE INDEX reservation_items_product_idx ON reservation_items (product_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE lock_keys (
                key_id VARCHAR(64) NOT NULL,
                key_token VARCHAR(44) NOT NULL,
                key_expiration INTEGER UNSIGNED NOT NULL,
                PRIMARY KEY(key_id)
            ) ENGINE = InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE lock_keys');
        $this->addSql('DROP TABLE reservation_items');
        $this->addSql('DROP TABLE reservations');
        $this->addSql('DROP TABLE products');
    }
}
