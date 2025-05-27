<?php declare(strict_types=1);

namespace RH\Tweakwise\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1685629462 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1685629462;
    }

    public function update(Connection $connection): void
    {
        // implement update
        $sql = <<<SQL
ALTER TABLE `s_plugin_rhae_tweakwise_feed`
                ADD last_started_at DATETIME(3),
                ADD last_generated_at DATETIME(3);
SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
