<?php declare(strict_types=1);

namespace RH\Tweakwise\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1744361209EventTagEnabled extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1744361209;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
ALTER TABLE `s_plugin_rhae_tweakwise_frontend`
                ADD `eventTagEnabled` TINYINT(1) NOT NULL DEFAULT '1';
SQL;
        $connection->executeStatement($sql);

    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
