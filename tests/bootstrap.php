<?php

// The package has no vendor of its own: the suite runs on the autoload of an
// application that installs it (`PHPUNIT_AUTOLOAD`), or on a sibling package's,
// and adds its own test classes to it.
// Its own vendor first, as the CI installs it; else an application's that installs
// it (`PHPUNIT_AUTOLOAD`), or a sibling package's.
$autoload = getenv('PHPUNIT_AUTOLOAD') ?: (file_exists(__DIR__ . '/../vendor/autoload.php')
    ? __DIR__ . '/../vendor/autoload.php'
    : __DIR__ . '/../../symfony-api/vendor/autoload.php');

if (! file_exists($autoload)) {
    throw new RuntimeException('No composer autoload found: set PHPUNIT_AUTOLOAD to one that installs this package.');
}

/** @var \Composer\Autoload\ClassLoader $loader */
$loader = require $autoload;
$loader->addPsr4('Wexample\\SymfonyLive\\', __DIR__ . '/../src');
$loader->addPsr4('Wexample\\SymfonyLive\\Tests\\', __DIR__);
