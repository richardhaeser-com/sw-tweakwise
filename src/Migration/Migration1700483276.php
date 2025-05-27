<?php declare(strict_types=1);

namespace RH\Tweakwise\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1700483276 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700483276;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
ALTER TABLE `s_plugin_rhae_tweakwise_frontend`
                ADD `wayOfSearch` VARCHAR(255) NULL;
SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
