<?php

declare(strict_types=1);

use JTL\Shop;
use Tests\Integration\TestDatabase;

$integrationTesting = false;
foreach ($_SERVER['argv'] ?? [] as $phpunitArgument) {
    if (str_contains($phpunitArgument, 'integration')) {
        $integrationTesting = true;
        break;
    }
}
if ($integrationTesting) {
    require_once __DIR__ . '/../includes/config.JTL-Shop.ini.php';
} else {
    define('PFAD_ROOT', dirname(__DIR__) . '/');
    define('URL_SHOP', 'http://localhost/');
}

require_once __DIR__ . '/../includes/defines.php';
require_once __DIR__ . '/../includes/autoload.php';
require_once __DIR__ . '/globalFunctions.php';

if ($integrationTesting) {
    $testDB = new TestDatabase(Shop::Container()->getDB(), Shop::Container()->getCache());
    $testDB->importSchema();
    $testDB->setRouter();
    $testDB->migrate();
    $testDB->setTemplateSettings();
    $testDB->setCoreSettings();
    require_once __DIR__ . '/../includes/globalinclude.php';
}
