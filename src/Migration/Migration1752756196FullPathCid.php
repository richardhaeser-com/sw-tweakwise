<?php declare(strict_types=1);

namespace RH\Tweakwise\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1752756196FullPathCid extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1752756196;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
ALTER TABLE `s_plugin_rhae_tweakwise_frontend`
                ADD `fullPathCid` TINYINT(1) NOT NULL DEFAULT '0';
SQL;
        $connection->executeUpdate($sql);

    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
