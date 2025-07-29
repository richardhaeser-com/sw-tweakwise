<?php declare(strict_types=1);

namespace RH\Tweakwise\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1750340134LimitFeed extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1750340134;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
ALTER TABLE `s_plugin_rhae_tweakwise_feed`
                ADD `includeCustomFields` TINYINT(1) NOT NULL DEFAULT '1',
                ADD `limit` VARCHAR(50) NOT NULL DEFAULT '1';
SQL;
        $connection->executeUpdate($sql);

    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
