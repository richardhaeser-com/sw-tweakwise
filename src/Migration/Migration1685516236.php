<?php declare(strict_types=1);

namespace RH\Tweakwise\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1685516236 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1685516236;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `s_plugin_rhae_tweakwise_frontend` (
    `id` BINARY(16) NOT NULL,
    `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3),
    PRIMARY KEY (`id`)
)
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;
SQL;
        $connection->executeUpdate($sql);

        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `s_plugin_rhae_tweakwise_frontend_sales_channel_domains` (
      `frontend_id` BINARY(16) NOT NULL,
      `sales_channel_domain_id` BINARY(16) NOT NULL,
      `created_at` DATETIME(3) NOT NULL,
      PRIMARY KEY (`frontend_id`, `sales_channel_domain_id`),
      CONSTRAINT `fk.tw_frontend_sales_channel_domains.frontend_id` FOREIGN KEY (`frontend_id`)
        REFERENCES `s_plugin_rhae_tweakwise_frontend` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
      CONSTRAINT `fk.tw_frontend_sales_channel_domains.sales_channel_domain_id` FOREIGN KEY (`sales_channel_domain_id`)
        REFERENCES `sales_channel_domain` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    )
    ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
    COLLATE=utf8mb4_unicode_ci
SQL;
        $connection->executeUpdate($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
