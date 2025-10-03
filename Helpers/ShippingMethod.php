<?php

/** @noinspection PhpDeprecationInspection */

declare(strict_types=1);

namespace JTL\Helpers;

use JTL\Cart\Cart;
use JTL\Cart\CartItem;
use JTL\Catalog\Product\Artikel;
use JTL\Catalog\Product\Preise;
use JTL\Checkout\Versandart;
use JTL\Country\Country;
use JTL\Customer\CustomerGroup;
use JTL\Firma;
use JTL\Language\LanguageHelper;
use JTL\Session\Frontend;
use JTL\Shop;
use stdClass;

use function Functional\first;
use function Functional\map;
use function Functional\some;

/**
 * Class ShippingMethod
 * @package JTL\Helpers
 * @deprecated since 5.5.0. Use Shop::Container()->getShippingService() instead.
 */
class ShippingMethod
{
    private static ?self $instance = null;

    public string $cacheID;

    /**
     * @var stdClass[]
     */
    public array $shippingMethods;

    /**
     * @var array<int, array<int, stdClass[]>>
     */
    public array $countries = [];

    public function __construct()
    {
        $this->cacheID         = 'smeth_' . Shop::Container()->getCache()->getBaseID();
        $this->shippingMethods = $this->getShippingMethods();
        self::$instance        = $this;
    }

    public static function getInstance(): self
    {
        return self::$instance ?? new self();
    }

    /**
     * @return stdClass[]
     * @deprecated since 5.5.0. Use Shop::Container()->getShippingService()->getAllShippingMethods() instead.
     */
    public function getShippingMethods(): array
    {
        \trigger_error(
            __METHOD__ . ' is deprecated and should not be used anymore.',
            \E_USER_DEPRECATED
        );

        return $this->shippingMethods ?? Shop::Container()->getDB()->getObjects('SELECT * FROM tversandart');
    }

    private static function getDeliveryText(int $minDelivery, int $maxDelivery, string $languageVar): string
    {
        if (!\stripos($languageVar, 'simple')) {
            return \str_replace(
                ['#MINDELIVERYTIME#', '#MAXDELIVERYTIME#'],
                [(string)$minDelivery, (string)$maxDelivery],
                Shop::Lang()->get($languageVar)
            );
        }

        return \str_replace(
            '#DELIVERYTIME#',
            (string)$minDelivery,
            Shop::Lang()->get($languageVar)
        );
    }

    /**
     * @return stdClass[]
     * @deprecated since 5.5.0. Use Shop::Container()->getShippingService()->getPossibleFreeShippingMethods() instead.
     */
    public function filter(float|int|string $freeFromX): array
    {
        \trigger_error(
            __METHOD__ . ' is deprecated and should not be used anymore.',
            \E_USER_DEPRECATED
        );

        $freeFromX = (float)$freeFromX;

        return \array_filter(
            $this->shippingMethods,
            static function ($s) use ($freeFromX): bool {
                return $s->fVersandkostenfreiAbX !== '0.00'
                    && (float)$s->fVersandkostenfreiAbX > 0
                    && (float)$s->fVersandkostenfreiAbX <= $freeFromX;
            }
        );
    }

    /**
     * @param array<int, float> $prices
     * @deprecated since 5.5.0. Use Shop::Container()->getShippingService()->getFreeShippingCountriesByProduct().
     */
    public function getFreeShippingCountries(array $prices, int $cgroupID, int $shippingClassID = 0): string
    {
        \trigger_error(
            __METHOD__ . ' is deprecated and should not be used anymore.',
            \E_USER_DEPRECATED
        );

        if (!isset($this->countries[$cgroupID][$shippingClassID])) {
            if (!isset($this->countries[$cgroupID])) {
                $this->countries[$cgroupID] = [];
            }
            $this->countries[$cgroupID][$shippingClassID] = Shop::Container()->getDB()->getObjects(
                "SELECT *
                    FROM tversandart
                    WHERE fVersandkostenfreiAbX > 0
                        AND (cVersandklassen = '-1'
                        OR cVersandklassen RLIKE :sClasses)
                        AND (cKundengruppen = '-1' OR FIND_IN_SET(:cGroupID, REPLACE(cKundengruppen, ';', ',')) > 0)",
                [
                    'sClasses' => '^([0-9 -]* )?' . $shippingClassID . ' ',
                    'cGroupID' => $cgroupID
                ]
            );
        }
        $shippingFreeCountries = [];
        foreach ($this->countries[$cgroupID][$shippingClassID] as $_method) {
            $price = $_method->eSteuer === 'brutto' ? $prices[0] : $prices[1];
            if ((float)$_method->fVersandkostenfreiAbX >= $price) {
                continue;
            }
            foreach (\explode(' ', $_method->cLaender) as $_country) {
                $shippingFreeCountries[] = $_country;
            }
        }

        return \implode(', ', \array_unique($shippingFreeCountries));
    }

    /**
     * @deprecated since 5.5.0. Use Shop::Container()->getShippingService()->cartIsDependent() === false.
     */
    public static function normalerArtikelversand(string $country): bool
    {
        \trigger_error(
            __METHOD__ . ' is deprecated and should not be used anymore.',
            \E_USER_DEPRECATED
        );

        return some(Frontend::getCart()->PositionenArr, static function ($item) use ($country): bool {
            return (int)$item->nPosTyp === \C_WARENKORBPOS_TYP_ARTIKEL
                && !self::gibArtikelabhaengigeVersandkosten($country, $item->Artikel, $item->nAnzahl);
        });
    }

    /**
     * @deprecated since 5.5.0. Use Shop::Container()->getShippingService()->cartIsMixed().
     */
    public static function hasSpecificShippingcosts(string $country): bool
    {
        \trigger_error(
            __METHOD__ . ' is deprecated and should not be used anymore.',
            \E_USER_DEPRECATED
        );

        return !empty(self::gibArtikelabhaengigeVersandkostenImWK($country, Frontend::getCart()->PositionenArr));
    }

    /**
     * @return stdClass[]
     * @deprecated since 5.5.0. Use Shop::Container()->getShippingService()->filterPaymentMethodByID() instead.
     */
    public static function getPaymentMethods(int $shippingMethodID, int $cgroupID, int $filterPaymentID = 0): array
    {
        \trigger_error(
            __METHOD__ . ' is deprecated and should not be used anymore.'
            . ' Use Shop::Container()->getShippingService()->filterPaymentMethodByID() instead',
            \E_USER_DEPRECATED
        );

        $filterSQL = '';
        $params    = [
            'methodID' => $shippingMethodID,
            'cGroupID' => $cgroupID,
        ];
        if ($filterPaymentID > 0) {
            $filterSQL           = ' AND tzahlungsart.kZahlungsart = :paymentID ';
            $params['paymentID'] = $filterPaymentID;
        }

        return Shop::Container()->getDB()->getObjects(
            'SELECT tversandartzahlungsart.*, tzahlungsart.*
                 FROM tversandartzahlungsart, tzahlungsart
                 WHERE tversandartzahlungsart.kVersandart = :methodID
                     ' . $filterSQL . "
                     AND tversandartzahlungsart.kZahlungsart = tzahlungsart.kZahlungsart
                     AND (tzahlungsart.cKundengruppen IS NULL OR tzahlungsart.cKundengruppen = ''
                        OR FIND_IN_SET(:cGroupID, REPLACE(tzahlungsart.cKundengruppen, ';', ',')) > 0)
                     AND tzahlungsart.nActive = 1
                     AND tzahlungsart.nNutzbar = 1
                 ORDER BY tzahlungsart.nSort",
            $params
        );
    }

    /**
     * @former gibMoeglicheVersandarten()
     * @return stdClass[]
     * @deprecated since 5.5.0. Use Shop::Container()->getShippingService()->getPossibleShippingMethods() instead.
     */
    public static function getPossibleShippingMethods(
        string $countryCode,
        string $zip,
        string $shippingClasses,
        int $cgroupID
    ): array {
        \trigger_error(
            __METHOD__ . ' is deprecated and should not be used anymore.',
            \E_USER_DEPRECATED
        );

        $minSum    = 10000;
        $vatNote   = null;
        $db        = Shop::Container()->getDB();
        $depending = self::normalerArtikelversand($countryCode) === false ? 'Y' : 'N';
        $methods   = $db->getObjects(
            "SELECT * FROM tversandart
                WHERE cNurAbhaengigeVersandart = :depOnly
                    AND cLaender LIKE :iso
                    AND (cVersandklassen = '-1'
                    OR cVersandklassen RLIKE :sClasses)
                    AND (cKundengruppen = '-1'
                    OR FIND_IN_SET(:cGroupID, REPLACE(cKundengruppen, ';', ',')) > 0)
                ORDER BY nSort",
            [
                'iso'      => '%' . $countryCode . '%',
                'cGroupID' => $cgroupID,
                'sClasses' => '^([0-9 -]* )?' . $shippingClasses . ' ',
                'depOnly'  => $depending
            ]
        );
        if (\count($methods) === 0) {
            return [];
        }
        $cart                     = Frontend::getCart();
        $taxClassID               = $cart->gibVersandkostenSteuerklasse();
        $hasSpecificShippingcosts = self::hasSpecificShippingcosts($countryCode);
        $netPricesActive          = Frontend::getCustomerGroup()->isMerchant();
        $currency                 = Frontend::getCurrency();
        foreach ($methods as $i => $method) {
            $gross = $method->eSteuer !== 'netto';

            $method->kVersandart        = (int)$method->kVersandart;
            $method->kVersandberechnung = (int)$method->kVersandberechnung;
            $method->nSort              = (int)$method->nSort;
            $method->nMinLiefertage     = (int)$method->nMinLiefertage;
            $method->nMaxLiefertage     = (int)$method->nMaxLiefertage;
            $method->Zuschlag           = self::getAdditionalFees($method, $countryCode, $zip);
            $method->fEndpreis          = self::calculateShippingFees($method, $countryCode, null);
            if ($method->fEndpreis === -1) {
                unset($methods[$i]);
                continue;
            }
            if ($netPricesActive === true) {
                $shippingCosts = $gross
                    ? $method->fEndpreis / (100 + Tax::getSalesTax($taxClassID)) * 100.0
                    : \round($method->fEndpreis, 2);
                $vatNote       = ' ' . Shop::Lang()->get('plus', 'productDetails')
                    . ' ' . Shop::Lang()->get('vat', 'productDetails');
            } elseif ($gross) {
                $shippingCosts = $method->fEndpreis;
            } else {
                $oldDeliveryCountryCode = $_SESSION['cLieferlandISO'];
                if ($oldDeliveryCountryCode !== $countryCode) {
                    Tax::setTaxRates($countryCode, true);
                }
                $shippingCosts = \round($method->fEndpreis * (100 + Tax::getSalesTax($taxClassID)) / 100, 2);
                if ($oldDeliveryCountryCode !== $countryCode) {
                    Tax::setTaxRates($oldDeliveryCountryCode, true);
                }
            }
            if ($method->fEndpreis < $minSum && $method->cIgnoreShippingProposal !== 'Y') {
                $minSum = $method->fEndpreis;
            }
            $method->angezeigterName           = [];
            $method->angezeigterHinweistext    = [];
            $method->cLieferdauer              = [];
            $method->specificShippingcosts_arr = null;
            foreach (Frontend::getLanguages() as $language) {
                $code      = $language->getCode();
                $localized = $db->select(
                    'tversandartsprache',
                    'kVersandart',
                    $method->kVersandart,
                    'cISOSprache',
                    $code
                );
                if (isset($localized, $localized->cName)) {
                    $method->angezeigterName[$code]        = $localized->cName;
                    $method->angezeigterHinweistext[$code] = $localized->cHinweistextShop;
                    $method->cLieferdauer[$code]           = $localized->cLieferdauer;
                }
            }
            if ($method->fEndpreis === 0) {
                // Abfrage ob ein Artikel Artikelabhängige Versandkosten besitzt
                $method->cPreisLocalized = Shop::Lang()->get('freeshipping');
                if ($hasSpecificShippingcosts === true) {
                    $method->cPreisLocalized           = Preise::getLocalizedPriceString(
                        $shippingCosts,
                        $currency
                    );
                    $method->specificShippingcosts_arr = self::gibArtikelabhaengigeVersandkostenImWK(
                        $countryCode,
                        $cart->PositionenArr
                    );
                }
            } else {
                // Abfrage ob ein Artikel Artikelabhängige Versandkosten besitzt
                $method->cPreisLocalized = Preise::getLocalizedPriceString($shippingCosts, $currency)
                    . ($vatNote ?? '');
                if ($hasSpecificShippingcosts === true) {
                    $method->specificShippingcosts_arr = self::gibArtikelabhaengigeVersandkostenImWK(
                        $countryCode,
                        $cart->PositionenArr
                    );
                }
            }
            // Abfrage ob die Zahlungsart/en zur Versandart gesetzt ist/sind
            $paymentMethods = self::getPaymentMethods($method->kVersandart, $cgroupID);
            $method->valid  = some($paymentMethods, static function (stdClass $pmm): bool {
                return PaymentMethod::shippingMethodWithValidPaymentMethod($pmm);
            });
        }
        // auf anzeige filtern
        $possibleMethods = \array_filter(
            \array_merge($methods),
            static function (stdClass $p) use ($minSum): bool {
                return $p->valid
                    && ($p->cAnzeigen === 'immer' || ($p->cAnzeigen === 'guenstigste' && $p->fEndpreis <= $minSum));
            }
        );
        // evtl. Versandkupon anwenden
        if (!empty($_SESSION['VersandKupon'])) {
            foreach ($possibleMethods as $method) {
                $method->fEndpreis = 0;
                // lokalisieren
                $method->cPreisLocalized = Preise::getLocalizedPriceString($method->fEndpreis, $currency);
            }
        }

        return $possibleMethods;
    }

    /**
     * @former ermittleVersandkosten()
     * @deprecated since 5.5.0
     */
    public static function getShippingCosts(string $country, string $zip, string &$errorMsg = ''): bool
    {
        \trigger_error(
            __METHOD__ . ' is deprecated and should not be used anymore.',
            \E_USER_DEPRECATED
        );

        if (\mb_strlen($country) === 0 || \mb_strlen($zip) === 0) {
            return !isset($_POST['versandrechnerBTN']);
        }
        $shippingMethods = self::getPossibleShippingMethods(
            $country,
            $zip,
            self::getShippingClasses(Frontend::getCart()),
            Frontend::getCustomer()->getGroupID()
        );
        if (\count($shippingMethods) > 0) {
            Shop::Smarty()
                ->assign(
                    'ArtikelabhaengigeVersandarten',
                    self::gibArtikelabhaengigeVersandkostenImWK(
                        $country,
                        Frontend::getCart()->PositionenArr
                    )
                )
                ->assign('Versandarten', $shippingMethods)
                ->assign('Versandland', LanguageHelper::getCountryCodeByCountryName($country))
                ->assign('VersandPLZ', Text::filterXSS($zip));
        } else {
            $errorMsg = Shop::Lang()->get('noDispatchAvailable');
        }
        \executeHook(\HOOK_WARENKORB_PAGE_ERMITTLEVERSANDKOSTEN);

        return true;
    }

    /**
     * @former ermittleVersandkostenExt()
     * @param array<array{kArtikel: int, fAnzahl: float, cInputData: string}> $products
     * @deprecated since 5.5.0. This function will be removed in the near future.
     */
    public static function getShippingCostsExt(array $products): string
    {
        \trigger_error(
            __METHOD__ . ' is deprecated and will be removed in the near future.',
            \E_USER_DEPRECATED
        );

        if (!isset($_SESSION['shipping_count'])) {
            $_SESSION['shipping_count'] = 0;
        }
        if (\count($products) === 0) {
            return '';
        }
        $iso      = $_SESSION['cLieferlandISO'] ?? 'DE';
        $cart     = Frontend::getCart();
        $cgroup   = Frontend::getCustomerGroup();
        $currency = Frontend::getCurrency();
        $cgroupID = $cgroup->getID();
        $db       = Shop::Container()->getDB();
        $cache    = Shop::Container()->getCache();
        // Baue ZusatzArtikel
        $additionalProduct                  = new stdClass();
        $additionalProduct->fAnzahl         = 0;
        $additionalProduct->fWarenwertNetto = 0;
        $additionalProduct->fGewicht        = 0;

        $shippingClasses        = self::getShippingClasses($cart);
        $defaultOptions         = Artikel::getDefaultOptions();
        $additionalShippingFees = 0;
        $perTaxClass            = [];
        $taxClassID             = 0;
        // Vorkonditionieren -- Gleiche kartikel aufsummieren
        // aber nur, wenn artikelabhaengiger Versand bei dem jeweiligen kArtikel
        $productIDs = [];
        foreach ($products as $product) {
            $productID              = (int)$product['kArtikel'];
            $productIDs[$productID] = isset($productIDs[$productID]) ? 1 : 0;
        }
        $merge = false;
        foreach ($productIDs as $productID => $nArtikelAssoc) {
            if ($nArtikelAssoc !== 1) {
                continue;
            }
            $tmpProduct = (new Artikel($db, $cgroup, $currency, $cache))
                ->fuelleArtikel($productID, $defaultOptions, $cgroupID);
            // Normaler Variationsartikel
            if (
                $tmpProduct !== null
                && $tmpProduct->nIstVater === 0
                && $tmpProduct->kVaterArtikel === 0
                && \count($tmpProduct->Variationen) > 0
                && self::pruefeArtikelabhaengigeVersandkosten($tmpProduct) === 2
            ) {
                // Nur wenn artikelabhaengiger Versand gestaffelt als Funktionsattribut gesetzt ist
                $fAnzahl = 0;
                foreach ($products as $i => $prod) {
                    if ($prod['kArtikel'] === $productID) {
                        $fAnzahl += $prod['fAnzahl'];
                        unset($products[$i]);
                    }
                }

                $merged             = [];
                $merged['kArtikel'] = $productID;
                $merged['fAnzahl']  = $fAnzahl;
                $products[]         = $merged;
                $merge              = true;
            }
        }
        if ($merge) {
            $products = \array_merge($products);
        }
        foreach ($products as $product) {
            $tmpProduct = (new Artikel($db, $cgroup, $currency, $cache))
                ->fuelleArtikel($product['kArtikel'], $defaultOptions, $cgroupID);
            if ($tmpProduct === null || $tmpProduct->kArtikel <= 0 || $tmpProduct->Preise === null) {
                continue;
            }
            $taxClassID = $tmpProduct->kSteuerklasse ?? 0;
            // Artikelabhaengige Versandkosten?
            if ($tmpProduct->nIstVater === 0) {
                // Summen pro Steuerklasse summieren
                if ($tmpProduct->kSteuerklasse === null) {
                    $perTaxClass[$tmpProduct->kSteuerklasse] = 0;
                }

                $perTaxClass[$tmpProduct->kSteuerklasse] += $tmpProduct->Preise->fVKNetto * $product['fAnzahl'];

                $oVersandPos = self::gibHinzukommendeArtikelAbhaengigeVersandkosten(
                    $tmpProduct,
                    $iso,
                    $product['fAnzahl']
                );
                if ($oVersandPos !== false) {
                    $additionalShippingFees += $oVersandPos->fKosten;
                    continue;
                }
            }
            // Normaler Artikel oder Kind Artikel
            if ($tmpProduct->kVaterArtikel > 0 || \count($tmpProduct->Variationen) === 0) {
                $additionalProduct->fAnzahl         += $product['fAnzahl'];
                $additionalProduct->fWarenwertNetto += $product['fAnzahl'] * $tmpProduct->Preise->fVKNetto;
                $additionalProduct->fGewicht        += $product['fAnzahl'] * $tmpProduct->fGewicht;

                if (
                    \mb_strlen($shippingClasses) > 0
                    && !\str_contains($shippingClasses, (string)$tmpProduct->kVersandklasse)
                ) {
                    $shippingClasses = '-' . $tmpProduct->kVersandklasse;
                } elseif (\mb_strlen($shippingClasses) === 0) {
                    $shippingClasses = (string)($tmpProduct->kVersandklasse ?? '');
                }
            } elseif (
                $tmpProduct->nIstVater === 0
                && $tmpProduct->kVaterArtikel === 0
                && \count($tmpProduct->Variationen) > 0
            ) { // Normale Variation
                if (\str_starts_with($product['cInputData'], '_')) {
                    // 1D
                    [$property0, $propertyValue0] = \explode(':', \mb_substr($product['cInputData'], 1));

                    $variation = Product::findVariation(
                        $tmpProduct->Variationen,
                        (int)$property0,
                        (int)$propertyValue0
                    );

                    $additionalProduct->fAnzahl         += $product['fAnzahl'];
                    $additionalProduct->fWarenwertNetto += $product['fAnzahl']
                        * ($tmpProduct->Preise->fVKNetto + $variation->fAufpreisNetto);
                    $additionalProduct->fGewicht        += $product['fAnzahl']
                        * ($tmpProduct->fGewicht + $variation->fGewichtDiff);
                } else {
                    // 2D
                    [$cVariation0, $cVariation1]  = \explode('_', $product['cInputData']);
                    [$property0, $propertyValue0] = \explode(':', $cVariation0);
                    [$property1, $propertyValue1] = \explode(':', $cVariation1);

                    $variation0 = Product::findVariation(
                        $tmpProduct->Variationen,
                        (int)$property0,
                        (int)$propertyValue0
                    );
                    $variation1 = Product::findVariation(
                        $tmpProduct->Variationen,
                        (int)$property1,
                        (int)$propertyValue1
                    );

                    $additionalProduct->fAnzahl         += $product['fAnzahl'];
                    $additionalProduct->fWarenwertNetto += $product['fAnzahl'] *
                        ($tmpProduct->Preise->fVKNetto + $variation0->fAufpreisNetto + $variation1->fAufpreisNetto);
                    $additionalProduct->fGewicht        += $product['fAnzahl'] *
                        ($tmpProduct->fGewicht + $variation0->fGewichtDiff + $variation1->fGewichtDiff);
                }
                if (
                    \mb_strlen($shippingClasses) > 0
                    && !\str_contains($shippingClasses, (string)$tmpProduct->kVersandklasse)
                ) {
                    $shippingClasses = '-' . $tmpProduct->kVersandklasse;
                } elseif (\mb_strlen($shippingClasses) === 0) {
                    $shippingClasses = (string)($tmpProduct->kVersandklasse ?? '');
                }
            } elseif ($tmpProduct->nIstVater > 0) { // Variationskombination (Vater)
                $child = new Artikel($db);
                if (\str_starts_with($product['cInputData'] ?? '', '_')) {
                    // 1D
                    $cVariation0                  = \mb_substr($product['cInputData'], 1);
                    [$property0, $propertyValue0] = \explode(':', $cVariation0);
                    $childProductID               = Product::getChildProductIDByAttribute(
                        $tmpProduct->kArtikel,
                        (int)$property0,
                        (int)$propertyValue0
                    );
                    $child->fuelleArtikel($childProductID, $defaultOptions, $cgroupID);
                    if ($child->kSteuerklasse === null || $child->Preise === null) {
                        continue;
                    }
                } else {
                    // 2D
                    [$cVariation0, $cVariation1]  = \explode('_', $product['cInputData']);
                    [$property0, $propertyValue0] = \explode(':', $cVariation0);
                    [$property1, $propertyValue1] = \explode(':', $cVariation1);

                    $childProductID = Product::getChildProductIDByAttribute(
                        $tmpProduct->kArtikel,
                        (int)$property0,
                        (int)$propertyValue0,
                        (int)$property1,
                        (int)$propertyValue1
                    );
                    $child->fuelleArtikel($childProductID, $defaultOptions, $cgroupID);
                }
                if (!\array_key_exists($child->kSteuerklasse, $perTaxClass)) {
                    $perTaxClass[$child->kSteuerklasse] = 0;
                }
                $perTaxClass[$child->kSteuerklasse] += $child->Preise->fVKNetto * $product['fAnzahl'];
                $sum                                = self::gibHinzukommendeArtikelAbhaengigeVersandkosten(
                    $child,
                    $iso,
                    $product['fAnzahl']
                );
                if ($sum !== false) {
                    $additionalShippingFees += $sum;
                    continue;
                }
                $additionalProduct->fAnzahl         += $product['fAnzahl'];
                $additionalProduct->fWarenwertNetto += $product['fAnzahl'] * $child->Preise->fVKNetto;
                $additionalProduct->fGewicht        += $product['fAnzahl'] * $child->fGewicht;
                if (
                    \mb_strlen($shippingClasses) > 0
                    && !\str_contains($shippingClasses, (string)$child->kVersandklasse)
                ) {
                    $shippingClasses = '-' . $child->kVersandklasse;
                } elseif (\mb_strlen($shippingClasses) === 0) {
                    $shippingClasses = (string)$child->kVersandklasse;
                }
            }
        }

        if (GeneralObject::hasCount('PositionenArr', $cart)) {
            // Wenn etwas im Warenkorb ist, dann Vesandart vom Warenkorb rausfinden
            $currentShippingMethod = self::getFavourableShippingMethod(
                $iso,
                $shippingClasses,
                $cgroupID,
                null
            );
            $depending             = self::gibArtikelabhaengigeVersandkostenImWK(
                $iso,
                $cart->PositionenArr
            );

            $sum = 0;
            foreach ($depending as $costs) {
                $sum += $costs->fKosten;
            }

            $currentShippingMethod->fEndpreis += $sum;
            $shippingMethod                   = self::getFavourableShippingMethod(
                $iso,
                $shippingClasses,
                $cgroupID,
                $additionalProduct
            );
            $shippingMethod->fEndpreis        += ($sum + $additionalShippingFees);
        } else {
            $currentShippingMethod            = new stdClass();
            $shippingMethod                   = new stdClass();
            $currentShippingMethod->fEndpreis = 0;
            $shippingMethod->fEndpreis        = $additionalShippingFees;
        }

        if (\abs($shippingMethod->fEndpreis - $currentShippingMethod->fEndpreis) > 0.01) {
            // Versand mit neuen Artikeln > als Versand ohne Steuerklasse bestimmen
            foreach ($cart->PositionenArr as $item) {
                if ((int)$item->nPosTyp === \C_WARENKORBPOS_TYP_ARTIKEL) {
                    if ($item->Artikel === null) {
                        continue;
                    }
                    // Summen pro Steuerklasse summieren
                    if (!\array_key_exists($item->Artikel->kSteuerklasse, $perTaxClass)) {
                        $perTaxClass[$item->Artikel->kSteuerklasse] = 0;
                    }
                    $perTaxClass[$item->Artikel->kSteuerklasse] += $item->Artikel->Preise->fVKNetto * $item->nAnzahl;
                }
            }

            if (Shop::getSettingValue(\CONF_KAUFABWICKLUNG, 'bestellvorgang_versand_steuersatz') === 'US') {
                $maxSum = 0;
                foreach ($perTaxClass as $j => $fWarensummeProSteuerklasse) {
                    if ($fWarensummeProSteuerklasse > $maxSum) {
                        $maxSum     = $fWarensummeProSteuerklasse;
                        $taxClassID = $j;
                    }
                }
            } else {
                $maxTaxRate = 0;
                foreach ($perTaxClass as $j => $fWarensummeProSteuerklasse) {
                    if (Tax::getSalesTax($j) > $maxTaxRate) {
                        $maxTaxRate = Tax::getSalesTax($j);
                        $taxClassID = $j;
                    }
                }
            }

            return \sprintf(
                Shop::Lang()->get('productExtraShippingNotice'),
                Preise::getLocalizedPriceString(
                    Tax::getGross($shippingMethod->fEndpreis, Tax::getSalesTax((int)$taxClassID), 4)
                )
            );
        }

        return Shop::Lang()->get('productNoExtraShippingNotice');
    }

    /**
     * @former gibGuenstigsteVersandart()
     * @deprecated since 5.5.0. Use Shop::Container()->getShippingService()->getFavourableShippingForProduct() instead.
     */
    public static function getFavourableShippingMethod(
        string $deliveryCountry,
        string $shippingClasses,
        int $customerGroupID,
        stdClass|Artikel|null $product,
        bool $checkProductDepedency = true
    ): ?stdClass {
        \trigger_error(
            __METHOD__ . ' is deprecated and should not be used anymore.',
            \E_USER_DEPRECATED
        );

        $favourableIDX   = 0;
        $minVersand      = 10000;
        $depOnly         = ($checkProductDepedency && self::normalerArtikelversand($deliveryCountry) === false)
            ? 'Y'
            : 'N';
        $shippingMethods = Shop::Container()->getDB()->getObjects(
            "SELECT *
            FROM tversandart
            WHERE cIgnoreShippingProposal != 'Y'
                AND cNurAbhaengigeVersandart = :depOnly
                AND cLaender LIKE :iso
                AND (cVersandklassen = '-1'
                    OR cVersandklassen RLIKE :sClasses)
                AND (cKundengruppen = '-1'
                    OR FIND_IN_SET(:cGroupID, REPLACE(cKundengruppen, ';', ',')) > 0)
            ORDER BY nSort",
            [
                'depOnly'  => $depOnly,
                'iso'      => '%' . $deliveryCountry . '%',
                'cGroupID' => $customerGroupID,
                'sClasses' => '^([0-9 -]* )?' . $shippingClasses . ' '
            ]
        );
        foreach ($shippingMethods as $i => $shippingMethod) {
            $shippingMethod->fEndpreis = self::calculateShippingFees($shippingMethod, $deliveryCountry, $product);
            if ($shippingMethod->fEndpreis === -1) {
                unset($shippingMethods[$i]);
                continue;
            }
            if ($shippingMethod->fEndpreis < $minVersand) {
                $minVersand    = $shippingMethod->fEndpreis;
                $favourableIDX = $i;
            }
        }

        return $shippingMethods[$favourableIDX];
    }

    /**
     * Prueft, ob es artikelabhaengige Versandkosten gibt und falls ja,
     * wird die hinzukommende Versandsumme fuer den Artikel
     * der hinzugefuegt werden soll errechnet und zurueckgegeben.
     */
    public static function gibHinzukommendeArtikelAbhaengigeVersandkosten(
        Artikel $product,
        string $iso,
        float|int $productAmount
    ): false|stdClass {
        \trigger_error(
            __METHOD__ . ' is deprecated and should not be used anymore.',
            \E_USER_DEPRECATED
        );

        $dep = self::pruefeArtikelabhaengigeVersandkosten($product);
        if ($dep === 1) {
            return self::gibArtikelabhaengigeVersandkosten($iso, $product, $productAmount, false);
        }
        if ($dep === 2) {
            // Gib alle Artikel im Warenkorb, die artikelabhaengige Versandkosten beinhalten
            $depending = self::gibArtikelabhaengigeVersandkostenImWK(
                $iso,
                Frontend::getCart()->PositionenArr,
                false
            );

            if (\count($depending) > 0) {
                $amount = $productAmount;
                $total  = 0;
                foreach ($depending as $shipping) {
                    $shipping->kArtikel = (int)$shipping->kArtikel;
                    // Wenn es bereits den hinzukommenden Artikel im Warenkorb gibt
                    // zaehle die Anzahl vom Warenkorb hinzu und gib die Kosten fuer den Artikel im Warenkorb
                    if ($shipping->kArtikel === $product->kArtikel) {
                        $amount += $shipping->nAnzahl;
                        $total  = $shipping->fKosten;
                        break;
                    }
                }

                return self::gibArtikelabhaengigeVersandkosten($iso, $product, $amount, false) - $total;
            }
        }

        return false;
    }

    /**
     * @deprecated since 5.5.0. Use Shop::Container()->getShippingService()->getCustomShippingCostType() instead.
     */
    public static function pruefeArtikelabhaengigeVersandkosten(Artikel $product): int
    {
        \trigger_error(
            __METHOD__ . ' is deprecated and should not be used anymore.',
            \E_USER_DEPRECATED
        );

        $hookReturn = false;
        \executeHook(\HOOK_TOOLS_GLOBAL_PRUEFEARTIKELABHAENGIGEVERSANDKOSTEN, [
            'oArtikel'    => &$product,
            'bHookReturn' => &$hookReturn
        ]);

        if ($hookReturn || $product->FunktionsAttribute === null) {
            return -1;
        }
        if ($product->FunktionsAttribute[\FKT_ATTRIBUT_VERSANDKOSTEN]) {
            // Artikelabhaengige Versandkosten
            return 1;
        }
        if ($product->FunktionsAttribute[\FKT_ATTRIBUT_VERSANDKOSTEN_GESTAFFELT]) {
            // Artikelabhaengige Versandkosten gestaffelt
            return 2;
        }

        return -1;  // Keine artikelabhaengigen Versandkosten
    }

    /**
     * @deprecated since 5.5.0. Use Shop::Container()->getShippingService()->getCustomShippingCostsByProduct() instead.
     */
    public static function gibArtikelabhaengigeVersandkosten(
        string $country,
        Artikel $product,
        float|int $amount,
        bool $checkDeliveryAddress = true
    ): false|stdClass {
        \trigger_error(
            __METHOD__ . ' is deprecated and should not be used anymore.',
            \E_USER_DEPRECATED
        );

        $taxRate    = null;
        $hookReturn = false;
        \executeHook(\HOOK_TOOLS_GLOBAL_GIBARTIKELABHAENGIGEVERSANDKOSTEN, [
            'oArtikel'    => &$product,
            'cLand'       => &$country,
            'nAnzahl'     => &$amount,
            'bHookReturn' => &$hookReturn
        ]);
        if ($hookReturn) {
            return false;
        }
        $netPricesActive = Frontend::getCustomerGroup()->isMerchant();
        $currency        = Frontend::getCurrency();
        // Steuersatz nur benötigt, wenn Nettokunde
        if ($netPricesActive === true && ($sClassID = Frontend::getCart()->gibVersandkostenSteuerklasse()) > 0) {
            $taxRate = Shop::Container()->getDB()->select(
                'tsteuersatz',
                'kSteuerklasse',
                $sClassID
            )->fSteuersatz ?? null;
        }
        $plusVat = Shop::Lang()->get('plus', 'productDetails') . ' ' . Shop::Lang()->get('vat', 'productDetails');
        // gestaffelte
        if (!empty($product->FunktionsAttribute[\FKT_ATTRIBUT_VERSANDKOSTEN_GESTAFFELT])) {
            $shippingData = \array_filter(
                \explode(
                    ';',
                    $product->FunktionsAttribute[\FKT_ATTRIBUT_VERSANDKOSTEN_GESTAFFELT]
                )
            );
            foreach ($shippingData as $shipping) {
                // DE 1-45,00:2-60,00:3-80;AT 1-90,00:2-120,00:3-150,00
                $data = \explode(' ', $shipping);
                if (\count($data) < 2) {
                    continue;
                }
                [$countries, $costs] = $data;
                if ($countries && ($country === $countries || $checkDeliveryAddress === false)) {
                    foreach (\explode(':', $costs) as $staffel) {
                        [$limit, $price] = \explode('-', $staffel);

                        $price = (float)\str_replace(',', '.', $price);
                        if ($price >= 0 && $limit > 0 && $amount <= $limit) {
                            $item        = new stdClass();
                            $item->cName = [];
                            foreach (Frontend::getLanguages() as $language) {
                                $item->cName[$language->getCode()] = Shop::Lang()->get('shippingFor', 'checkout')
                                    . ' ' . $product->cName . ' (' . $countries . ')';
                            }
                            $item->fKosten = $price;
                            if ($netPricesActive === true) {
                                $loc = Preise::getLocalizedPriceString(
                                    Tax::getNet($item->fKosten, $taxRate),
                                    $currency
                                );

                                $item->cPreisLocalized = $loc . ' ' . $plusVat;
                            } else {
                                $item->cPreisLocalized = Preise::getLocalizedPriceString($item->fKosten, $currency);
                            }

                            return $item;
                        }
                    }
                }
            }
        }
        if (empty($product->FunktionsAttribute[\FKT_ATTRIBUT_VERSANDKOSTEN])) {
            return false;
        }
        $shippingData = \array_filter(\explode(';', \trim($product->FunktionsAttribute[\FKT_ATTRIBUT_VERSANDKOSTEN])));
        foreach ($shippingData as $shipping) {
            $data = \explode(' ', $shipping);
            if (\count($data) < 2) {
                continue;
            }
            [$countries, $shippingCosts] = $data;
            if ($countries && ($country === $countries || $checkDeliveryAddress === false)) {
                $item = new stdClass();
                // posname lokalisiert ablegen
                $item->cName = [];
                foreach (Frontend::getLanguages() as $language) {
                    $item->cName[$language->getCode()] = Shop::Lang()->get('shippingFor', 'checkout')
                        . ' ' . $product->cName . ' (' . $countries . ')';
                }
                $item->fKosten = (float)\str_replace(',', '.', $shippingCosts) * $amount;
                if ($netPricesActive === true) {
                    $loc = Preise::getLocalizedPriceString(
                        Tax::getNet($item->fKosten, $taxRate),
                        $currency
                    );

                    $item->cPreisLocalized = $loc . ' ' . $plusVat;
                } else {
                    $item->cPreisLocalized = Preise::getLocalizedPriceString($item->fKosten, $currency);
                }

                return $item;
            }
        }

        return false;
    }

    /**
     * @param CartItem[] $items
     * @return stdClass[]
     * @deprecated since 5.5.0. Use Shop::Container()->getShippingService()->getCustomShippingCostsByCart() instead.
     */
    public static function gibArtikelabhaengigeVersandkostenImWK(
        string $country,
        array $items,
        bool $checkDelivery = true
    ): array {
        \trigger_error(
            __METHOD__ . ' is deprecated and should not be used anymore.',
            \E_USER_DEPRECATED
        );

        $shippingItems = [];
        $items         = \array_filter($items, static function ($item): bool {
            return (int)$item->nPosTyp === \C_WARENKORBPOS_TYP_ARTIKEL && \is_object($item->Artikel);
        });
        foreach ($items as $item) {
            $shippingItem = self::gibArtikelabhaengigeVersandkosten(
                $country,
                $item->Artikel,
                $item->nAnzahl,
                $checkDelivery
            );
            if (!empty($shippingItem->cName)) {
                $shippingItem->kArtikel = (int)$item->Artikel->kArtikel;
                $shippingItems[]        = $shippingItem;
            }
        }

        return $shippingItems;
    }

    /**
     * @deprecated since 5.5.0. Use Shop::Container()->getShippingService()->getShippingClasses() instead.
     */
    public static function getShippingClasses(Cart $cart): string
    {
        \trigger_error(
            __METHOD__ . ' is deprecated and should not be used anymore.',
            \E_USER_DEPRECATED
        );

        $classes = [];
        foreach ($cart->PositionenArr as $item) {
            if (
                (int)$item->nPosTyp === \C_WARENKORBPOS_TYP_ARTIKEL
                && $item->kVersandklasse > 0
                && !\in_array($item->kVersandklasse, $classes, true)
            ) {
                $classes[] = $item->kVersandklasse;
            }
        }
        \sort($classes);

        return \implode('-', $classes);
    }

    /**
     * @former gibVersandZuschlag()
     * @deprecated since 5.5.0. Use Shop::Container()->getShippingService()->getShippingSurcharge() instead.
     */
    public static function getAdditionalFees(Versandart|stdClass $shippingMethod, string $iso, string $zip): ?stdClass
    {
        \trigger_error(
            __METHOD__ . ' is deprecated and should not be used anymore.',
            \E_USER_DEPRECATED
        );

        $shippingMethodData = new Versandart((int)$shippingMethod->kVersandart);
        if (($surcharge = $shippingMethodData->getShippingSurchargeForZip($zip, $iso)) !== null) {
            return (object)[
                'kVersandzuschlag' => $surcharge->getID(),
                'kVersandart'      => $surcharge->getShippingMethod(),
                'cIso'             => $surcharge->getISO(),
                'cName'            => $surcharge->getTitle(),
                'fZuschlag'        => $surcharge->getSurcharge(),
                'cPreisLocalized'  => $surcharge->getPriceLocalized(),
                'angezeigterName'  => $surcharge->getNames()
            ];
        }

        return null;
    }

    /**
     * @former berechneVersandpreis()
     * @deprecated since 5.5.0. Use Shop::Container()->getShippingService()->calcCostForShippingMethod() instead.
     */
    public static function calculateShippingFees(
        Versandart|stdClass $shippingMethod,
        string $iso,
        Artikel|stdClass|null $additionalProduct,
        Artikel|stdClass|null $product = null
    ): int|float {
        \trigger_error(
            __METHOD__ . ' is deprecated and should not be used anymore.',
            \E_USER_DEPRECATED
        );

        $db                            = Shop::Container()->getDB();
        $excludeShippingCostAttributes = self::normalerArtikelversand($iso) === true;
        if (!isset($additionalProduct->fAnzahl)) {
            if ($additionalProduct === null) {
                $additionalProduct = new stdClass();
            }
            $additionalProduct->fAnzahl         = 0;
            $additionalProduct->fWarenwertNetto = 0;
            $additionalProduct->fGewicht        = 0;
        }
        $calculation = $db->select(
            'tversandberechnung',
            'kVersandberechnung',
            $shippingMethod->kVersandberechnung
        );
        $price       = 0;
        switch ($calculation->cModulId ?? '') {
            case 'vm_versandkosten_pauschale_jtl':
                $price = (float)$shippingMethod->fPreis;
                break;

            case 'vm_versandberechnung_gewicht_jtl':
                $totalWeight = $product->fGewicht
                    ?? Frontend::getCart()->getWeight($excludeShippingCostAttributes, $iso);
                $totalWeight += $additionalProduct->fGewicht;
                $shipping    = $db->getSingleObject(
                    'SELECT *
                        FROM tversandartstaffel
                        WHERE kVersandart = :sid
                            AND fBis >= :wght
                        ORDER BY fBis ASC',
                    ['sid' => (int)$shippingMethod->kVersandart, 'wght' => $totalWeight]
                );
                if ($shipping !== null) {
                    $price = (float)$shipping->fPreis;
                } else {
                    return -1;
                }
                break;

            case 'vm_versandberechnung_warenwert_jtl':
                $total    = $product && $product->Preise !== null
                    ? $product->Preise->fVKNetto
                    : Frontend::getCart()->gibGesamtsummeWarenExt(
                        [\C_WARENKORBPOS_TYP_ARTIKEL],
                        true,
                        $excludeShippingCostAttributes,
                        $iso
                    );
                $total    += $additionalProduct->fWarenwertNetto;
                $shipping = $db->getSingleObject(
                    'SELECT *
                        FROM tversandartstaffel
                        WHERE kVersandart = :sid
                            AND fBis >= :val
                        ORDER BY fBis ASC',
                    ['sid' => (int)$shippingMethod->kVersandart, 'val' => $total]
                );
                if (isset($shipping->kVersandartStaffel)) {
                    $price = (float)$shipping->fPreis;
                } else {
                    return -1;
                }
                break;

            case 'vm_versandberechnung_artikelanzahl_jtl':
                $productCount = 1;
                if (!$product) {
                    $productCount = isset($_SESSION['Warenkorb'])
                        ? Frontend::getCart()->gibAnzahlArtikelExt(
                            [\C_WARENKORBPOS_TYP_ARTIKEL],
                            $excludeShippingCostAttributes,
                            $iso
                        )
                        : 0;
                }
                $productCount += $additionalProduct->fAnzahl;
                $shipping     = $db->getSingleObject(
                    'SELECT *
                        FROM tversandartstaffel
                        WHERE kVersandart = :sid
                            AND fBis >= :cnt
                        ORDER BY fBis ASC',
                    ['sid' => (int)$shippingMethod->kVersandart, 'cnt' => $productCount]
                );
                if (isset($shipping->kVersandartStaffel)) {
                    $price = (float)$shipping->fPreis;
                } else {
                    return -1;
                }
                break;

            default:
                // bearbeite fremdmodule
                break;
        }
        \executeHook(\HOOK_CALCULATESHIPPINGFEES, [
            'price'             => &$price,
            'shippingMethod'    => $shippingMethod,
            'iso'               => $iso,
            'additionalProduct' => $additionalProduct,
            'product'           => $product,
        ]);
        if (
            $shippingMethod->cNurAbhaengigeVersandart === 'Y'
            && (!empty($product->FunktionsAttribute[\FKT_ATTRIBUT_VERSANDKOSTEN])
                || !empty($product->FunktionsAttribute[\FKT_ATTRIBUT_VERSANDKOSTEN_GESTAFFELT]))
        ) {
            $productSpecific = self::gibArtikelabhaengigeVersandkosten($iso, $product, 1);
            $price           += (float)($productSpecific->fKosten ?? 0);
        }
        if ($price >= $shippingMethod->fDeckelung && $shippingMethod->fDeckelung > 0) {
            $price = (float)$shippingMethod->fDeckelung;
        }
        if (isset($shippingMethod->Zuschlag->fZuschlag) && (int)$shippingMethod->Zuschlag->fZuschlag !== 0) {
            $price += (float)$shippingMethod->Zuschlag->fZuschlag;
        }
        $productPrice         = 0;
        $totalForShippingFree = 0;
        if ($shippingMethod->eSteuer === 'netto') {
            if ($product && $product->Preise !== null) {
                $productPrice = $product->Preise->fVKNetto;
            }
            if (isset($_SESSION['Warenkorb'])) {
                $totalForShippingFree = Tax::getNet(
                    Frontend::getCart()->gibGesamtsummeWarenExt([\C_WARENKORBPOS_TYP_ARTIKEL], true, true, $iso),
                    Tax::getSalesTax(Frontend::getCart()->gibVersandkostenSteuerklasse())
                );
            }
        } elseif ($shippingMethod->eSteuer === 'brutto') {
            if ($product && $product->Preise !== null && $product->kSteuerklasse !== null) {
                $productPrice = Tax::getGross(
                    $product->Preise->fVKNetto,
                    Tax::getSalesTax($product->kSteuerklasse)
                );
            }
            if (isset($_SESSION['Warenkorb'])) {
                $totalForShippingFree = Frontend::getCart()->gibGesamtsummeWarenExt(
                    [\C_WARENKORBPOS_TYP_ARTIKEL],
                    true,
                    true,
                    $iso
                );
            }
        }

        if (
            $shippingMethod->fVersandkostenfreiAbX > 0
            && (($product && $productPrice >= $shippingMethod->fVersandkostenfreiAbX)
                || ($totalForShippingFree >= $shippingMethod->fVersandkostenfreiAbX))
        ) {
            $price = 0;
        }
        \executeHook(\HOOK_TOOLSGLOBAL_INC_BERECHNEVERSANDPREIS, [
            'fPreis'         => &$price,
            'versandart'     => $shippingMethod,
            'cISO'           => $iso,
            'oZusatzArtikel' => $additionalProduct,
            'Artikel'        => $product,
        ]);

        return $price;
    }

    /**
     * calculate shipping costs for exports
     * @former gibGuenstigsteVersandkosten()
     * @deprecated since 5.5.0. Use Shop::Container()->getShippingService()->getLowestShippingFeesForProduct() instead.
     */
    public static function getLowestShippingFees(
        string $iso,
        Artikel $product,
        bool|int $allowCash,
        int $customerGroupID
    ): float|int {
        \trigger_error(
            __METHOD__ . ' is deprecated and should not be used anymore.',
            \E_USER_DEPRECATED
        );

        $fee                    = 99999;
        $db                     = Shop::Container()->getDB();
        $hasProductShippingCost = $product->isUsedForShippingCostCalculation($iso) ? 'N' : 'Y';
        $dep                    = " AND cNurAbhaengigeVersandart = '" . $hasProductShippingCost . "' ";

        $methods = $db->getObjects(
            "SELECT *
                FROM tversandart
                WHERE cIgnoreShippingProposal != 'Y'
                    AND cLaender LIKE :iso
                    AND (cVersandklassen = '-1'
                        OR cVersandklassen RLIKE :scls)
                    AND (cKundengruppen = '-1'
                        OR FIND_IN_SET(:cgid, REPLACE(cKundengruppen, ';', ',')) > 0)" . $dep,
            [
                'iso'  => '%' . $iso . '%',
                'scls' => '^([0-9 -]* )?' . $product->kVersandklasse . ' ',
                'cgid' => $customerGroupID
            ]
        );
        foreach ($methods as $method) {
            $method->kVersandart        = (int)$method->kVersandart;
            $method->kVersandberechnung = (int)$method->kVersandberechnung;
            $method->nSort              = (int)$method->nSort;
            $method->nMinLiefertage     = (int)$method->nMinLiefertage;
            $method->nMaxLiefertage     = (int)$method->nMaxLiefertage;
            if (!$allowCash) {
                $cash = $db->select(
                    'tversandartzahlungsart',
                    'kZahlungsart',
                    6,
                    'kVersandart',
                    $method->kVersandart
                );
                if ($cash !== null && isset($cash->kVersandartZahlungsart) && $cash->kVersandartZahlungsart > 0) {
                    continue;
                }
            }
            $vp = self::calculateShippingFees($method, $iso, null, $product);
            if ($vp !== -1 && $vp < $fee) {
                $fee = $vp;
            }
            if ($vp === 0) {
                break;
            }
        }

        return $fee === 99999 ? -1 : $fee;
    }

    /**
     * @deprecated since 5.5.0. Use Shop::Container()->getShippingService()->getDeliverytimeEstimationText() instead.
     */
    public static function getDeliverytimeEstimationText(int $minDeliveryDays, int $maxDeliveryDays): string
    {
        \trigger_error(
            __METHOD__ . ' is deprecated and should not be used anymore.',
            \E_USER_DEPRECATED
        );

        switch (true) {
            case ($maxDeliveryDays < \DELIVERY_TIME_DAYS_TO_WEEKS_LIMIT):
                $minDelivery = $minDeliveryDays;
                $maxDelivery = $maxDeliveryDays;
                $languageVar = $minDeliveryDays === $maxDeliveryDays
                    ? 'deliverytimeEstimationSimple'
                    : 'deliverytimeEstimation';
                break;
            case ($maxDeliveryDays < \DELIVERY_TIME_DAYS_TO_MONTHS_LIMIT):
                $minDelivery = (int)\ceil($minDeliveryDays / \DELIVERY_TIME_DAYS_PER_WEEK);
                $maxDelivery = (int)\ceil($maxDeliveryDays / \DELIVERY_TIME_DAYS_PER_WEEK);
                $languageVar = $minDelivery === $maxDelivery
                    ? 'deliverytimeEstimationSimpleWeeks'
                    : 'deliverytimeEstimationWeeks';
                break;
            default:
                $minDelivery = (int)\ceil($minDeliveryDays / \DELIVERY_TIME_DAYS_PER_MONTH);
                $maxDelivery = (int)\ceil($maxDeliveryDays / \DELIVERY_TIME_DAYS_PER_MONTH);
                $languageVar = $minDelivery === $maxDelivery
                    ? 'deliverytimeEstimationSimpleMonths'
                    : 'deliverytimeEstimationMonths';
        }

        $deliveryText = self::getDeliveryText($minDelivery, $maxDelivery, $languageVar);

        \executeHook(\HOOK_GET_DELIVERY_TIME_ESTIMATION_TEXT, [
            'min'  => $minDeliveryDays,
            'max'  => $maxDeliveryDays,
            'text' => &$deliveryText
        ]);

        return $deliveryText;
    }

    /**
     * @former baueVersandkostenfreiString()
     * @deprecated since 5.5.0. Use Shop::Container()->getShippingService()->getShippingFreeString() instead.
     */
    public static function getShippingFreeString(
        mixed $method,
        float|int $cartSumGros,
        float|int $cartSumNet = 0
    ): string {
        \trigger_error(
            __METHOD__ . ' is deprecated and should not be used anymore.',
            \E_USER_DEPRECATED
        );

        if (isset($_SESSION['oVersandfreiKupon'])) {
            return '';
        }
        if (
            !\is_object($method)
            || (float)$method->fVersandkostenfreiAbX <= 0
            || !isset($_SESSION['Warenkorb'], $_SESSION['Steuerland'])
        ) {
            return '';
        }

        if (isset($method->cNameLocalized)) {
            $name = $method->cNameLocalized;
        } else {
            $localized = Shop::Container()->getDB()->select(
                'tversandartsprache',
                'kVersandart',
                $method->kVersandart,
                'cISOSprache',
                Shop::getLanguageCode()
            );
            $name      = $localized !== null && !empty($localized->cName)
                ? $localized->cName
                : $method->cName;
        }
        $shippingFreeDifference = self::getShippingFreeDifference($method, $cartSumGros, $cartSumNet);
        if ($shippingFreeDifference <= 0) {
            return \sprintf(
                Shop::Lang()->get('noShippingCostsReached', 'basket'),
                $name,
                self::getShippingFreeCountriesString($method)
            );
        }

        return \sprintf(
            Shop::Lang()->get('noShippingCostsAt', 'basket'),
            Preise::getLocalizedPriceString($shippingFreeDifference),
            $name,
            self::getShippingFreeCountriesString($method)
        );
    }

    /**
     * @deprecated since 5.5.0. Use Shop::Container()->getShippingService()->getShippingFreeDifference() instead.
     */
    public static function getShippingFreeDifference(
        Versandart|stdClass $method,
        float|int $cartSumGros,
        float|int $cartSumNet = 0
    ): float {
        \trigger_error(
            __METHOD__ . ' is deprecated and should not be used anymore.',
            \E_USER_DEPRECATED
        );

        if ($cartSumNet === 0) {
            $cartSumNet = $cartSumGros;
        }
        // check if vkfreiabx is calculated net or gros
        if ($method->eSteuer === 'netto') {
            $shippingFreeDifference = (float)$method->fVersandkostenfreiAbX - (float)$cartSumNet;
        } else {
            $shippingFreeDifference = (float)$method->fVersandkostenfreiAbX - (float)$cartSumGros;
        }

        return $shippingFreeDifference;
    }

    /**
     * @param Versandart|stdClass|mixed $shippingMethod
     * @former baueVersandkostenfreiLaenderString()
     * @deprecated since 5.5.0. Use Shop::Container()->getShippingService()->getShippingFreeCountriesString() instead.
     */
    public static function getShippingFreeCountriesString(mixed $shippingMethod): string
    {
        \trigger_error(
            __METHOD__ . ' is deprecated and should not be used anymore.',
            \E_USER_DEPRECATED
        );

        if (!\is_object($shippingMethod) || (float)$shippingMethod->fVersandkostenfreiAbX <= 0) {
            return '';
        }
        $langID  = Shop::getLanguageID();
        $cacheID = 'bvkfls_' . $shippingMethod->fVersandkostenfreiAbX . \mb_strlen($shippingMethod->cLaender)
            . '_' . $langID;
        /** @var string|false $shippingFreeCountries */
        $shippingFreeCountries = Shop::Container()->getCache()->get($cacheID);
        if ($shippingFreeCountries === false) {
            $shippingFreeCountries = \implode(
                ', ',
                \array_map(
                    static fn(Country $e): string => $e->getName($langID),
                    Shop::Container()->getCountryService()->getFilteredCountryList(
                        \array_filter(\explode(' ', $shippingMethod->cLaender))
                    )->toArray()
                )
            );

            Shop::Container()->getCache()->set($cacheID, $shippingFreeCountries, [\CACHING_GROUP_OPTION]);
        }

        return $shippingFreeCountries;
    }

    /**
     * @former gibVersandkostenfreiAb()
     * @deprecated since 5.5.0. Use Shop::Container()->getShippingService()->getFreeShippingMethod() instead.
     */
    public static function getFreeShippingMinimum(int $customerGroupID, string $country = ''): int|stdClass
    {
        \trigger_error(
            __METHOD__ . ' is deprecated and should not be used anymore.',
            \E_USER_DEPRECATED
        );

        $cache           = Shop::Container()->getCache();
        $db              = Shop::Container()->getDB();
        $shippingClasses = self::getShippingClasses(Frontend::getCart());
        $defaultShipping = self::normalerArtikelversand($country);
        $cacheID         = 'vkfrei_' . $customerGroupID
            . '_' . $country
            . '_' . $shippingClasses
            . '_' . Shop::getLanguageCode();
        if (($shippingMethod = $cache->get($cacheID)) === false) {
            $iso = 'DE';
            if (\mb_strlen($country) > 0) {
                $iso = $country;
            } else {
                $company = new Firma(true, $db, $cache);
                if ($company->country !== null) {
                    $iso = $company->country->getISO();
                }
            }
            $shippingMethods = map(
                self::getPossibleShippingMethods(
                    $iso,
                    $_SESSION['Lieferadresse']->cPLZ ?? Frontend::getCustomer()->cPLZ,
                    $shippingClasses,
                    $customerGroupID
                ),
                static fn(stdClass $e): int => $e->kVersandart
            );
            if (\count($shippingMethods) === 0) {
                $cache->set($cacheID, null, [\CACHING_GROUP_OPTION]);

                return 0;
            }

            $productSpecificCondition = empty($defaultShipping) ? '' : " AND cNurAbhaengigeVersandart = 'N' ";
            $shippingMethod           = $db->getSingleObject(
                "SELECT tversandart.*, tversandartsprache.cName AS cNameLocalized
                    FROM tversandart
                    LEFT JOIN tversandartsprache
                        ON tversandart.kVersandart = tversandartsprache.kVersandart
                        AND tversandartsprache.cISOSprache = :cLangID
                    WHERE fVersandkostenfreiAbX > 0
                        AND (cVersandklassen = '-1'
                            OR cVersandklassen RLIKE :cShippingClass)
                        AND tversandart.kVersandart IN (" . \implode(', ', $shippingMethods) . ")
                        AND (cKundengruppen = '-1'
                            OR FIND_IN_SET(:cGroupID, REPLACE(cKundengruppen, ';', ',')) > 0)
                            AND cLaender LIKE :ccode " . $productSpecificCondition . '
                    ORDER BY tversandart.fVersandkostenfreiAbX, tversandart.nSort ASC
                    LIMIT 1',
                [
                    'cLangID'        => Shop::getLanguageCode(),
                    'cShippingClass' => '^([0-9 -]* )?' . $shippingClasses . ' ',
                    'cGroupID'       => $customerGroupID,
                    'ccode'          => '%' . $iso . '%'
                ]
            );
            if ($shippingMethod !== null) {
                $shippingMethod->kVersandart        = (int)$shippingMethod->kVersandart;
                $shippingMethod->kVersandberechnung = (int)$shippingMethod->kVersandberechnung;
                $shippingMethod->nSort              = (int)$shippingMethod->nSort;
                $shippingMethod->nMinLiefertage     = (int)$shippingMethod->nMinLiefertage;
                $shippingMethod->nMaxLiefertage     = (int)$shippingMethod->nMaxLiefertage;
            }
            $cache->set($cacheID, $shippingMethod, [\CACHING_GROUP_OPTION]);
        }

        return $shippingMethod !== null && $shippingMethod->fVersandkostenfreiAbX > 0
            ? $shippingMethod
            : 0;
    }

    /**
     * @param string[] $filterISO
     * @return stdClass[]
     * @former gibBelieferbareLaender()
     * @since 5.0.0
     * @deprecated since 5.5.0. Use Shop::Container()->getShippingService()->getPossibleShippingCountries() instead.
     */
    public static function getPossibleShippingCountries(
        int $customerGroupID = 0,
        bool $ignoreConf = false,
        bool $force = false,
        array $filterISO = []
    ): array {
        \trigger_error(
            __METHOD__ . ' is deprecated and should not be used anymore.',
            \E_USER_DEPRECATED
        );

        if (empty($customerGroupID)) {
            $customerGroupID = CustomerGroup::getDefaultGroupID();
        }
        $countryHelper = Shop::Container()->getCountryService();
        if (
            !$force && ($ignoreConf
                || Shop::getSettingValue(\CONF_KUNDEN, 'kundenregistrierung_nur_lieferlaender') === 'Y')
        ) {
            $prep = ['cgid' => $customerGroupID];
            $cond = '';
            if (\count($filterISO) > 0) {
                $items = [];
                $i     = 0;
                foreach ($filterISO as $item) {
                    $idx        = 'i' . $i++;
                    $items[]    = ':' . $idx;
                    $prep[$idx] = $item;
                }
                $cond = 'AND tland.cISO IN (' . \implode(',', $items) . ')';
            }

            $countryISOFilter = Shop::Container()->getDB()->getObjects(
                "SELECT DISTINCT tland.cISO
                    FROM tland
                    INNER JOIN tversandart ON FIND_IN_SET(tland.cISO, REPLACE(tversandart.cLaender, ' ', ','))
                    WHERE (tversandart.cKundengruppen = '-1'
                        OR FIND_IN_SET(:cgid, REPLACE(cKundengruppen, ';', ',')) > 0)" . $cond,
                $prep
            );
            $countries        = $countryHelper->getFilteredCountryList(
                map($countryISOFilter, fn(stdClass $country): string => $country->cISO)
            )->toArray();
        } else {
            $countries = $countryHelper->getFilteredCountryList($filterISO, true)->toArray();
        }
        \executeHook(\HOOK_TOOLSGLOBAL_INC_GIBBELIEFERBARELAENDER, [
            'oLaender_arr' => &$countries
        ]);

        return $countries;
    }

    /**
     * @return stdClass[]
     * @former gibMoeglicheVerpackungen()
     * @since 5.0.0
     * @deprecated since 5.5.0. Use Shop::Container()->getShippingService()->getPossiblePackagings() instead.
     */
    public static function getPossiblePackagings(int $customerGroupID): array
    {
        \trigger_error(
            __METHOD__ . ' is deprecated and should not be used anymore.',
            \E_USER_DEPRECATED
        );

        $cartSum    = Frontend::getCart()->gibGesamtsummeWarenExt([\C_WARENKORBPOS_TYP_ARTIKEL], true);
        $packagings = Shop::Container()->getDB()->getObjects(
            "SELECT * FROM tverpackung
                JOIN tverpackungsprache
                    ON tverpackung.kVerpackung = tverpackungsprache.kVerpackung
                WHERE tverpackungsprache.cISOSprache = :lcode
                AND (tverpackung.cKundengruppe = '-1'
                    OR FIND_IN_SET(:cid, REPLACE(tverpackung.cKundengruppe, ';', ',')) > 0)
                AND :csum >= tverpackung.fMindestbestellwert
                AND tverpackung.nAktiv = 1
                ORDER BY tverpackung.kVerpackung",
            [
                'lcode' => Shop::getLanguageCode(),
                'cid'   => $customerGroupID,
                'csum'  => $cartSum
            ]
        );
        $currency   = Frontend::getCurrency();
        foreach ($packagings as $packaging) {
            $packaging->nKostenfrei        = ($cartSum >= $packaging->fKostenfrei
                && $packaging->fBrutto > 0
                && (float)$packaging->fKostenfrei !== 0.0)
                ? 1
                : 0;
            $packaging->fBruttoLocalized   = Preise::getLocalizedPriceString($packaging->fBrutto, $currency);
            $packaging->kVerpackung        = (int)$packaging->kVerpackung;
            $packaging->kSteuerklasse      = (int)$packaging->kSteuerklasse;
            $packaging->nAktiv             = (int)$packaging->nAktiv;
            $packaging->kVerpackungSprache = (int)$packaging->kVerpackungSprache;
        }

        return $packagings;
    }

    /**
     * @param object[]|null $shippingMethods
     * @param int           $paymentMethodID
     * @return stdClass|null
     * @deprecated since 5.5.0. Use Shop::Container()->getShippingService()->getFirstShippingMethod() instead.
     */
    public static function getFirstShippingMethod(?array $shippingMethods = null, int $paymentMethodID = 0): ?object
    {
        \trigger_error(
            __METHOD__ . ' is deprecated and should not be used anymore.',
            \E_USER_DEPRECATED
        );

        $customer = Frontend::getCustomer();
        if (!\is_array($shippingMethods)) {
            $country = $_SESSION['Lieferadresse']->cLand ?? $customer->cLand;
            $zip     = $_SESSION['Lieferadresse']->cPLZ ?? $customer->cPLZ;

            $shippingMethods = self::getPossibleShippingMethods(
                $country,
                $zip,
                self::getShippingClasses(Frontend::getCart()),
                $customer->getGroupID()
            );
        }
        if ($paymentMethodID === 0) {
            $paymentMethodID = (int)($_SESSION['Zahlungsart']->kZahlungsart ?? '0');
        }

        if ($paymentMethodID > 0) {
            /** @var stdClass[] $shippingMethods */
            $shippingMethods = \array_filter(
                $shippingMethods,
                static function (stdClass $method) use ($paymentMethodID, $customer) {
                    $paymentMethods = self::getPaymentMethods(
                        (int)$method->kVersandart,
                        $customer->getGroupID(),
                        $paymentMethodID
                    );

                    return \count($paymentMethods) > 0;
                }
            );
        }

        return first($shippingMethods);
    }
}
