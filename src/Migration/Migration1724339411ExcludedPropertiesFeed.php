<?php declare(strict_types=1);

namespace RH\Tweakwise\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1724339411ExcludedPropertiesFeed extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1724339411;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
ALTER TABLE `s_plugin_rhae_tweakwise_feed`
                ADD `excludeChildren` TINYINT(1) NOT NULL DEFAULT '0',
                ADD `excludeReviews` TINYINT(1) NOT NULL DEFAULT '0',
                ADD `excludeTags` TINYINT(1) NOT NULL DEFAULT '0',
                ADD `excludeOptions` TINYINT(1) NOT NULL DEFAULT '0',
                ADD `excludeProperties` TINYINT(1) NOT NULL DEFAULT '0';
SQL;
        $connection->executeUpdate($sql);

    }
}
