<?php

declare(strict_types=1);

use JTL\Crawler\Controller;
use JTL\Shop;

const JTL_INCLUDE_ONLY_DB = 1;
require_once __DIR__ . '/globalinclude.php';

$controller = new Controller(Shop::Container()->getDB(), Shop::Container()->getCache());
$controller->getResponse();
