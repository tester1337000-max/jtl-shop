<?php

declare(strict_types=1);

use JTL\Debug\DataCollector\Smarty;
use JTL\Filter\Metadata;
use JTL\Language\LanguageHelper;
use JTL\Profiler;
use JTL\Router\Router;
use JTL\Router\State;
use JTL\Session\Frontend;
use JTL\Shop;
use JTL\Shopsetting;
use JTL\Smarty\ContextType;
use JTL\Smarty\JTLSmarty;

$nStartzeit = microtime(true);

if (file_exists(__DIR__ . '/config.JTL-Shop.ini.php')) {
    require_once __DIR__ . '/config.JTL-Shop.ini.php';
}

/**
 * @param string $message
 */
function handleFatal(string $message): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache', true, 500);
    die($message);
}

if (!defined('PFAD_ROOT')) {
    handleFatal('Could not load configuration file. For shop installation <a href="install/">click here</a>.');
}

require_once PFAD_ROOT . 'includes/defines.php';
require_once PFAD_ROOT . PFAD_INCLUDES . 'autoload.php';
require_once PFAD_ROOT . PFAD_INCLUDES . 'sprachfunktionen.php';

defined('DB_HOST') || handleFatal('Kein MySql-Datenbankhost angegeben. Bitte config.JTL-Shop.ini.php bearbeiten!');
defined('DB_NAME') || handleFatal('Kein MySql Datenbanknamen angegeben. Bitte config.JTL-Shop.ini.php bearbeiten!');
defined('DB_USER') || handleFatal('Kein MySql-Datenbankbenutzer angegeben. Bitte config.JTL-Shop.ini.php bearbeiten!');
defined('DB_PASS') || handleFatal('Kein MySql-Datenbankpasswort angegeben. Bitte config.JTL-Shop.ini.php bearbeiten!');

Profiler::start();

$db    = null;
$cache = null;
$shop  = Shop::getInstance();
try {
    $db = Shop::Container()->getDB();
} catch (Exception $exc) {
    handleFatal($exc->getMessage());
}
if (!defined('CLI_BATCHRUN') && $db !== null) {
    $cache = Shop::Container()->getCache();
    $cache->setJtlCacheConfig($db->selectAll('teinstellungen', 'kEinstellungenSektion', CONF_CACHING));
    $language = LanguageHelper::getInstance($db, $cache);
    Shop::setLanguage($language->getLanguageID(), $language->getLanguageCode());
    if (!JTL_INCLUDE_ONLY_DB) {
        $config   = Shopsetting::getInstance($db, $cache)->getAll();
        $debugbar = Shop::Container()->getDebugBar();
        Shop::setRouter(new Router($db, $cache, new State(), Shop::Container()->getAlertService(), $config));
        $globalMetaData = Metadata::getGlobalMetaData();
        $session        = (defined('JTLCRON') && JTLCRON === true)
            ? Frontend::getInstance(true, true, 'JTLCRON')
            : Frontend::getInstance();
        Shop::bootstrap(true, $db, $cache);
        executeHook(HOOK_GLOBALINCLUDE_INC);
        $session->deferredUpdate();
        $smarty = new JTLSmarty(false, ContextType::FRONTEND, $config);
        $debugbar->addCollector(new Smarty($smarty));
    }
}
