<?php

declare(strict_types=1);

use JTL\dbeS\FileHandler;
use JTL\dbeS\Starter;
use JTL\dbeS\Synclogin;
use JTL\Language\LanguageHelper;
use JTL\Plugin\Helper;
use JTL\Router\Router;
use JTL\Router\State;
use JTL\Shop;
use JTL\Shopsetting;

const DEFINES_PFAD             = __DIR__ . '/../includes/';
const FREIDEFINIERBARER_FEHLER = 8;

require_once DEFINES_PFAD . 'config.JTL-Shop.ini.php';
require_once DEFINES_PFAD . 'defines.php';
require_once PFAD_ROOT . PFAD_INCLUDES . 'autoload.php';
require_once PFAD_ROOT . PFAD_INCLUDES . 'sprachfunktionen.php';
require_once __DIR__ . '/functions.php';

error_reporting(SYNC_LOG_LEVEL);
$shop         = Shop::getInstance();
$db           = Shop::Container()->getDB();
$cache        = Shop::Container()->getCache()->setJtlCacheConfig(
    $db->selectAll('teinstellungen', 'kEinstellungenSektion', CONF_CACHING)
);
$logger       = Shop::Container()->getLogService()->withName('dbeS');
$pluginHooks  = Helper::getHookList();
$language     = LanguageHelper::getInstance($db, $cache);
$languageID   = $language->getLanguageID();
$languageCode = $language->getLanguageCode();
Shop::setLanguage($languageID, $languageCode);
$fileID = $_REQUEST['id'] ?? null;
Shop::setRouter(
    new Router(
        $db,
        $cache,
        new State(),
        Shop::Container()->getAlertService(),
        Shopsetting::getInstance($db, $cache)->getAll()
    )
);
Shop::bootstrap(true, $db, $cache);
ob_start('handleError');
$starter = new Starter(new Synclogin($db, $logger), new FileHandler($logger), $db, $cache, $logger);
$starter->start($fileID, $_POST, $_FILES);
