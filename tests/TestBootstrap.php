<?php declare(strict_types=1);

use Shopware\Core\TestBootstrapper;

require __DIR__ . '/../../../../vendor/autoload.php';

// Shopware 6.5.x doesn't have Shopware\Core\Test\Stub\Framework\IdsCollection
// (introduced later) — it shipped the identical class at
// Shopware\Core\Framework\Test\IdsCollection, which is also what 6.5.x's
// ProductBuilder type-hints. Alias the new location to the old one so tests
// written against the new location run unmodified on older core versions.
if (!class_exists(\Shopware\Core\Test\Stub\Framework\IdsCollection::class)
    && class_exists(\Shopware\Core\Framework\Test\IdsCollection::class)
) {
    class_alias(\Shopware\Core\Framework\Test\IdsCollection::class, \Shopware\Core\Test\Stub\Framework\IdsCollection::class);
}

(new TestBootstrapper())->addActivePlugins('RhaeTweakwise')->bootstrap();
