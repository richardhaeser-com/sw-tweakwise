<?php declare(strict_types=1);

use Shopware\Core\TestBootstrapper;

require __DIR__ . '/../../../../vendor/autoload.php';

(new TestBootstrapper())->addActivePlugins('RhaeTweakwise')->bootstrap();
