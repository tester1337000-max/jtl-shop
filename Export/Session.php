<?php

declare(strict_types=1);

namespace JTL\Export;

use JTL\Catalog\Currency;
use JTL\Customer\CustomerGroup;
use JTL\DB\DbInterface;
use JTL\Helpers\Tax;
use JTL\Language\LanguageModel;
use JTL\Session\Frontend;
use JTL\Shop;
use stdClass;

use function Functional\first;

/**
 * Class Session
 * @package JTL\Export
 */
class Session
{
    private ?stdClass $oldSession = null;

    private Currency $currency;

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function setCurrency(Currency $currency): Session
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function initSession(Model $model, DbInterface $db, array $config = []): self
    {
        if (isset($_SESSION['Kundengruppe'])) {
            $this->oldSession               = new stdClass();
            $this->oldSession->Kundengruppe = $_SESSION['Kundengruppe'];
            $this->oldSession->kSprache     = $_SESSION['kSprache'];
            $this->oldSession->cISO         = $_SESSION['cISOSprache'];
            $this->oldSession->Waehrung     = Frontend::getCurrency();
        }
        $languageID     = $model->getLanguageID();
        $this->currency = $model->getCurrencyID() > 0
            ? new Currency($model->getCurrencyID())
            : (new Currency())->getDefault();
        Tax::setTaxRates($config['exportformate_lieferland'] ?? null);
        $languages = Shop::Lang()->gibInstallierteSprachen();
        /** @var LanguageModel|null $langISO */
        $langISO = first($languages, fn(LanguageModel $l): bool => $l->getId() === $languageID);

        $_SESSION['Kundengruppe']  = (new CustomerGroup($model->getCustomerGroupID(), $db))
            ->setMayViewPrices(1)
            ->setMayViewCategories(1);
        $_SESSION['kKundengruppe'] = $model->getCustomerGroupID();
        $_SESSION['kSprache']      = $languageID;
        $_SESSION['Sprachen']      = $languages;
        $_SESSION['Waehrung']      = $this->currency;
        Shop::setLanguage($languageID, $langISO->cISO ?? null);

        return $this;
    }

    public function restoreSession(): self
    {
        if ($this->oldSession !== null) {
            $_SESSION['Kundengruppe'] = $this->oldSession->Kundengruppe;
            $_SESSION['Waehrung']     = $this->oldSession->Waehrung;
            $_SESSION['kSprache']     = $this->oldSession->kSprache;
            $_SESSION['cISOSprache']  = $this->oldSession->cISO;
            Shop::setLanguage($this->oldSession->kSprache, $this->oldSession->cISO);
        }

        return $this;
    }
}
