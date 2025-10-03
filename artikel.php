<?php

declare(strict_types=1);

use JTL\Shop;

if (!defined('PFAD_ROOT')) {
    http_response_code(400);
    exit();
}
Shop::dispatch();
