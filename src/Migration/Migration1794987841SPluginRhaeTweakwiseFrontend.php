<?php declare(strict_types=1);

namespace RH\Tweakwise\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1794987841SPluginRhaeTweakwiseFrontend extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794987841;
    }

    public function update(Connection $connection): void
    {
        $query = <<<'SQL'
            ALTER TABLE s_plugin_rhae_tweakwise_frontend ADD functionsInitialisationEnabled TINYINT(1) DEFAULT 0 NOT NULL;
SQL;

        $connection->executeStatement($query);
        $connection->executeStatement(
            'ALTER TABLE s_plugin_rhae_tweakwise_frontend ALTER COLUMN functionsInitialisationEnabled SET DEFAULT 1'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // Add destructive update if necessary
    }
}
