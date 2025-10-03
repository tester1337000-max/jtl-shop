<?php

declare(strict_types=1);

if (!file_exists(PFAD_ROOT . PFAD_INCLUDES . 'vendor/autoload.php')) {
    header('Content-type: text/html', true, 503);
    echo 'Use "composer install" to install the required dependencies';
    exit;
}
require_once PFAD_ROOT . PFAD_INCLUDES . 'vendor/autoload.php';
