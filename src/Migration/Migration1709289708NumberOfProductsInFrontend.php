<?php declare(strict_types=1);

namespace RH\Tweakwise\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1709289708NumberOfProductsInFrontend extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1709289708;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
ALTER TABLE `s_plugin_rhae_tweakwise_frontend`
                ADD `productsDesktop` TINYINT(1) NOT NULL DEFAULT 0,
                ADD `productsTablet` TINYINT(1) NOT NULL DEFAULT 0,
                ADD `productsMobile` TINYINT(1) NOT NULL DEFAULT 0;
SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
