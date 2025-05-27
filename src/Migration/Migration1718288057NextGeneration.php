<?php declare(strict_types=1);

namespace RH\Tweakwise\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1718288057NextGeneration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1718288057;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
ALTER TABLE `s_plugin_rhae_tweakwise_feed`
                ADD `next_generation_at` DATETIME(3),
                ADD `status` VARCHAR(255) NULL,
                ADD `interval` VARCHAR(10) NULL
                ;
SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
