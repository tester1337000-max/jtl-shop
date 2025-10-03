<?php

declare(strict_types=1);

use JTL\Helpers\Request;
use JTL\Media\Media;

require_once __DIR__ . '/includes/globalinclude.php';
set_exception_handler(static function ($e) {
    header(Request::makeHTTPHeader(404));
    echo $e->getMessage();
    exit;
});

set_error_handler(static function ($code, $message, $file, $line) {
    throw new Exception(sprintf('%s in file "%s" on line %d', $message, $file, $line));
}, E_ALL & ~(E_STRICT | E_NOTICE));

if (!isset($_GET['img'], $_GET['a']) || !is_array($_GET['img'])) {
    throw new InvalidArgumentException('Missing arguments');
}

Media::getInstance();
