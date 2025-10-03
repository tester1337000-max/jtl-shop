<?php

declare(strict_types=1);

namespace JTL;

use Exception;
use JTL\Cache\JTLCacheInterface;
use JTL\Catalog\Category\Kategorie;
use JTL\Cron\Starter\StarterFactory;
use JTL\DB\DbInterface;
use JTL\Events\Dispatcher;
use JTL\Events\Event;
use JTL\Filter\Config;
use JTL\Filter\ProductFilter;
use JTL\Helpers\Product;
use JTL\Helpers\Request;
use JTL\Helpers\Tax;
use JTL\Language\LanguageHelper;
use JTL\Mapper\PageTypeToPageName;
use JTL\Plugin\Helper as PluginHelper;
use JTL\Plugin\LegacyPluginLoader;
use JTL\Plugin\PluginLoader;
use JTL\Plugin\State;
use JTL\Router\Router;
use JTL\Router\State as RoutingState;
use JTL\Services\DefaultServicesInterface;
use JTL\Services\Factory;
use JTL\Session\Frontend;
use JTL\Smarty\ContextType;
use JTL\Smarty\JTLSmarty;
use JTLShop\SemVer\Version;
use stdClass;

use function Functional\first;
use function Functional\map;
use function Functional\tail;

/**
 * Class Shop
 * @package JTL
 * @method static LanguageHelper Lang()
 * @method static Smarty\JTLSmarty Smarty(bool $fast_init = false, string $context = null)
 * @method static bool has(string $key)
 * @method static Shop set(string $key, mixed $value)
 * @method static null|mixed get($key)
 */
final class Shop extends ShopBC
{
    public static string $cISO = '';

    public static int $kSprache = 0;

    private static ?DefaultServicesInterface $container = null;

    private static ?Shop $instance = null;

    private static ?string $imageBaseURL = null;

    /**
     * @var array<int, array<int, string>>
     */
    private static array $url = [];

    private static bool $isFrontend = true;

    public static string $uri = '';

    protected static ?bool $logged = null;

    protected static ?string $adminToken = null;

    protected static ?string $adminLangTag = null;

    /**
     * @var array<string, mixed>
     */
    private array $registry = [];

    public static ?ProductFilter $productFilter = null;

    private static RoutingState $state;

    private static Router $router;

    /**
     * @var array<string, string>
     */
    private static array $mapping = [
        'Lang'   => 'getLanguageHelper',
        'Smarty' => 'getSmarty',
        'has'    => 'registryHas',
        'set'    => 'registrySet',
        'get'    => 'registryGet'
    ];

    /**
     * @var array{host: string, scheme: string, locale: string, iso: string, id: int,
     *      default: string, prefix: string, currency: bool, localized: bool}[]
     */
    public static array $forceHost = [
        [
            'host'      => '',
            'scheme'    => '',
            'locale'    => '',
            'iso'       => '',
            'id'        => 0,
            'default'   => 'N',
            'prefix'    => '/',
            'currency'  => false,
            'localized' => false
        ]
    ];

    private function __construct()
    {
        self::$state    = new RoutingState();
        self::$instance = $this;
    }

    public static function getInstance(): self
    {
        return self::$instance ?? new self();
    }

    /**
     * object wrapper - this allows to call NiceDB->query() etc.
     *
     * @param array<mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return ($mapping = self::map($method)) !== null
            ? $this->$mapping(...$arguments)
            : null;
    }

    public static function getRouter(): Router
    {
        return self::$router;
    }

    public static function setRouter(Router $router): void
    {
        self::$router = $router;
    }

    /**
     * static wrapper - this allows to call Shop::Container()->getDB()->query() etc.
     * @param array<mixed> $arguments
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        return ($mapping = self::map($method)) !== null
            ? self::getInstance()->$mapping(...$arguments)
            : null;
    }

    public function registryGet(string $key): mixed
    {
        return $this->registry[$key] ?? null;
    }

    public function registrySet(string $key, mixed $value): self
    {
        $this->registry[$key] = $value;

        return $this;
    }

    public function registryHas(string $key): bool
    {
        return isset($this->registry[$key]);
    }

    private static function map(string $method): ?string
    {
        return self::$mapping[$method] ?? null;
    }

    public static function setImageBaseURL(string $url): void
    {
        self::$imageBaseURL = \rtrim($url, '/') . '/';
    }

    public static function getImageBaseURL(): string
    {
        if (self::$imageBaseURL === null) {
            $url                = \defined('IMAGE_BASE_URL') ? \IMAGE_BASE_URL : self::getURL();
            self::$imageBaseURL = \rtrim($url, '/') . '/';
        }

        return self::$imageBaseURL;
    }

    public function getLanguageHelper(): LanguageHelper
    {
        return LanguageHelper::getInstance();
    }

    /**
     * @phpstan-param ContextType::*|null $context
     */
    public function getSmarty(bool $fast = false, ?string $context = null): JTLSmarty
    {
        $context = $context ?? (self::isFrontend() ? ContextType::FRONTEND : ContextType::BACKEND);

        return JTLSmarty::getInstance($fast, $context);
    }

    /**
     * quick&dirty debugging
     *
     * @param mixed      $var - the variable to debug
     * @param bool       $die - set true to die() afterwards
     * @param mixed|null $beforeString - a prefix string
     * @param int        $backtrace - backtrace depth
     */
    public static function dbg(mixed $var, bool $die = false, mixed $beforeString = null, int $backtrace = 0): void
    {
        $nl    = \PHP_SAPI === 'cli' ? \PHP_EOL : '<br>';
        $trace = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, $backtrace);
        /** @var array{file: string, line: int, function: string, class: string, type: string} $callee */
        $callee = first($trace);
        $base   = \pathinfo($callee['file'], \PATHINFO_BASENAME);
        echo $base . ':' . $callee['line'] . ' ';
        if ($beforeString !== null) {
            echo $beforeString . $nl;
        }
        if (\PHP_SAPI !== 'cli') {
            echo '<pre>';
        }
        /** @noinspection ForgottenDebugOutputInspection */
        \var_dump($var);
        if ($backtrace > 0) {
            echo $nl . 'Backtrace:' . $nl;
            /** @noinspection ForgottenDebugOutputInspection */
            \var_dump(tail($trace));
        }
        if (\PHP_SAPI !== 'cli') {
            echo '</pre>';
        }
        if ($die === true) {
            die();
        }
    }

    /**
     * @return ($iso is true ? string : int)
     */
    public static function getLanguage(bool $iso = false): int|string
    {
        return $iso === false ? self::$kSprache : self::$cISO;
    }

    public static function getLanguageID(): int
    {
        return self::$kSprache;
    }

    public static function getLanguageCode(): string
    {
        return self::$cISO;
    }

    public static function setLanguage(int $languageID, ?string $iso = null): void
    {
        self::$kSprache = $languageID;
        if ($iso !== null) {
            self::$cISO = $iso;
        }
    }

    /**
     * @param int|int[] $config
     * @return array<string, mixed>
     */
    public static function getSettings(int|array $config): array
    {
        return Shopsetting::getInstance()->getSettings($config);
    }

    /**
     * @return array<string, mixed>
     * @throws \InvalidArgumentException
     * @since 5.2.0
     */
    public static function getSettingSection(int $sectionID): array
    {
        $section = Shopsetting::getInstance()->getSection($sectionID);
        if ($section === null) {
            throw new \InvalidArgumentException('Section ' . $sectionID . ' not found');
        }

        return $section;
    }

    public static function getSettingValue(int $section, string $option): mixed
    {
        return Shopsetting::getInstance()->getValue($section, $option);
    }

    public static function bootstrap(bool $isFrontend, DbInterface $db, JTLCacheInterface $cache): void
    {
        self::$isFrontend = $isFrontend;
        if (\SAFE_MODE === true) {
            return;
        }
        self::defineDeprecatedConstants();
        $cacheID = 'plgnbtstrp';
        /** @var stdClass[]|false $plugins */
        $plugins = $cache->get($cacheID);
        if ($plugins === false) {
            $plugins = map(
                $db->getObjects(
                    'SELECT kPlugin AS id, bExtension AS modern
                        FROM tplugin
                        WHERE nStatus = :state
                          AND bBootstrap = 1
                        ORDER BY nPrio ASC',
                    ['state' => State::ACTIVATED]
                ) ?: [],
                static function (stdClass $e): stdClass {
                    $e->id     = (int)$e->id;
                    $e->modern = (int)$e->modern;

                    return $e;
                }
            );
            $cache->set($cacheID, $plugins, [\CACHING_GROUP_PLUGIN]);
        }
        $dispatcher      = Dispatcher::getInstance();
        $extensionLoader = new PluginLoader($db, $cache);
        $pluginLoader    = new LegacyPluginLoader($db, $cache);
        foreach ($plugins as $plugin) {
            $loader = $plugin->modern === 1 ? $extensionLoader : $pluginLoader;
            if (($p = PluginHelper::bootstrap($plugin->id, $loader)) !== null) {
                $p->boot($dispatcher);
                $p->loaded();
            }
        }
    }

    private static function defineDeprecatedConstants(): void
    {
        /**
         * @deprecated CONSISTENT_GROSS_PRICES
         */
        if (\defined('CONSISTENT_GROSS_PRICES')) {
            if (!isset($_SESSION['CONSISTENT_GROSS_PRICES_logged'])) {
                self::Container()->getLogService()->error(
                    'The define-constant CONSISTENT_GROSS_PRICES is deprecated. ' .
                    'Please do not set it in "config.JTL-Shop.ini.php" anymore. ' .
                    'it is now controlled through the Shop setting "consistent_gross_prices"'
                );
                $_SESSION['CONSISTENT_GROSS_PRICES_logged'] = 1;
            }
        } else {
            \define(
                'CONSISTENT_GROSS_PRICES',
                self::getSettingValue(\CONF_GLOBAL, 'consistent_gross_prices') === 'Y'
            );
        }
    }

    public static function isFrontend(): bool
    {
        return self::$isFrontend === true;
    }

    public static function setIsFrontend(bool $isFrontend): void
    {
        self::$isFrontend = $isFrontend;
    }

    public static function dispatch(): void
    {
        self::run();
        self::$router->dispatch(self::Smarty(false, ContextType::FRONTEND));
    }

    public static function run(): ProductFilter
    {
        self::$state = self::$router->init();
        self::setParams(self::$state->getAsParams());
        if (self::$state->productsPerPage !== 0) {
            $_SESSION['ArtikelProSeite'] = self::$state->productsPerPage;
        }
        self::$isInitialized = true;
        $conf                = new Config();
        $conf->setLanguageID(self::getLanguageID());
        $conf->setLanguages(LanguageHelper::getAllLanguages());
        $conf->setCustomerGroupID(Frontend::getCustomerGroup()->getID());
        $conf->setConfig(Shopsetting::getInstance()->getAll());
        $conf->setBaseURL(self::getURL() . '/');
        self::setImageBaseURL(\defined('IMAGE_BASE_URL') ? \IMAGE_BASE_URL : self::getURL());
        self::Container()->getConsentManager()->initActiveItems(self::getLanguageID());
        self::$productFilter = new ProductFilter($conf, self::Container()->getDB(), self::Container()->getCache());
        self::getLanguageFromServerName();
        Dispatcher::getInstance()->fire(Event::RUN);
        $starterFactory = new StarterFactory(self::getSettingSection(\CONF_CRON));
        $starterFactory->getStarter()->start();

        return self::$productFilter;
    }

    public static function getState(): RoutingState
    {
        return self::$state;
    }

    public static function validateState(): void
    {
        if (
            self::$state->categoryID > 0
            && !Kategorie::isVisible(self::$state->categoryID, Frontend::getCustomerGroup()->getID())
        ) {
            self::$state->categoryID = 0;
            self::$kKategorie        = 0;
            self::$state->is404      = true;
            self::$is404             = true;
        }
        if (self::$state->productID > 0 && Product::isVariChild(self::$state->productID)) {
            self::$state->childProductID = self::$state->productID;
            self::$state->productID      = Product::getParent(self::$state->productID);
            self::$kVariKindArtikel      = self::$state->childProductID;
            self::$kArtikel              = self::$state->productID;
        }
        if (self::$state->productID > 0) {
            $redirect = Request::verifyGPCDataInt('r');
            if (
                $redirect > 0
                && (self::$state->newsItemID > 0 // get param "n" was used as product amount
                    || (isset($_GET['n']) && (float)$_GET['n'] > 0)) // product amount was a float >0 and <1
            ) {
                // GET param "n" is often misused as "amount of product"
                self::$state->newsItemID = 0;
                if ($redirect === \R_LOGIN_WUNSCHLISTE) {
                    // login redirect on wishlist add when not logged in uses get param "n" as amount
                    // and "a" for the product ID - but we want to go to the login page, not to the product page
                    self::$state->productID = 0;
                    self::$kArtikel         = 0;
                }
            } elseif (
                ($redirect === \R_LOGIN_BEWERTUNG || $redirect === \R_LOGIN_TAG)
                && Frontend::getCustomer()->getID() > 0
            ) {
                // avoid redirect to product page for ratings that require logged in customers
                self::$state->productID = 0;
                self::$kArtikel         = 0;
            }
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function setParams(array $params): void
    {
        foreach ($params as $key => $val) {
            if (\property_exists(__CLASS__, $key)) {
                self::${$key} = $val;
            }
        }
    }

    /**
     * @return array{kKonfigPos: int, kKategorie: int, kArtikel: int, kVariKindArtikel: int, kSeite: int, kLink: int,
     *      kHersteller: int, kSuchanfrage: int, kMerkmalWert: int, kSuchspecial: int, suchspecial: int, kNews: int,
     *      kNewsMonatsUebersicht: int, kNewsKategorie: int, nBewertungSterneFilter: int, cPreisspannenFilter: string,
     *      manufacturerFilters: int[], kHerstellerFilter: int, categoryFilters: int[], MerkmalFilter_arr: int[],
     *      kKategorieFilter: int, searchSpecialFilters: int[], kSuchFilter: int, kSuchspecialFilter: int,
     *      SuchFilter_arr: int[], nDarstellung: int, nSort: int, nSortierung: int, show: int, vergleichsliste: int,
     *      bFileNotFound: bool, is404: bool, cCanonicalURL: string, nLinkart: int, nSterne: int, kWunschliste: int,
     *      nAnzahl: int, optinCode: string, cSuche: string, nArtikelProSeite: int}
     */
    public static function getParameters(): array
    {
        return self::$state->getAsParams();
    }

    private static function getLanguageFromServerName(): void
    {
        if (\EXPERIMENTAL_MULTILANG_SHOP !== true) {
            return;
        }
        foreach (Frontend::getLanguages() as $language) {
            $code = \mb_convert_case($language->getCode(), \MB_CASE_UPPER);
            /** @var string $shopURL */
            $shopURL = \defined('URL_SHOP_' . $code) ? \constant('URL_SHOP_' . $code) : \URL_SHOP;
            if ($_SERVER['HTTP_HOST'] === \parse_url($shopURL, \PHP_URL_HOST)) {
                self::setLanguage($language->getId(), $language->getCode());
                break;
            }
        }
    }

    public static function seoCheckFinish(): void
    {
        self::$MerkmalFilter                  = ProductFilter::initCharacteristicFilter();
        self::$SuchFilter                     = ProductFilter::initSearchFilter();
        self::$categoryFilterIDs              = ProductFilter::initCategoryFilter();
        self::$state->characteristicFilterIDs = self::$MerkmalFilter;
        self::$state->searchFilterIDs         = self::$SuchFilter;
        self::$state->categoryFilterIDs       = self::$categoryFilterIDs;

        \executeHook(\HOOK_SEOCHECK_ENDE);
    }

    public static function updateLanguage(int $languageID, ?string $iso = null): void
    {
        $iso = $iso ?? self::Lang()->getIsoFromLangID($languageID)->cISO ?? '';
        if ($iso !== $_SESSION['cISOSprache']) {
            Frontend::checkReset($iso);
            Tax::setTaxRates();
        }
        if (self::$productFilter !== null && self::$productFilter->getFilterConfig()->getLanguageID() !== $languageID) {
            self::$productFilter->getFilterConfig()->setLanguageID($languageID);
            self::$productFilter->initBaseStates();
        }
        $customer     = Frontend::getCustomer();
        $customerLang = $customer->getLanguageID();
        if ($customerLang > 0 && $customerLang !== $languageID) {
            $customer->setLanguageID($languageID);
            $customer->updateInDB();
        }
        if ($languageID !== self::$kSprache) {
            self::Container()->getLinkService()->updateDefaultLanguageData($languageID, $iso);
        }
        self::setLanguage($languageID, $iso);
    }

    /**
     * decide which page to load
     */
    public static function getEntryPoint(): string
    {
        return self::$state->fileName;
    }

    /**
     * build product filter object from parameters
     * @param array<string, mixed> $params
     */
    public static function buildProductFilter(
        array $params,
        ProductFilter|stdClass|null $productFilter = null,
        bool $validate = true
    ): ProductFilter {
        $pf = new ProductFilter(
            Config::getDefault(),
            self::Container()->getDB(),
            self::Container()->getCache()
        );
        if ($productFilter !== null) {
            foreach (\get_object_vars($productFilter) as $k => $v) {
                $pf->$k = $v;
            }
        }

        return $pf->initStates($params, $validate);
    }

    public static function getProductFilter(): ProductFilter
    {
        if (self::$productFilter === null) {
            self::$productFilter = self::buildProductFilter([]);
        }

        return self::$productFilter;
    }

    public static function setProductFilter(ProductFilter $productFilter): void
    {
        self::$productFilter = $productFilter;
    }

    public static function getShopDatabaseVersion(): Version
    {
        $version = self::Container()->getDB()->getSingleObject('SELECT nVersion FROM tversion');
        if ($version === null) {
            return Version::parse('0.0.0');
        }
        $version = $version->nVersion;
        if ($version === '5') {
            $version = '5.0.0';
        }

        return Version::parse($version);
    }

    public static function getLogo(bool $fullUrl = false): ?string
    {
        $ret  = null;
        $logo = self::getSettingValue(\CONF_LOGO, 'shop_logo');
        if ($logo !== null && $logo !== '') {
            $ret = \PFAD_SHOPLOGO . $logo;
        } elseif (\is_dir(\PFAD_ROOT . \PFAD_SHOPLOGO)) {
            $dir = \opendir(\PFAD_ROOT . \PFAD_SHOPLOGO);
            if (!$dir) {
                return '';
            }
            while (($file = \readdir($dir)) !== false) {
                if ($file !== '.' && $file !== '..' && \str_contains($file, \SHOPLOGO_NAME)) {
                    $ret = \PFAD_SHOPLOGO . $file;
                    break;
                }
            }
        }

        return $ret === null
            ? null
            : ($fullUrl === true
                ? self::getImageBaseURL()
                : '') . $ret;
    }

    /**
     * @param array<int, array<int, string>> $urls
     */
    public static function setURLs(array $urls): void
    {
        self::$url = $urls;
    }

    /**
     * @return string - the shop URL without trailing slash
     */
    public static function getURL(bool $forceSSL = false, ?int $langID = null): string
    {
        $langID = $langID ?? self::$kSprache;
        $idx    = (int)$forceSSL;
        if (isset(self::$url[$langID][$idx]) && self::isFrontend()) {
            return self::$url[$langID][$idx];
        }
        $url                      = self::buildBaseURL($forceSSL);
        self::$url[$langID][$idx] = $url;

        return $url;
    }

    /**
     * @return string - the shop Admin URL without trailing slash
     */
    public static function getAdminURL(bool $forceSSL = false): string
    {
        return \rtrim(self::buildBaseURL($forceSSL) . '/' . \PFAD_ADMIN, '/');
    }

    private static function buildBaseURL(bool $forceSSL): string
    {
        $url = \URL_SHOP;
        if (\str_starts_with($url, 'http://')) {
            $sslStatus = Request::checkSSL();
            if ($sslStatus === 2) {
                $url = \str_replace('http://', 'https://', $url);
            } elseif ($sslStatus === 4 || ($sslStatus === 3 && $forceSSL)) {
                $url = \str_replace('http://', 'https://', $url);
            }
        }

        return \rtrim($url, '/');
    }

    public static function setPageType(int $pageType): void
    {
        $mapper                = new PageTypeToPageName();
        self::$pageType        = $pageType;
        self::$state->pageType = $pageType;
        self::$AktuelleSeite   = $mapper->map($pageType);
        \executeHook(\HOOK_SHOP_SET_PAGE_TYPE, [
            'pageType' => self::$pageType,
            'pageName' => self::$AktuelleSeite
        ]);
    }

    public static function getPageType(): int
    {
        return self::$state->pageType ?? \PAGE_UNBEKANNT;
    }

    public static function isAdmin(bool $sessionSwitchAllowed = false): bool
    {
        if (\is_bool(self::$logged)) {
            return self::$logged;
        }
        if (\session_name() === 'eSIdAdm') {
            // admin session already active
            self::$logged       = self::Container()->getAdminAccount()->logged();
            self::$adminToken   = $_SESSION['jtl_token'];
            self::$adminLangTag = $_SESSION['AdminAccount']->language;
        } elseif (!empty($_SESSION['loggedAsAdmin']) && $_SESSION['loggedAsAdmin'] === true) {
            // frontend session has been notified by admin session
            self::$logged       = true;
            self::$adminToken   = $_SESSION['adminToken'];
            self::$adminLangTag = $_SESSION['adminLangTag'];
            self::Container()->getGetText()->setLanguage(self::$adminLangTag);
        } elseif (
            $sessionSwitchAllowed === true
            && isset($_COOKIE['eSIdAdm'])
            && Request::verifyGPDataString('fromAdmin') === 'yes'
        ) {
            // frontend session has not been notified yet
            // try to fetch information autonomously
            $frontendId = \session_id();
            \session_write_close();
            \session_name('eSIdAdm');
            \session_id($_COOKIE['eSIdAdm']);
            \session_start();
            self::$logged = $_SESSION['loginIsValid'] ?? null;

            if (isset($_SESSION['jtl_token'], $_SESSION['AdminAccount'])) {
                $adminToken                   = $_SESSION['jtl_token'];
                $adminLangTag                 = $_SESSION['AdminAccount']->language;
                $_SESSION['frontendUpToDate'] = true;

                if (self::$logged) {
                    self::Container()->getGetText();
                }
            } else {
                $adminToken   = null;
                $adminLangTag = null;
            }

            \session_write_close();
            \session_name('JTLSHOP');
            \session_id($frontendId ?: null);
            \session_start();
            self::$adminToken          = $adminToken;
            self::$adminLangTag        = $adminLangTag;
            $_SESSION['adminToken']    = $adminToken;
            $_SESSION['adminLangTag']  = $adminLangTag;
            $_SESSION['loggedAsAdmin'] = self::$logged;
        } else {
            // no information about admin session available
            self::$logged       = null;
            self::$adminToken   = null;
            self::$adminLangTag = null;
        }

        return self::$logged ?? false;
    }

    /**
     * @throws Exception
     */
    public static function getAdminSessionToken(): ?string
    {
        if (self::isAdmin()) {
            return self::$adminToken;
        }

        return null;
    }

    /**
     * @throws Exception
     */
    public static function getCurAdminLangTag(): ?string
    {
        if (self::isAdmin()) {
            return self::$adminLangTag;
        }

        return null;
    }

    public static function isBrandfree(): bool
    {
        return Nice::getInstance()->checkErweiterung(\SHOP_ERWEITERUNG_BRANDFREE);
    }

    public static function Container(): DefaultServicesInterface
    {
        if (!self::$container) {
            $factory         = new Factory();
            self::$container = $factory->createContainers();
        }

        return self::$container;
    }
}
