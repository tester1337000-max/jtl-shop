<?php

declare(strict_types=1);

if (!isset($_GET['c'], $_GET['s'])) {
    exit;
}
require __DIR__ . '/../config.JTL-Shop.ini.php';
require __DIR__ . '/functions.php';

outputCaptcha();
