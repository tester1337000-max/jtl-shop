<?php

declare(strict_types=1);

namespace JTL\Catalog\Product;

use JTL\Catalog\Currency;
use JTL\DB\DbInterface;
use JTL\Helpers\Tax;
use JTL\Session\Frontend;
use JTL\Settings\Option\Globals;
use JTL\Settings\Settings;
use JTL\Shop;
use stdClass;

/**
 * Class Preise
 * @package JTL\Catalog\Product
 */
class Preise
{
    public int $kKundengruppe;

    public int $kArtikel;

    public int $kKunde;

    /**
     * @var array<int, string>
     */
    public array $cVKLocalized = [];

    public float $fVKNetto = 0.0;

    public float $fVKBrutto = 0.0;

    public ?float $fPreis1 = null;

    public ?float $fPreis2 = null;

    public ?float $fPreis3 = null;

    public ?float $fPreis4 = null;

    public ?float $fPreis5 = null;

    /**
     * @var numeric-string|float|null
     */
    public string|float|null $fUst = null;

    public ?float $alterVKNetto = null;

    public ?int $nAnzahl1 = null;

    public ?int $nAnzahl2 = null;

    public ?int $nAnzahl3 = null;

    public ?int $nAnzahl4 = null;

    public ?int $nAnzahl5 = null;

    /**
     * @var array<int, float>
     */
    public array $alterVK = [];

    public ?float $rabatt = null;

    /**
     * @var array<int, string>
     */
    public array $alterVKLocalized = [];

    /**
     * @var array<int, float>
     */
    public array $fVK = [];

    /**
     * @var int[]
     */
    public array $nAnzahl_arr = [];

    /**
     * @var float[]
     */
    public array $fPreis_arr = [];

    /**
     * @var array<int, float[]>
     */
    public array $fStaffelpreis_arr = [];

    /**
     * @var array<string[]>
     */
    public array $cPreisLocalized_arr = [];

    public bool|int $Sonderpreis_aktiv = false;

    public bool $Kundenpreis_aktiv = false;

    public ?PriceRange $oPriceRange = null;

    public ?string $SonderpreisBis_en = null;

    public ?string $SonderpreisBis_de = null;

    public int $discountPercentage = 0;

    public bool $noDiscount = false;

    /**
     * @var array<int, string>
     */
    public array $cAufpreisLocalized = [];

    /**
     * @var array<int, string>
     */
    public array $cPreisVPEWertInklAufpreis = [];

    /**
     * @var array<int, string>
     */
    public array $PreisecPreisVPEWertInklAufpreis = [];

    private ?DbInterface $db = null;

    private bool $consistentGrossPrices;

    public function __construct(
        int $customerGroupID,
        int $productID,
        int $customerID = 0,
        int $taxClassID = 0,
        ?DbInterface $db = null
    ) {
        $this->db                    = $db ?? Shop::Container()->getDB();
        $this->kArtikel              = $productID;
        $this->kKundengruppe         = $customerGroupID;
        $this->kKunde                = $customerID;
        $this->consistentGrossPrices = Settings::boolValue(Globals::CONSISTENT_GROSS_PRICES);

        $price   = $this->getPrice($customerGroupID, $productID, $customerID);
        $taxData = $this->getTaxData($productID);
        if ($taxClassID === 0 && $taxData !== null) {
            $taxClassID = (int)$taxData->kSteuerklasse;
        }
        $this->fUst = Tax::getSalesTax($taxClassID);
        if ($price !== null && $taxData !== null) {
            if ((int)$price->kKunde > 0) {
                $this->Kundenpreis_aktiv = true;
            }
            if ((int)$price->noDiscount > 0) {
                $this->noDiscount = true;
            }
            $defaultTax = (float)$taxData->fMwSt;
            $this->setScalePrice(
                (int)$price->kPreis,
                $defaultTax,
                $customerGroupID,
                $productID
            );
        }

        $this->berechneVKs();
        $this->oPriceRange = new PriceRange($productID, $customerGroupID, $customerID, $this->getDB());
        \executeHook(\HOOK_PRICES_CONSTRUCT, [
            'customerGroupID' => $customerGroupID,
            'customerID'      => $customerID,
            'productID'       => $productID,
            'taxClassID'      => $taxClassID,
            'prices'          => $this
        ]);
    }

    public function __wakeup(): void
    {
        $this->consistentGrossPrices = Settings::boolValue(Globals::CONSISTENT_GROSS_PRICES);
    }

    protected function getPrice(
        int $customerGroupID,
        int $productID,
        int $customerID = 0
    ): ?stdClass {
        $params         = [
            'pid'  => $productID,
            'cgid' => $customerGroupID
        ];
        $customerFilter = ' AND kKundengruppe = :cgid';
        if ($customerID > 0 && $this->hasCustomPrice($customerID)) {
            $params['cid']  = $customerID;
            $customerFilter = ' AND (p.kKundengruppe, COALESCE(p.kKunde, 0)) = (
                            SELECT min(IFNULL(p1.kKundengruppe, :cgid)), max(IFNULL(p1.kKunde, 0))
                            FROM tpreis AS p1
                            WHERE p1.kArtikel = :pid
                                AND (p1.kKundengruppe = 0 OR p1.kKundengruppe = :cgid)
                                AND (p1.kKunde = 0 OR p1.kKunde = :cid))';
        }

        return $this->getDB()->getSingleObject(
            'SELECT kPreis, noDiscount, kKunde
                FROM tpreis AS p
                WHERE kArtikel = :pid' . $customerFilter,
            $params
        );
    }

    /**
     * @return stdClass[]
     */
    protected function getPriceDetails(int $priceID): array
    {
        return $this->getDB()->getObjects(
            'SELECT nAnzahlAb, fVKNetto
                FROM tpreisdetail
                WHERE kPreis = :priceID
                ORDER BY nAnzahlAb',
            ['priceID' => $priceID]
        );
    }

    protected function getSpecialPrice(int $customerGroupID, int $productID): ?stdClass
    {
        return $this->getDB()->getSingleObject(
            "SELECT tsonderpreise.fNettoPreis, tartikelsonderpreis.dEnde AS dEnde_en,
                DATE_FORMAT(tartikelsonderpreis.dEnde, '%d.%m.%Y') AS dEnde_de
                FROM tsonderpreise
                JOIN tartikel 
                    ON tartikel.kArtikel = :productID
                JOIN tartikelsonderpreis 
                    ON tartikelsonderpreis.kArtikelSonderpreis = tsonderpreise.kArtikelSonderpreis
                    AND tartikelsonderpreis.kArtikel = :productID
                    AND tartikelsonderpreis.cAktiv = 'Y'
                    AND tartikelsonderpreis.dStart <= CURDATE()
                    AND (tartikelsonderpreis.dEnde IS NULL OR tartikelsonderpreis.dEnde >= CURDATE()) 
                    AND (tartikelsonderpreis.nAnzahl <= tartikel.fLagerbestand 
                        OR tartikelsonderpreis.nIstAnzahl = 0)
                WHERE tsonderpreise.kKundengruppe = :customerGroup",
            [
                'productID'     => $productID,
                'customerGroup' => $customerGroupID,
            ]
        );
    }

    protected function getTaxData(int $productID): ?stdClass
    {
        return $this->getDB()->getSingleObject(
            'SELECT kSteuerklasse, fMwSt
                FROM tartikel
                WHERE kArtikel = :pid',
            ['pid' => $productID]
        );
    }

    public function getDB(): DbInterface
    {
        if ($this->db === null || $this->db->isConnected() === false) {
            $this->db = Shop::Container()->getDB();
        }

        return $this->db;
    }

    /**
     * Return recalculated new net price based on the rounded default gross price.
     * This is necessary for having consistent gross prices in case of
     * threshold delivery (Tax rate != default tax rate).
     *
     * @param float|numeric-string $netPrice the product net price
     * @param float|numeric-string $defaultTax the default tax factor of the product e.g. 19 for 19% vat
     * @param float|numeric-string $conversionTax the taxFactor of the delivery country / delivery threshold
     * @return double - calculated net price based on a rounded(!!!) DEFAULT gross price.
     */
    private function getRecalculatedNetPrice(
        float|string $netPrice,
        float|string $defaultTax,
        float|string $conversionTax,
        int $precision = 2
    ): float {
        $newNetPrice = $netPrice;

        if (
            $this->consistentGrossPrices === true
            && (float)$defaultTax > 0.0
            && (float)$conversionTax > 0.0
            && (float)$defaultTax !== (float)$conversionTax
            && Frontend::getCustomerGroup()->isMerchant() === false
        ) {
            $newNetPrice = \round((float)$netPrice * ((float)$defaultTax + 100) / 100, $precision)
                / ((float)$conversionTax + 100) * 100;
        }

        \executeHook(\HOOK_RECALCULATED_NET_PRICE, [
            'netPrice'      => $netPrice,
            'defaultTax'    => $defaultTax,
            'conversionTax' => $conversionTax,
            'newNetPrice'   => &$newNetPrice
        ]);

        return (double)$newNetPrice;
    }

    public function customerHasCustomPriceForProduct(int $customerID, int $productID): bool
    {
        if (!$this->hasCustomPrice($customerID)) {
            return false;
        }
        $cnt = $this->getDB()->getSingleInt(
            'SELECT COUNT(kPreis) AS cnt 
                FROM tpreis
                WHERE kKunde = :cid 
                  AND (kArtikel = :pid OR kArtikel IN (SELECT kArtikel FROM tartikel WHERE kVaterArtikel = :pid))',
            'cnt',
            ['cid' => $customerID, 'pid' => $productID]
        );

        return $cnt > 0;
    }

    public function hasCustomPrice(int $customerID): bool
    {
        if ($customerID <= 0) {
            return false;
        }
        $cacheID = 'custprice_' . $customerID;
        if (($data = Shop::Container()->getCache()->get($cacheID)) === false) {
            $data = $this->getDB()->getSingleObject(
                'SELECT COUNT(kPreis) AS nAnzahl 
                    FROM tpreis
                    WHERE kKunde = :cid',
                ['cid' => $customerID]
            );
            if ($data !== null) {
                $cacheTags = [\CACHING_GROUP_ARTICLE];
                Shop::Container()->getCache()->set($cacheID, $data, $cacheTags);
            }
        }

        return $data !== null && $data->nAnzahl > 0;
    }

    public function isDiscountable(): bool
    {
        return !($this->Kundenpreis_aktiv || $this->Sonderpreis_aktiv || $this->noDiscount);
    }

    public function rabbatierePreise(float $discount, float $offset = 0.0): self
    {
        if ($this->oPriceRange === null || (int)$discount === 0 || !$this->isDiscountable()) {
            return $this;
        }
        $this->rabatt       = $discount;
        $this->alterVKNetto = $this->fVKNetto;

        $this->fVKNetto = ($this->fVKNetto - $this->fVKNetto * $discount / 100.0) + $offset;
        $this->fPreis1  = ($this->fPreis1 - $this->fPreis1 * $discount / 100.0) + $offset;
        $this->fPreis2  = ($this->fPreis2 - $this->fPreis2 * $discount / 100.0) + $offset;
        $this->fPreis3  = ($this->fPreis3 - $this->fPreis3 * $discount / 100.0) + $offset;
        $this->fPreis4  = ($this->fPreis4 - $this->fPreis4 * $discount / 100.0) + $offset;
        $this->fPreis5  = ($this->fPreis5 - $this->fPreis5 * $discount / 100.0) + $offset;

        foreach ($this->fPreis_arr as $i => $fPreis) {
            $this->fPreis_arr[$i] = ($fPreis - $fPreis * $discount / 100.0) + $offset;
        }
        $this->berechneVKs();
        $this->oPriceRange->setDiscount($discount);

        return $this;
    }

    public function localizePreise(?Currency $currency = null): self
    {
        if ($this->fUst === null) {
            return $this;
        }
        $currency                  = self::getCurrency($currency);
        $this->cPreisLocalized_arr = [];
        foreach ($this->fPreis_arr as $price) {
            $this->cPreisLocalized_arr[] = [
                self::getLocalizedPriceString(Tax::getGross($price, $this->fUst, 4), $currency),
                self::getLocalizedPriceString($price, $currency)
            ];
        }
        $this->cVKLocalized[0] = self::getLocalizedPriceString(
            Tax::getGross($this->fVKNetto, $this->fUst, 4),
            $currency
        );
        $this->cVKLocalized[1] = self::getLocalizedPriceString($this->fVKNetto, $currency);
        $this->fVKBrutto       = Tax::getGross($this->fVKNetto, $this->fUst);
        if ($this->alterVKNetto) {
            $this->alterVKLocalized[0] = self::getLocalizedPriceString(
                Tax::getGross($this->alterVKNetto, $this->fUst, 4),
                $currency
            );
            $this->alterVKLocalized[1] = self::getLocalizedPriceString($this->alterVKNetto, $currency);
        }

        return $this;
    }

    public function berechneVKs(): self
    {
        if ($this->fUst === null) {
            return $this;
        }
        $factor = Frontend::getCurrency()->getConversionFactor();

        $this->fVKBrutto = Tax::getGross($this->fVKNetto, $this->fUst);

        $this->fVK[0] = Tax::getGross($this->fVKNetto * $factor, $this->fUst);
        $this->fVK[1] = $this->fVKNetto * $factor;

        $this->alterVK[0] = Tax::getGross($this->alterVKNetto * $factor, $this->fUst);
        $this->alterVK[1] = $this->alterVKNetto * $factor;

        $this->fStaffelpreis_arr = [];
        foreach ($this->fPreis_arr as $price) {
            $this->fStaffelpreis_arr[] = [
                Tax::getGross($price * $factor, $this->fUst),
                $price * $factor
            ];
        }
        if (!empty($this->alterVKNetto)) {
            $this->discountPercentage = (int)\round(
                (($this->alterVKNetto - $this->fVKNetto) * 100) / $this->alterVKNetto
            );
        }

        return $this;
    }

    public static function getPriceJoinSql(
        int $customerGroupID,
        string $priceAlias = 'tpreis',
        string $detailAlias = 'tpreisdetail',
        string $productAlias = 'tartikel'
    ): string {
        return 'JOIN tpreis AS ' . $priceAlias . ' ON ' . $priceAlias . '.kArtikel = ' . $productAlias . '.kArtikel
                    AND ' . $priceAlias . '.kKundengruppe = ' . $customerGroupID . '
                JOIN tpreisdetail AS ' . $detailAlias . ' ON ' . $detailAlias . '.kPreis = ' . $priceAlias . '.kPreis
                    AND ' . $detailAlias . '.nAnzahlAb = 0';
    }

    /**
     * Set all fvk prices to zero.
     */
    public function setPricesToZero(): void
    {
        $this->fVKNetto  = 0.0;
        $this->fVKBrutto = 0.0;
        foreach ($this->fVK as $key => $fVK) {
            $this->fVK[$key] = 0.0;
        }
        foreach ($this->alterVK as $key => $alterVK) {
            $this->alterVK[$key] = 0.0;
        }
        $this->fPreis1 = 0.0;
        $this->fPreis2 = 0.0;
        $this->fPreis3 = 0.0;
        $this->fPreis4 = 0.0;
        $this->fPreis5 = 0.0;
        foreach ($this->fPreis_arr as $key => $price) {
            $this->fPreis_arr[$key] = 0.0;
        }
        foreach ($this->fStaffelpreis_arr as $fStaffelpreisKey => $fStaffelpreis) {
            foreach ($fStaffelpreis as $pKey => $price) {
                $this->fStaffelpreis_arr[$fStaffelpreisKey][$pKey] = 0.0;
            }
        }
    }

    /**
     * @former gibPreisLocalizedOhneFaktor()
     */
    public static function getLocalizedPriceWithoutFactor(
        float|int|string $price,
        Currency|stdClass|null $currency = null,
        bool $html = true
    ): string {
        $currency = $currency ?? Frontend::getCurrency();
        if (\is_a($currency, stdClass::class)) {
            $currency = new Currency($currency->kWaehrung);
        }
        $localized = \number_format(
            (float)$price,
            2,
            $currency->getDecimalSeparator(),
            $currency->getThousandsSeparator()
        );
        $name      = $html ? $currency->getHtmlEntity() : $currency->getName();

        return $currency->getForcePlacementBeforeNumber()
            ? $name . ' ' . $localized
            : $localized . ' ' . $name;
    }

    /**
     * @former self::getLocalizedPriceString()
     */
    public static function getLocalizedPriceString(
        float|string|null $price,
        mixed $currency = null,
        bool $html = true,
        int $decimals = 2
    ): string {
        $currency     = self::getCurrency($currency);
        $localized    = \number_format(
            (float)$price * $currency->getConversionFactor(),
            $decimals,
            $currency->getDecimalSeparator(),
            $currency->getThousandsSeparator()
        );
        $currencyName = $html ? $currency->getHtmlEntity() : $currency->getName();

        \executeHook(\HOOK_LOCALIZED_PRICE_STRING, [
            'price'        => $price,
            'currency'     => &$currency,
            'html'         => $html,
            'decimals'     => $decimals,
            'currencyName' => &$currencyName,
            'localized'    => &$localized
        ]);

        return $currency->getForcePlacementBeforeNumber()
            ? ($currencyName . ' ' . $localized)
            : ($localized . ' ' . $currencyName);
    }

    private static function getCurrency(mixed $currency): Currency
    {
        if ($currency instanceof Currency) {
            return $currency;
        }
        if ($currency === null || \is_numeric($currency) || \is_bool($currency)) {
            $currency = Frontend::getCurrency();
        } elseif ($currency instanceof stdClass) {
            $loaded = null;
            foreach (Frontend::getCurrencies() as $cur) {
                if ($cur->getID() === (int)$currency->kWaehrung) {
                    $loaded = $cur;
                    break;
                }
            }
            $currency = $loaded ?? new Currency((int)$currency->kWaehrung);
        } elseif (\is_string($currency)) {
            $currency = Currency::fromISO($currency);
        } else {
            $currency = new Currency();
        }

        return $currency;
    }

    public function setScalePrice(int $priceID, float $defaultTax, int $customerGroupID, int $productID): void
    {
        $currentTax        = $this->fUst;
        $specialPriceValue = null;
        $prices            = $this->getPriceDetails($priceID);
        if ($currentTax === null) {
            return;
        }
        foreach ($prices as $i => $price) {
            // Standardpreis
            if ($price->nAnzahlAb < 1) {
                $this->fVKNetto = $this->getRecalculatedNetPrice($price->fVKNetto, $defaultTax, $currentTax);
                $specialPrice   = $this->getSpecialPrice($customerGroupID, $productID);

                if ($specialPrice !== null) {
                    $specialPrice->fNettoPreis = $this->getRecalculatedNetPrice(
                        $specialPrice->fNettoPreis ?? 0.0,
                        $defaultTax,
                        $currentTax
                    );
                    if ($specialPrice->fNettoPreis < $this->fVKNetto) {
                        $specialPriceValue       = $specialPrice->fNettoPreis;
                        $this->alterVKNetto      = $this->fVKNetto;
                        $this->fVKNetto          = $specialPriceValue;
                        $this->Sonderpreis_aktiv = 1;
                        $this->SonderpreisBis_de = $specialPrice->dEnde_de;
                        $this->SonderpreisBis_en = $specialPrice->dEnde_en;
                    }
                }
            } else {
                // Alte Preisstaffeln
                if ($i <= 5) {
                    $scaleGetter = 'nAnzahl' . $i;
                    $priceGetter = 'fPreis' . $i;

                    $this->{$scaleGetter} = (int)$price->nAnzahlAb;
                    $this->{$priceGetter} = $specialPriceValue ?? $this->getRecalculatedNetPrice(
                        $price->fVKNetto,
                        $defaultTax,
                        $currentTax
                    );
                }

                $this->nAnzahl_arr[] = (int)$price->nAnzahlAb;
                $this->fPreis_arr[]  =
                    ($specialPriceValue !== null && $specialPriceValue < (double)$price->fVKNetto)
                        ? $specialPriceValue
                        : $this->getRecalculatedNetPrice($price->fVKNetto, $defaultTax, $currentTax);
            }
        }
    }
}
