<?php declare(strict_types=1);

namespace RH\Tweakwise\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1785389179BackfillBackendSyncProperties extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1785389179;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE `s_plugin_rhae_tweakwise_frontend` SET `backendSyncProperties` = \'{}\' WHERE `backendSyncProperties` IS NULL'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
