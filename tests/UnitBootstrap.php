<?php declare(strict_types=1);

/**
 * Minimal bootstrap for unit tests.
 *
 * Unlike TestBootstrap.php this does NOT boot the Shopware kernel and does NOT
 * require a running MySQL server. It only loads the two Composer autoloaders so
 * that Shopware core classes (entities, collections, etc.) are available without
 * a full application container.
 *
 * Usage:
 *   vendor/bin/phpunit --bootstrap tests/UnitBootstrap.php tests/Unit/
 *
 * Or via the named test-suite (see phpunit.xml.dist):
 *   vendor/bin/phpunit --bootstrap tests/UnitBootstrap.php --testsuite "Tweakwise Unit tests"
 */

// Project-root autoloader — provides Shopware core classes.
require __DIR__ . '/../../../../vendor/autoload.php';

// Plugin-local autoloader — provides RH\Tweakwise classes.
require __DIR__ . '/../vendor/autoload.php';
