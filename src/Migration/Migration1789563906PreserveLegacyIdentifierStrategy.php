<?php declare(strict_types=1);

namespace RH\Tweakwise\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1789563906PreserveLegacyIdentifierStrategy extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1789563906;
    }

    public function update(Connection $connection): void
    {
        $hasExistingConfiguration = (bool) $connection->fetchOne(
            'SELECT EXISTS(SELECT 1 FROM `s_plugin_rhae_tweakwise_feed`) OR EXISTS(SELECT 1 FROM `s_plugin_rhae_tweakwise_frontend`)'
        );

        if ($hasExistingConfiguration) {
            $connection->executeStatement(
                'INSERT INTO `system_config` (`id`, `configuration_key`, `configuration_value`, `created_at`) VALUES (UNHEX(REPLACE(UUID(), \'-\', \'\')), \'RhaeTweakwise.config.useContextBasedIdentifiers\', \'{"_value": false}\', NOW(3)) ON DUPLICATE KEY UPDATE `configuration_value` = VALUES(`configuration_value`)'
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
