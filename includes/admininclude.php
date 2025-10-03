<?php

declare(strict_types=1);

use JTL\Helpers\Request;
use JTL\Language\LanguageHelper;
use JTL\Profiler;
use JTL\Router\Router;
use JTL\Router\State;
use JTL\Services\JTL\CaptchaServiceInterface;
use JTL\Services\JTL\SimpleCaptchaService;
use JTL\Session\Backend;
use JTL\Shop;
use JTL\Shopsetting;
use JTL\Smarty\BackendSmarty;
use JTL\Update\Updater;

if (isset($_REQUEST['safemode'])) {
    $GLOBALS['plgSafeMode'] = in_array(strtolower($_REQUEST['safemode']), ['1', 'on', 'ein', 'true', 'wahr']);
}
const DEFINES_PFAD = __DIR__ . '/../../includes/';
require DEFINES_PFAD . 'config.JTL-Shop.ini.php';
require DEFINES_PFAD . 'defines.php';

error_reporting(ADMIN_LOG_LEVEL);
date_default_timezone_set(SHOP_TIMEZONE);

defined('DB_HOST') || die('Kein MySQL-Datenbankhost angegeben. Bitte config.JTL-Shop.ini.php bearbeiten!');
defined('DB_NAME') || die('Kein MySQL Datenbankname angegeben. Bitte config.JTL-Shop.ini.php bearbeiten!');
defined('DB_USER') || die('Kein MySQL-Datenbankbenutzer angegeben. Bitte config.JTL-Shop.ini.php bearbeiten!');
defined('DB_PASS') || die('Kein MySQL-Datenbankpasswort angegeben. Bitte config.JTL-Shop.ini.php bearbeiten!');

require PFAD_ROOT . PFAD_INCLUDES . 'autoload.php';
require PFAD_ROOT . PFAD_INCLUDES . 'sprachfunktionen.php';

function routeRedirect(string $route): never
{
    header('Location: ' . Shop::getAdminURL() . '/' . $route, true, 308);
    exit();
}

Profiler::start();
Shop::setIsFrontend(false);
$db       = Shop::Container()->getDB();
$cache    = Shop::Container()->getCache()->setJtlCacheConfig(
    $db->selectAll('teinstellungen', 'kEinstellungenSektion', CONF_CACHING)
);
$session  = Backend::getInstance();
$lang     = LanguageHelper::getInstance($db, $cache);
$oAccount = Shop::Container()->getAdminAccount();
Shop::setRouter(
    new Router(
        $db,
        $cache,
        new State(),
        Shop::Container()->getAlertService(),
        Shopsetting::getInstance($db, $cache)->getAll()
    )
);
$smarty = new BackendSmarty($db, $cache);

Shop::Container()->singleton(CaptchaServiceInterface::class, static function () {
    return new SimpleCaptchaService(true);
});
$hasUpdates = (new Updater($db))->hasPendingUpdates();
if ($hasUpdates === false && Request::pInt('noBootstrap') === 0) {
    Shop::bootstrap(false, $db, $cache);
}
$smarty->assign('account', $oAccount->account())
    ->assign('favorites', $oAccount->favorites())
    ->assign('hasPendingUpdates', $hasUpdates);
