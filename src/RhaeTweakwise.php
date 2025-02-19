<?php

declare(strict_types=1);

namespace RH\Tweakwise;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

class RhaeTweakwise extends Plugin
{
    public function install(InstallContext $installContext): void
    {
    }

    public function update(UpdateContext $updateContext): void
    {
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $connection = $this->container->get(Connection::class);
        $connection->executeStatement('DROP TABLE IF EXISTS s_plugin_rhae_tweakwise_frontend_sales_channel_domains');
        $connection->executeStatement('DROP TABLE IF EXISTS s_plugin_rhae_tweakwise_sales_channel_domains');
        $connection->executeStatement('DROP TABLE IF EXISTS s_plugin_rhae_tweakwise_frontend');
        $connection->executeStatement('DROP TABLE IF EXISTS s_plugin_rhae_tweakwise_feed');
        $connection->executeStatement("DELETE FROM  product_cross_selling WHERE type = 'tweakwiseRecommendation'");
        $connection->executeStatement('ALTER TABLE `product_cross_selling` DROP FOREIGN KEY `fk.product_cross_selling.product_cross_selling_tweakwise_id`;');
        $connection->executeStatement('ALTER TABLE `product_cross_selling` DROP COLUMN `product_cross_selling_tweakwise_id`;');
        $connection->executeStatement('DROP TABLE IF EXISTS product_cross_selling_tweakwise');
    }
}
