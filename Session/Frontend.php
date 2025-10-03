<?php

declare(strict_types=1);

namespace JTL\Session;

use DateTime;
use Exception;
use InvalidArgumentException;
use JTL\Cart\Cart;
use JTL\Cart\PersistentCart;
use JTL\Catalog\ComparisonList;
use JTL\Catalog\Currency;
use JTL\Catalog\Wishlist\Wishlist;
use JTL\Checkout\Lieferadresse;
use JTL\Checkout\Zahlungsart;
use JTL\Customer\Customer;
use JTL\Customer\CustomerGroup;
use JTL\Firma;
use JTL\Helpers\GeneralObject;
use JTL\Helpers\Manufacturer;
use JTL\Helpers\Request;
use JTL\Helpers\Tax;
use JTL\Helpers\Text;
use JTL\Language\LanguageHelper;
use JTL\Language\LanguageModel;
use JTL\Link\LinkGroupCollection;
use JTL\Plugin\Helper;
use JTL\Plugin\PluginLoader;
use JTL\Shop;
use stdClass;

use function Functional\first;

/**
 * Class Frontend
 * @package JTL\Session
 */
class Frontend extends AbstractSession
{
    private const DEFAULT_SESSION = 'JTLSHOP';

    protected static ?Frontend $instance = null;

    private bool $mustUpdate = false;

    /**
     * @throws Exception
     */
    public static function getInstance(
        bool $start = true,
        bool $force = false,
        string $sessionName = self::DEFAULT_SESSION
    ): self {
        return ($force === true || self::$instance === null || self::$sessionName !== $sessionName)
            ? new self($start, $sessionName)
            : self::$instance;
    }

    /**
     * @throws Exception
     */
    public function __construct(bool $start = true, string $sessionName = self::DEFAULT_SESSION)
    {
        parent::__construct($start, $sessionName);
        self::$instance = $this;
        $this->setStandardSessionVars();
        Shop::setLanguage($_SESSION['kSprache'], $_SESSION['cISOSprache']);
    }

    /**
     * this method is split from updateGlobals() to allow a later execution after the plugin bootstrapper
     * was initialized. otherwise the hooks executed by these method calls could not be handled with the
     * event dispatcher
     */
    public function deferredUpdate(): void
    {
        \executeHook(\HOOK_CORE_SESSION_CONSTRUCTOR);
        if ($this->mustUpdate !== true && (bool)($_SESSION['session.fully_initialized'] ?? false) === true) {
            return;
        }
        self::getCart()->loescheDeaktiviertePositionen();
        Tax::setTaxRates();
        $_SESSION['session.fully_initialized'] = true;
    }

    /**
     * setzt Sessionvariablen beim ersten Sessionaufbau oder wenn globale Daten aktualisiert werden müssen
     * @throws Exception
     */
    public function setStandardSessionVars(): self
    {
        LanguageHelper::getInstance()->autoload();
        $_SESSION['FremdParameter'] = [];
        $_SESSION['Warenkorb']      = $_SESSION['Warenkorb'] ?? new Cart();
        $_SESSION['consentVersion'] = (int)($_SESSION['consentVersion'] ?? 1);
        $_SESSION['loginDate']      = $_SESSION['loginDate'] ?? (new DateTime())->getTimestamp();

        $updateGlobals  = $this->checkGlobals();
        $updateLanguage = $this->checkLanguageUpdate();
        $updateGlobals  = $updateLanguage || $updateGlobals || $this->checkSessionUpdate();
        $lang           = $_GET['lang'] ?? '';
        $checked        = false;
        if (isset($_SESSION['kSprache'])) {
            self::checkReset($lang);
            $checked = true;
        }
        if ($updateGlobals) {
            $this->updateGlobals();
            if ($updateLanguage && isset($_SESSION['Kunde'])) {
                // Kundensprache ändern, wenn im eingeloggten Zustand die Sprache geändert wird
                $_SESSION['Kunde']->kSprache = $_SESSION['kSprache'];
                $_SESSION['Kunde']->updateInDB();
            }
        }
        if (!$checked) {
            self::checkReset($lang);
        }
        $this->checkWishlistDeletes()->checkComparelistDeletes();
        if (!isset($_SESSION['cISOSprache'])) {
            \session_destroy();
            die(
                '<h1>Ihr Shop wurde installiert. Lesen Sie in unserem Guide '
                . '<a href="https://jtl-url.de/3dw4f">'
                . 'mehr zu ersten Schritten mit JTL-Shop, der Grundkonfiguration '
                . 'und dem erstem Abgleich mit JTL-Wawi</a>.</h1>'
            );
        }
        $this->checkCustomerUpdate();
        $this->initLanguageURLs();
        Shop::Container()->getAlertService()->initFromSession();

        return $this;
    }

    private function checkLanguageUpdate(): bool
    {
        return isset($_GET['lang']) && (!isset($_SESSION['cISOSprache']) || $_GET['lang'] !== $_SESSION['cISOSprache']);
    }

    public function logout(): void
    {
        $languageID   = Shop::getLanguageID();
        $languageCode = Shop::getLanguageCode();
        $currency     = self::getCurrency();
        unset($_SESSION['Warenkorb']);

        $params = \session_get_cookie_params();
        \setcookie(
            \session_name() ?: self::DEFAULT_SESSION,
            '',
            \time() - 7000000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
        \session_destroy();
        $session = new self();
        \session_regenerate_id(true);

        $_SESSION['kSprache']    = $languageID;
        $_SESSION['cISOSprache'] = $languageCode;
        $_SESSION['Waehrung']    = $currency;
        Shop::setLanguage($languageID, $languageCode);
        $session->deferredUpdate();
    }

    /**
     * @throws Exception
     */
    public function checkCustomerUpdate(?string $location = null): bool
    {
        if (empty($_SESSION['Kunde']->kKunde)) {
            return false;
        }
        $data = Shop::Container()->getDB()->getSingleObject(
            'SELECT kKunde, dSessionInvalidate, DATE_SUB(NOW(), INTERVAL 3 HOUR) < dVeraendert AS modified
                FROM tkunde
                WHERE kKunde = :cid',
            ['cid' => (int)$_SESSION['Kunde']->kKunde]
        );
        if ($data === null) {
            return true;
        }
        if ($data->dSessionInvalidate !== null) {
            $loginTime = new DateTime();
            $loginTime->setTimestamp($_SESSION['loginDate']);
            if ($loginTime < new DateTime($data->dSessionInvalidate)) {
                $location = $location
                    ?? Shop::Container()->getLinkService()->getStaticRoute('jtl.php') . '?loggedout=1&reason=upw';
                $this->logout();
                \header('Location: ' . $location, true, 303);
                exit;
            }
        }
        if (isset($_SESSION['kundendaten_aktualisiert'])) {
            return false;
        }
        if ($data->modified === '1') {
            Shop::setLanguage(
                $_SESSION['kSprache'] ?? $_SESSION['Kunde']->kSprache ?? 0,
                $_SESSION['cISOSprache'] ?? null
            );
            $this->setCustomer(new Customer((int)$_SESSION['Kunde']->kKunde));
            $_SESSION['kundendaten_aktualisiert'] = 1;
        }

        return true;
    }

    private function checkSessionUpdate(): bool
    {
        return ((bool)($_SESSION['session.fully_initialized'] ?? false) !== true
            || (isset($_SESSION['Kundengruppe']) && \get_class($_SESSION['Kundengruppe']) === stdClass::class)
            || (isset($_SESSION['Waehrung']) && \get_class($_SESSION['Waehrung']) === stdClass::class)
            || (isset($_SESSION['Sprachen']) && \get_class(\array_values($_SESSION['Sprachen'])[0]) === stdClass::class)
        );
    }

    /**
     * @throws Exception
     */
    private function checkGlobals(): bool
    {
        $doUpdate = true;
        if (isset($_SESSION['Globals_TS'])) {
            $doUpdate = false;
            $last     = Shop::Container()->getDB()->getSingleObject(
                'SELECT * 
                    FROM tglobals 
                    WHERE dLetzteAenderung > :ts',
                ['ts' => $_SESSION['Globals_TS']]
            );
            if ($last !== null) {
                $_SESSION['Globals_TS']     = $last->dLetzteAenderung;
                $_SESSION['consentVersion'] = (int)$last->consentVersion;
                $doUpdate                   = true;
            }
        } else {
            $data = Shop::Container()->getDB()->getSingleObject('SELECT * FROM tglobals');
            if ($data === null) {
                throw new Exception('Fatal: could not load tglobals');
            }
            $_SESSION['Globals_TS']     = $data->dLetzteAenderung;
            $_SESSION['consentVersion'] = (int)$data->consentVersion;
        }

        return $doUpdate || !isset($_SESSION['cISOSprache'], $_SESSION['kSprache'], $_SESSION['Kundengruppe']);
    }

    /**
     * @throws Exception
     */
    private function updateGlobals(): void
    {
        unset($_SESSION['oKategorie_arr_new']);
        $_SESSION['ks']       = [];
        $_SESSION['Sprachen'] = LanguageHelper::getInstance()->gibInstallierteSprachen();
        Currency::setCurrencies(true);

        if (!isset($_SESSION['jtl_token'])) {
            $_SESSION['jtl_token'] = Shop::Container()->getCryptoService()->randomString(32);
        }
        $defaultLang = '';
        foreach ($_SESSION['Sprachen'] as $language) {
            $iso = Text::convertISO2ISO639($language->getCode());
            $language->setIso639($iso);
            if ($language->isShopDefault()) {
                $defaultLang = $iso;
            }
        }
        if (!isset($_SESSION['kSprache'])) {
            $default = Text::convertISO6392ISO($defaultLang);
            foreach ($_SESSION['Sprachen'] as $lang) {
                if ($lang->getCode() === $default || (empty($default) && $lang->isShopDefault())) {
                    $_SESSION['kSprache']    = $lang->getId();
                    $_SESSION['cISOSprache'] = \trim($lang->getCode());
                    Shop::setLanguage($_SESSION['kSprache'], $_SESSION['cISOSprache']);
                    $_SESSION['currentLanguage'] = clone $lang;
                    break;
                }
            }
        } elseif (isset($_SESSION['currentLanguage']) && \get_class($_SESSION['currentLanguage']) === stdClass::class) {
            foreach ($_SESSION['Sprachen'] as $lang) {
                if ($_SESSION['kSprache'] === $lang->kSprache) {
                    $_SESSION['currentLanguage'] = clone $lang;
                }
            }
        }
        if (isset($_SESSION['Waehrung'])) {
            if (\get_class($_SESSION['Waehrung']) === stdClass::class) {
                $_SESSION['Waehrung'] = new Currency($_SESSION['Waehrung']->kWaehrung);
            }
            /** @var Currency $currency */
            foreach ($_SESSION['Waehrungen'] as $currency) {
                if ($currency->getCode() === $_SESSION['Waehrung']->getCode()) {
                    $_SESSION['Waehrung']      = $currency;
                    $_SESSION['cWaehrungName'] = $currency->getName();
                }
            }
        } else {
            /** @var Currency $currency */
            foreach ($_SESSION['Waehrungen'] as $currency) {
                if ($currency->isDefault()) {
                    $_SESSION['Waehrung']      = $currency;
                    $_SESSION['cWaehrungName'] = $currency->getName();
                }
            }
        }
        // EXPERIMENTAL_MULTILANG_SHOP
        if (Shop::$forceHost[0]['host'] === ($_SERVER['HTTP_HOST'] ?? '-')) {
            foreach ($_SESSION['Sprachen'] as $lang) {
                if (Shop::$forceHost[0]['id'] === $lang->getId()) {
                    $_SESSION['kSprache']    = $lang->getId();
                    $_SESSION['cISOSprache'] = \trim($lang->getCode());
                    Shop::setLanguage($_SESSION['kSprache'], $_SESSION['cISOSprache']);
                    break;
                }
            }
        } else {
            foreach ($_SESSION['Sprachen'] as $lang) {
                if (!isset($_SERVER['HTTP_HOST'])) {
                    break;
                }
                if (\defined('URL_SHOP_' . \mb_convert_case($lang->cISO, \MB_CASE_UPPER))) {
                    /** @var string $shopLangURL */
                    $shopLangURL = \constant('URL_SHOP_' . \mb_convert_case($lang->cISO, \MB_CASE_UPPER));
                    if (\str_contains($shopLangURL, $_SERVER['HTTP_HOST'])) {
                        $_SESSION['kSprache']    = $lang->kSprache;
                        $_SESSION['cISOSprache'] = \trim($lang->cISO);
                        Shop::setLanguage($_SESSION['kSprache'], $_SESSION['cISOSprache']);
                        break;
                    }
                }
            }
        }
        // EXPERIMENTAL_MULTILANG_SHOP END
        if (
            !isset($_SESSION['Kunde']->kKunde, $_SESSION['Kundengruppe']->kKundengruppe)
            || \get_class($_SESSION['Kundengruppe']) === stdClass::class
        ) {
            $_SESSION['Kundengruppe'] = (new CustomerGroup())
                ->setLanguageID((int)$_SESSION['kSprache'])
                ->loadDefaultGroup();
        }
        if (\PHP_SAPI !== 'cli' && Shop::Container()->getCache()->isCacheGroupActive(\CACHING_GROUP_CORE) === false) {
            $_SESSION['Linkgruppen'] = Shop::Container()->getLinkService()->getLinkGroups();
            $_SESSION['Hersteller']  = Manufacturer::getInstance()->getManufacturers();
        }
        if (\defined('STEUERSATZ_STANDARD_LAND')) {
            $merchantCountryCode = \STEUERSATZ_STANDARD_LAND;
        } else {
            $company = new Firma(true, Shop::Container()->getDB());
            if (!empty($company->cLand)) {
                $merchantCountryCode = LanguageHelper::getIsoCodeByCountryName($company->cLand);
            }
        }
        $_SESSION['Steuerland']     = $merchantCountryCode ?? 'DE';
        $_SESSION['cLieferlandISO'] = $_SESSION['Steuerland'];
        Shop::setLanguage($_SESSION['kSprache'], $_SESSION['cISOSprache']);
        $this->mustUpdate = true;
        Shop::Lang()->reset();
    }

    private function checkWishlistDeletes(): self
    {
        $index = Request::verifyGPCDataInt('wlplo');
        if ($index !== 0) {
            $wl = self::getWishList();
            $wl->entfernePos($index);
        }

        return $this;
    }

    private function checkComparelistDeletes(): self
    {
        if (Request::verifyGPDataString('delete') === 'all') {
            unset($_SESSION['Vergleichsliste']);
            \http_response_code(301);
            \header('Location: ' . Shop::Container()->getLinkService()->getStaticRoute('vergleichsliste.php'));
            exit;
        }

        $listID = Request::verifyGPCDataInt(\QUERY_PARAM_COMPARELIST_PRODUCT);
        if ($listID !== 0 && GeneralObject::isCountable('oArtikel_arr', $_SESSION['Vergleichsliste'])) {
            // Wunschliste Position aus der Session löschen
            foreach ($_SESSION['Vergleichsliste']->oArtikel_arr as $i => $product) {
                if ((int)$product->kArtikel === $listID) {
                    unset($_SESSION['Vergleichsliste']->oArtikel_arr[$i]);
                }
            }
            // Ist nach dem Löschen des Artikels aus der Vergleichslite kein weiterer Artikel vorhanden?
            if (\count($_SESSION['Vergleichsliste']->oArtikel_arr) === 0) {
                unset($_SESSION['Vergleichsliste']);
            } else {
                // Positionen Array in der Wunschliste neu nummerieren
                $_SESSION['Vergleichsliste']->oArtikel_arr = \array_merge($_SESSION['Vergleichsliste']->oArtikel_arr);
            }
            if (!isset($_SERVER['REQUEST_URI']) || \str_contains($_SERVER['REQUEST_URI'], 'index.php')) {
                \http_response_code(301);
                \header('Location: ' . Shop::getURL() . '/');
                exit;
            }
        }

        return $this;
    }

    public function cleanUp(): self
    {
        if (isset($_SESSION['Kunde']->nRegistriert) && (int)$_SESSION['Kunde']->nRegistriert === 0) {
            unset($_SESSION['Kunde']);
        }

        unset(
            $_SESSION['Zahlungsart'],
            $_SESSION['Warenkorb'],
            $_SESSION['Versandart'],
            $_SESSION['Lieferadresse'],
            $_SESSION['VersandKupon'],
            $_SESSION['NeukundenKupon'],
            $_SESSION['Kupon'],
            $_SESSION['GuthabenLocalized'],
            $_SESSION['Bestellung'],
            $_SESSION['IP'],
            $_SESSION['kommentar']
        );
        $_SESSION['Warenkorb'] = new Cart();
        // WarenkorbPers loeschen
        $oWarenkorbPers = new PersistentCart($_SESSION['Kunde']->kKunde ?? 0);
        $oWarenkorbPers->entferneAlles();

        return $this;
    }

    public static function getDeliveryAddress(): Lieferadresse
    {
        return $_SESSION['Lieferadresse'] ?? new Lieferadresse();
    }

    public static function setDeliveryAddress(Lieferadresse $address): void
    {
        $_SESSION['Lieferadresse'] = $address;
    }

    public function setCustomer(Customer $customer): self
    {
        $customer->angezeigtesLand = LanguageHelper::getCountryCodeByCountryName($customer->cLand ?? '');
        $_SESSION['Kunde']         = $customer;
        try {
            $_SESSION['Kundengruppe'] = new CustomerGroup((int)$customer->kKundengruppe);
        } catch (InvalidArgumentException) {
            $_SESSION['Kundengruppe'] = new CustomerGroup(CustomerGroup::getDefaultGroupID());
        }
        $_SESSION['Kundengruppe']->setMayViewCategories(1)
            ->setMayViewPrices(1);
        self::getCart()->setzePositionsPreise();
        Tax::setTaxRates();
        self::setSpecialLinks();

        return $this;
    }

    public static function getCustomer(): Customer
    {
        return $_SESSION['Kunde'] ?? new Customer();
    }

    public static function getCustomerGroup(): CustomerGroup
    {
        return $_SESSION['Kundengruppe'] ?? (new CustomerGroup())->loadDefaultGroup();
    }

    public static function getVisitor(): ?stdClass
    {
        return $_SESSION['oBesucher'] ?? null;
    }

    public function getLanguage(): LanguageHelper
    {
        $lang                    = LanguageHelper::getInstance();
        $lang->kSprache          = (int)$_SESSION['kSprache'];
        $lang->currentLanguageID = (int)$_SESSION['kSprache'];
        $lang->kSprachISO        = $lang->mappekISO($_SESSION['cISOSprache']);
        $lang->cISOSprache       = $_SESSION['cISOSprache'];

        return $lang;
    }

    /**
     * @return LanguageModel[]
     */
    public static function getLanguages(): array
    {
        return $_SESSION['Sprachen'] ?? [];
    }

    /**
     * @return Zahlungsart[]
     * @todo: this is never set and never used?
     * @deprecated since 5.4.0
     */
    public function getPaymentMethods(): array
    {
        return $_SESSION['Zahlungsarten'] ?? [];
    }

    public static function getCurrency(): Currency
    {
        $currency = $_SESSION['Waehrung'] ?? null;
        if ($currency !== null && \get_class($currency) === Currency::class) {
            return $currency;
        }
        if ($currency !== null && \get_class($currency) === stdClass::class) {
            $_SESSION['Waehrung'] = new Currency((int)$_SESSION['Waehrung']->kWaehrung);
        }

        return $_SESSION['Waehrung'] ?? (new Currency())->getDefault();
    }

    public static function getCart(): Cart
    {
        return $_SESSION['Warenkorb'] ?? new Cart();
    }

    /**
     * @return Currency[]
     */
    public static function getCurrencies(): array
    {
        return $_SESSION['Waehrungen'] ?? [];
    }

    public static function getWishList(): Wishlist
    {
        return $_SESSION['Wunschliste'] ?? new Wishlist();
    }

    public static function getCompareList(): ComparisonList
    {
        return $_SESSION['Vergleichsliste'] ?? new ComparisonList();
    }

    /**
     * @former checkeSpracheWaehrung()
     * @since 5.0.0
     */
    public static function checkReset(string $langISO = ''): void
    {
        if ($langISO !== '') {
            /** @var LanguageModel|null $lang */
            $lang = first(LanguageHelper::getAllLanguages(), fn(LanguageModel $l): bool => $l->getCode() === $langISO);
            if ($lang === null) {
                self::urlFallback();
            }
            $langCode                = $lang->getIso();
            $langID                  = $lang->getId();
            $_SESSION['cISOSprache'] = $langCode;
            $_SESSION['kSprache']    = $langID;
            $oldCode                 = Shop::getLanguageCode();
            Shop::setLanguage($langID, $langCode);
            if ($oldCode !== $langCode) {
                $loader = new PluginLoader(Shop::Container()->getDB(), Shop::Container()->getCache());
                foreach (Helper::getBootstrappedPlugins() as $bsp) {
                    Helper::updatePluginInstance($loader->init($bsp->getPlugin()->getID(), false, $langID));
                }
            }
            unset($_SESSION['Suche']);
            self::setSpecialLinks();
            if (isset($_SESSION['Wunschliste'])) {
                self::getWishList()->umgebungsWechsel();
            }
            if (isset($_SESSION['Vergleichsliste'])) {
                self::getCompareList()->umgebungsWechsel();
            }
            $_SESSION['currentLanguage'] = clone $lang;
        }
        $currencyCode = Request::verifyGPDataString('curr');
        if ($currencyCode) {
            self::updateCurrency($currencyCode);
        }
        LanguageHelper::getInstance()->autoload();
    }

    public static function updateCurrency(string $currencyCode): void
    {
        $currentCurrency = $_SESSION['Waehrung'] ?? null;
        if ($currentCurrency instanceof Currency && $currentCurrency->getCode() === $currencyCode) {
            return;
        }
        $currencies = self::getCurrencies();
        if (\count($currencies) === 0) {
            $currencies = Currency::loadAll();
        }
        /** @var Currency|null $currency */
        $currency = first($currencies, fn(Currency $c): bool => $c->getCode() === $currencyCode);
        if ($currency === null) {
            return;
        }
        $_SESSION['Waehrung']      = $currency;
        $_SESSION['cWaehrungName'] = $currency->getName();
        if (isset($_SESSION['Wunschliste'])) {
            self::getWishList()->umgebungsWechsel();
        }
        if (isset($_SESSION['Vergleichsliste'])) {
            self::getCompareList()->umgebungsWechsel();
        }
        $cart = self::getCart();
        if (\count($cart->PositionenArr) > 0) {
            $cart->setzePositionsPreise();
        }
    }

    /**
     * @since 5.0.0
     */
    private static function urlFallback(): never
    {
        $key = 'kArtikel';
        $val = 0;
        \http_response_code(301);
        if (($id = Request::verifyGPCDataInt(\QUERY_PARAM_PRODUCT)) > 0) {
            $val = $id;
        } elseif (($id = Request::verifyGPCDataInt(\QUERY_PARAM_CHILD_PRODUCT)) > 0) {
            $val = $id;
        } elseif (($id = Request::verifyGPCDataInt(\QUERY_PARAM_CATEGORY)) > 0) {
            $key = 'kKategorie';
            $val = $id;
        } elseif (($id = Request::verifyGPCDataInt(\QUERY_PARAM_LINK)) > 0) {
            $key = 'kLink';
            $val = $id;
        } elseif (($id = Request::verifyGPCDataInt(\QUERY_PARAM_MANUFACTURER)) > 0) {
            $key = 'kHersteller';
            $val = $id;
        } elseif (($id = Request::verifyGPCDataInt(\QUERY_PARAM_SEARCH_QUERY_ID)) > 0) {
            $key = 'kSuchanfrage';
            $val = $id;
        } elseif (($id = Request::verifyGPCDataInt(\QUERY_PARAM_CHARACTERISTIC_VALUE)) > 0) {
            $key = 'kMerkmalWert';
            $val = $id;
        } elseif (($id = Request::verifyGPCDataInt(\QUERY_PARAM_SEARCH_SPECIAL)) > 0) {
            $key = 'kSuchspecial';
            $val = $id;
        } elseif (($id = Request::verifyGPCDataInt(\QUERY_PARAM_NEWS_ITEM)) > 0) {
            $key = 'kNews';
            $val = $id;
        } elseif (($id = Request::verifyGPCDataInt(\QUERY_PARAM_NEWS_OVERVIEW)) > 0) {
            $key = 'kNewsMonatsUebersicht';
            $val = $id;
        } elseif (($id = Request::verifyGPCDataInt(\QUERY_PARAM_NEWS_CATEGORY)) > 0) {
            $key = 'kNewsKategorie';
            $val = $id;
        }
        $dbRes = Shop::Container()->getDB()->select(
            'tseo',
            'cKey',
            $key,
            'kKey',
            $val,
            'kSprache',
            Shop::getLanguageID()
        );
        $seo   = $dbRes->cSeo ?? '';
        \header('Location: ' . Shop::getURL() . '/' . $seo, true, 301);
        exit;
    }

    /**
     * @former setzeLinks()
     * @since 5.0.0
     */
    public static function setSpecialLinks(): LinkGroupCollection
    {
        $linkGroups                    = Shop::Container()->getLinkService()->getLinkGroups();
        $_SESSION['Link_Datenschutz']  = $linkGroups->Link_Datenschutz;
        $_SESSION['Link_AGB']          = $linkGroups->Link_AGB;
        $_SESSION['Link_Versandseite'] = $linkGroups->Link_Versandseite;

        return $linkGroups;
    }
}
