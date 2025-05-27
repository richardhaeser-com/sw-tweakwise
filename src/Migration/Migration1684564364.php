<?php declare(strict_types=1);

namespace RH\Tweakwise\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1684564364 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1684564364;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `s_plugin_rhae_tweakwise_sales_channel_domains` (
      `feed_id` BINARY(16) NOT NULL,
      `sales_channel_domain_id` BINARY(16) NOT NULL,
      `created_at` DATETIME(3) NOT NULL,
      PRIMARY KEY (`feed_id`, `sales_channel_domain_id`),
      CONSTRAINT `fk.rhae_tweakwise_sales_channel_domains.feed_id` FOREIGN KEY (`feed_id`)
        REFERENCES `s_plugin_rhae_tweakwise_feed` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
      CONSTRAINT `fk.rhae_tweakwise_sales_channel_domains.sales_channel_domain_id` FOREIGN KEY (`sales_channel_domain_id`)
        REFERENCES `sales_channel_domain` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    )
    ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
    COLLATE=utf8mb4_unicode_ci
SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
