<?php

/**
 * Sets up autoloading and returns the Minify\App
 */

declare(strict_types=1);

use Minify\App;

call_user_func(static function () {
    define('JTL_INCLUDE_ONLY_DB', true);
    require_once __DIR__ . '/../../globalinclude.php';
});

return new App(__DIR__);
