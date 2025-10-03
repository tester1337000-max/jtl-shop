<?php

/**
 * Sets up MinApp controller and serves files
 * @package Minify
 */

declare(strict_types=1);

use Minify\App;

$app = (require __DIR__ . '/bootstrap.php');
/* @var App $app */

$app->runServer();
