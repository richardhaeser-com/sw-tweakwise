<?php declare(strict_types=1);

namespace RH\Tweakwise\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1707852677CheckoutSales extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1707852677;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
ALTER TABLE `s_plugin_rhae_tweakwise_frontend`
                ADD `checkoutSales` VARCHAR(50) NULL,
                ADD `checkoutSalesRecommendationsGroupKey` VARCHAR(50) NULL,
                ADD `checkoutSalesFeaturedProductsId` VARCHAR(50) NULL;
SQL;
        $connection->executeUpdate($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
