<?php declare(strict_types=1);

namespace RH\Tweakwise\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1702300500 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1702300500;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS `product_cross_selling_tweakwise` (
            `id` BINARY(16) NOT NULL,
            `group_key` varchar(25) NULL,
            `created_at` DATETIME(3) NOT NULL,
            `updated_at` DATETIME(3),
            PRIMARY KEY (`id`)
        )
            ENGINE = InnoDB
            DEFAULT CHARSET = utf8mb4
            COLLATE = utf8mb4_unicode_ci;
        SQL;
        $connection->executeStatement($sql);
        $connection->executeStatement('
          ALTER TABLE `product_cross_selling` ADD `product_cross_selling_tweakwise_id` BINARY(16);
        ');
        $connection->executeStatement('
          ALTER TABLE `product_cross_selling` ADD CONSTRAINT `fk.product_cross_selling.product_cross_selling_tweakwise_id`
          FOREIGN KEY (`product_cross_selling_tweakwise_id`) REFERENCES `product_cross_selling_tweakwise`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
