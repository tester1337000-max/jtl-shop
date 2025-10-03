<?php

declare(strict_types=1);

namespace JTL\Session;

use JTL\Catalog\Currency;
use JTL\Customer\CustomerGroup;
use JTL\Helpers\Text;
use JTL\Language\LanguageHelper;
use JTL\Language\LanguageModel;
use JTL\Shop;
use stdClass;

use function Functional\first;
use function Functional\map;

/**
 * Class Backend
 * @package JTL\Session
 */
class Backend extends AbstractSession
{
    private const DEFAULT_SESSION = 'eSIdAdm';

    private const SESSION_HASH_KEY = 'session.hash';

    protected static ?Backend $instance = null;

    /**
     * @throws \Exception
     */
    public static function getInstance(): self
    {
        return self::$instance ?? new self();
    }

    /**
     * @throws \Exception
     * @param bool $init - quick fix to allow usage within unit tests
     */
    public function __construct(bool $init = true)
    {
        parent::__construct(true, self::DEFAULT_SESSION);
        self::$instance = $this;
        if ($init === false) {
            return;
        }
        $_SESSION['jtl_token'] = $_SESSION['jtl_token'] ?? Shop::Container()->getCryptoService()->randomString(32);
        $this->setLanguage();
        if (isset($_SESSION['Kundengruppe']) && \get_class($_SESSION['Kundengruppe']) === stdClass::class) {
            $_SESSION['Kundengruppe'] = new CustomerGroup((int)$_SESSION['Kundengruppe']->kKundengruppe);
        }
        if (isset($_SESSION['Waehrung']) && \get_class($_SESSION['Waehrung']) === stdClass::class) {
            $_SESSION['Waehrung'] = new Currency((int)$_SESSION['Waehrung']->kWaehrung);
        }
        if (empty($_SESSION['Sprachen']) || \get_class(\array_values($_SESSION['Sprachen'])[0]) === stdClass::class) {
            $_SESSION['Sprachen'] = LanguageHelper::getInstance()->gibInstallierteSprachen();
        }
        $this->initLanguageURLs();
    }

    private function setLanguage(): void
    {
        if (isset($_SESSION['kSprache'], $_SESSION['cISOSprache'])) {
            Shop::setLanguage($_SESSION['kSprache'], $_SESSION['cISOSprache']);
            return;
        }
        if (($this->setLanguageByAdminAccount() === false) && $this->setLanguageFromDefault() === false) {
            // default shop language is not a backend language
            /** @var LanguageModel|null $lang */
            $lang = first(
                LanguageHelper::getInstance()->gibInstallierteSprachen(),
                static fn(LanguageModel $e): bool => $e->isShopDefault() === true
            );
            if ($lang === null) {
                /** @var LanguageModel $lang */
                $lang = first(
                    LanguageHelper::getAllLanguages(),
                    static fn(LanguageModel $e): bool => $e->getIso() === 'ger'
                );
            }
            $_SESSION['kSprache']    = $lang->getId();
            $_SESSION['cISOSprache'] = $lang->getCode();
        }
        $_SESSION['kSprache']         = (int)($_SESSION['kSprache'] ?? 1);
        $_SESSION['cISOSprache']      = $_SESSION['cISOSprache'] ?? 'ger';
        $_SESSION['editLanguageID']   = $_SESSION['editLanguageID'] ?? $_SESSION['kSprache'];
        $_SESSION['editLanguageCode'] = $_SESSION['editLanguageCode'] ?? $_SESSION['cISOSprache'];
        Shop::setLanguage($_SESSION['kSprache'], $_SESSION['cISOSprache']);
    }

    private function setLanguageFromDefault(): bool
    {
        $languages = LanguageHelper::getInstance()->gibInstallierteSprachen();
        /** @var LanguageModel|null $lang */
        $lang    = first($languages, fn(LanguageModel $e): bool => $e->isShopDefault() === true);
        $allowed = map($languages, fn(LanguageModel $e): string => $e->getIso639());
        if ($lang === null) {
            return false;
        }
        $default = Text::convertISO6392ISO($this->getBrowserLanguage($allowed, $lang->getCode()));
        foreach ($languages as $language) {
            if ($language->getCode() !== $default) {
                continue;
            }
            foreach (Shop::Container()->getGetText()->getAdminLanguages() as $tag => $adminLocale) {
                if ($adminLocale === $language->getNameDE() || $adminLocale === $language->getNameEN()) {
                    $_SESSION['kSprache']    = $language->getId();
                    $_SESSION['cISOSprache'] = $language->getCode();
                    Shop::Container()->getGetText()->setLanguage($tag);
                    return true;
                }
            }
        }

        return false;
    }

    private function setLanguageByAdminAccount(): bool
    {
        $adminDefault = $_SESSION['AdminAccount']->language ?? null;
        if ($adminDefault === null) {
            return false;
        }
        $languages = LanguageHelper::getInstance()->gibInstallierteSprachen();
        foreach (Shop::Container()->getGetText()->getAdminLanguages() as $tag => $adminLocale) {
            if ($tag !== $adminDefault) {
                continue;
            }
            foreach ($languages as $language) {
                if ($adminLocale === $language->getNameDE() || $adminLocale === $language->getNameEN()) {
                    $_SESSION['kSprache']    = $language->getId();
                    $_SESSION['cISOSprache'] = $language->getCode();
                    Shop::Container()->getGetText()->setLanguage($tag);

                    return true;
                }
            }
        }

        return false;
    }

    private static function createHash(string $value = \APPLICATION_VERSION): string
    {
        return \hash('sha1', $value);
    }

    public function reHash(string $value = \APPLICATION_VERSION): self
    {
        self::set(self::SESSION_HASH_KEY, self::createHash($value));

        return $this;
    }

    public function isValid(): bool
    {
        return self::get(self::SESSION_HASH_KEY, '') === self::createHash();
    }
}
