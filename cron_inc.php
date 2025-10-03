<?php

declare(strict_types=1);

use JTL\Cron\Checker;
use JTL\Cron\JobFactory;
use JTL\Cron\Queue;
use JTL\Shop;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;

defined('JTLCRON') || define('JTLCRON', true);
//$nStartzeit will be set in globalinclude - just to make sure it exists..
$nStartzeit = time();
if (!defined('PFAD_LOGFILES')) {
    require __DIR__ . '/globalinclude.php';
}
if (SAFE_MODE === true) {
    return;
}
if (PHP_SAPI === 'cli') {
    $handler = new StreamHandler('php://stdout', Level::Debug);
    $handler->setFormatter(new LineFormatter("[%datetime%] %message%\n", null, false, true));
    $logger = new Logger('cron', [$handler], [new PsrLogMessageProcessor()]);
} else {
    $logger = Shop::Container()->getLogService();
    if (isset($_POST['runCron'])) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        ignore_user_abort(true);
        header('Connection: close');
        ob_start();
        echo 'Starting cron';
        $size = ob_get_length();
        header('Content-Length: ' . $size);
        ob_end_flush();
        flush();
    }
}
$db     = Shop::Container()->getDB();
$cache  = Shop::Container()->getCache();
$runner = new Queue($db, $logger, new JobFactory($db, $logger, $cache), (int)$nStartzeit);
$runner->run(new Checker($db, $logger));
