<?php declare(strict_types=1);

namespace RH\Tweakwise\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1724314951TypeOfFeed extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1724314951;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
ALTER TABLE `s_plugin_rhae_tweakwise_feed`
                ADD `type` VARCHAR(10) DEFAULT 'full'
                ;
SQL;
        $connection->executeUpdate($sql);

    }
}
