<?php

declare(strict_types=1);

namespace JTL\Cart;

use Exception;
use JTL\Catalog\Currency;
use JTL\Catalog\Product\Artikel;
use JTL\Catalog\Product\EigenschaftWert;
use JTL\Catalog\Product\Preise;
use JTL\Checkout\Eigenschaft;
use JTL\Checkout\Kupon;
use JTL\Checkout\Versandart;
use JTL\Customer\Customer;
use JTL\Extensions\Config\Item;
use JTL\Extensions\Config\ItemLocalization;
use JTL\Extensions\Download\Download;
use JTL\Helpers\Form;
use JTL\Helpers\Order;
use JTL\Helpers\Request;
use JTL\Helpers\Tax;
use JTL\Language\Texts;
use JTL\Link\SpecialPageNotFoundException;
use JTL\Session\Frontend;
use JTL\Settings\Option\Checkout;
use JTL\Settings\Settings;
use JTL\Shipping\Services\ShippingService;
use JTL\Shop;
use JTL\Traits\FloatingPointTrait;
use stdClass;

use function Functional\select;
use function Functional\some;

/**
 * Class Cart
 * @package JTL\Cart
 */
class Cart
{
    use FloatingPointTrait;

    public ?int $kWarenkorb = 0;

    public ?int $kKunde = 0;

    public ?int $kLieferadresse = 0;

    public int $kZahlungsInfo = 0;

    /**
     * @var CartItem[]
     */
    public array $PositionenArr = [];

    public string $cEstimatedDelivery = '';

    public string $cChecksumme = '';

    public ?Currency $Waehrung = null;

    public ?Versandart $oFavourableShipping = null;

    public string $favourableShippingString = '';

    /**
     * @var object[]
     */
    public array $OrderAttributes = [];

    /**
     * @var stdClass[]
     */
    public static array $updatedPositions = [];

    /**
     * @var CartItem[]
     */
    public static array $deletedPositions = [];

    /**
     * @var array|null
     */
    private ?array $config = null;

    private ?ShippingService $shippingService = null;

    public function __wakeup(): void
    {
        $this->config          = null;
        $this->shippingService = null;
    }

    /**
     * @return string[]
     */
    public function __sleep(): array
    {
        return select(\array_keys(\get_object_vars($this)), static function (string $e): bool {
            return !\in_array(
                $e,
                [
                    'config',
                    'shippingService',
                ],
                true
            );
        });
    }

    public function __construct(int $id = 0)
    {
        if ($id > 0) {
            $this->loadFromDB($id);
        }
    }

    public function getShippingService(): ShippingService
    {
        if ($this->shippingService !== null) {
            return $this->shippingService;
        }
        $shippingService = Shop::Container()->getShippingService();
        $this->setShippingService($shippingService);

        return $shippingService;
    }

    protected function setShippingService(ShippingService $shippingService): void
    {
        $this->shippingService = $shippingService;
    }

    public function getConfiguration(string $section, string $offset, mixed $default = null): mixed
    {
        if ($this->config !== null) {
            return $this->config;
        }
        $config = Shop::getSettings([\CONF_GLOBAL, \CONF_KAUFABWICKLUNG]);
        $this->setConfiguration($config);

        return $config[$section][$offset] ?? $default;
    }

    protected function setConfiguration(array $config): void
    {
        $this->config = $config;
    }

    /**
     * @since 4.06.10
     * @param bool       $onlyStockRelevant
     * @param null|int[] $excludePos
     * @return float[]
     */
    public function getAllDependentAmount(bool $onlyStockRelevant = false, ?array $excludePos = null): array
    {
        $depAmount = [];

        foreach ($this->PositionenArr as $key => $cartItem) {
            if (\is_array($excludePos) && \in_array($key, $excludePos, true)) {
                continue;
            }

            if (
                !empty($cartItem->Artikel)
                && (!$onlyStockRelevant
                    || ($cartItem->Artikel->cLagerBeachten === 'Y' && $cartItem->Artikel->cLagerKleinerNull !== 'Y'))
            ) {
                $depProducts = $cartItem->Artikel->getAllDependentProducts($onlyStockRelevant);

                foreach ($depProducts as $productID => $item) {
                    if (isset($depAmount[$productID])) {
                        $depAmount[$productID] += ($cartItem->nAnzahl * $item->stockFactor);
                    } else {
                        $depAmount[$productID] = $cartItem->nAnzahl * $item->stockFactor;
                    }
                }
            }
        }

        return $depAmount;
    }

    /**
     * @since 4.06.10
     * @param null|int[] $excludePos
     */
    public function getDependentAmount(
        int $productID,
        bool $onlyStockRelevant = false,
        ?array $excludePos = null
    ): float {
        static $depAmount = null;

        if ($excludePos !== null) {
            $tmpAmount = $this->getAllDependentAmount($onlyStockRelevant, $excludePos);

            return $tmpAmount[$productID] ?? 0.0;
        }

        if (!isset($depAmount[$productID])) {
            $depAmount = $this->getAllDependentAmount($onlyStockRelevant);
        }

        return $depAmount[$productID] ?? 0.0;
    }

    public function getMaxAvailableAmount(int $item, float $amount): float
    {
        foreach ($this->PositionenArr[$item]->Artikel?->getAllDependentProducts(true) ?? [] as $dependent) {
            $depProduct = $dependent->product;
            $depAmount  = $this->getDependentAmount($depProduct->kArtikel, true, [$item]);
            $newAmount  = \floor(
                ($depProduct->fLagerbestand - $depAmount) / $depProduct->fPackeinheit / $dependent->stockFactor
            );
            if ($depProduct->fAbnahmeintervall > 0) {
                $newAmount -= \fmod($newAmount, (float)$depProduct->fAbnahmeintervall);
            }
            if ($newAmount < $amount) {
                $amount = $newAmount;
            }
        }

        return $amount;
    }

    /**
     * Entfernt Positionen, die in der Wawi zwischenzeitlich deaktiviert/geloescht wurden
     */
    public function loescheDeaktiviertePositionen(): self
    {
        foreach ($this->PositionenArr as $i => $item) {
            $item->nPosTyp = (int)$item->nPosTyp;
            $delete        = false;
            if (!empty($item->Artikel)) {
                if (
                    isset(
                        $item->Artikel->fLagerbestand,
                        $item->Artikel->cLagerBeachten,
                        $item->Artikel->cLagerKleinerNull,
                        $item->Artikel->cLagerVariation
                    )
                    && $item->Artikel->fLagerbestand <= 0
                    && $item->Artikel->cLagerBeachten === 'Y'
                    && $item->Artikel->cLagerKleinerNull !== 'Y'
                    && $item->Artikel->cLagerVariation !== 'Y'
                ) {
                    $delete = true;
                } elseif (
                    empty($item->kKonfigitem)
                    && $item->nPosTyp !== \C_WARENKORBPOS_TYP_GRATISGESCHENK
                    && !$item->Artikel->bHasKonfig
                    && isset($item->fPreis)
                    && $this->isZero((float)$item->fPreis)
                    && $this->getConfiguration(
                        'global',
                        'global_preis0',
                        ''
                    ) === 'N'
                ) {
                    $delete = true;
                } elseif (
                    !empty($item->Artikel->FunktionsAttribute[\FKT_ATTRIBUT_UNVERKAEUFLICH])
                    && $item->nPosTyp !== \C_WARENKORBPOS_TYP_GRATISGESCHENK
                ) {
                    $delete = true;
                } else {
                    $delete = Shop::Container()->getDB()->select(
                        'tartikel',
                        'kArtikel',
                        (int)$item->kArtikel
                    ) === null;
                }

                \executeHook(\HOOK_WARENKORB_CLASS_LOESCHEDEAKTIVIERTEPOS, [
                    'oPosition' => $item,
                    'delete'    => &$delete
                ]);
            }
            if ($delete) {
                self::addDeletedPosition($item);
                unset($this->PositionenArr[$i]);
            }
        }
        $this->PositionenArr = \array_merge($this->PositionenArr);

        return $this;
    }

    public static function addUpdatedPosition(stdClass $item): void
    {
        self::$updatedPositions[] = $item;
    }

    public static function addDeletedPosition(CartItem $item): void
    {
        self::$deletedPositions[] = $item;
    }

    /**
     * @param int                      $productID
     * @param float|int|numeric-string $qty
     * @param array<mixed>             $attributeValues
     * @param int                      $type
     * @param string|false             $unique
     * @param int                      $configItemID
     * @param bool                     $setzePositionsPreise
     * @param string                   $responsibility
     * @return $this
     */
    public function fuegeEin(
        int $productID,
        float|int|string $qty,
        array $attributeValues,
        int $type = \C_WARENKORBPOS_TYP_ARTIKEL,
        string|bool $unique = false,
        int $configItemID = 0,
        bool $setzePositionsPreise = true,
        string $responsibility = 'core'
    ): self {
        $iso           = Shop::getLanguageCode();
        $currentLangID = Shop::getLanguageID();
        // @todo: schaue, ob diese Pos nicht markiert werden muesste, wenn anzahl>lager gekauft wird
        // schaue, ob es nicht schon Positionen mit diesem Artikel gibt
        foreach ($this->PositionenArr as $item) {
            if (
                !(isset($item->Artikel->kArtikel)
                    && (int)$item->Artikel->kArtikel === $productID
                    && (int)$item->nPosTyp === $type
                    && !$item->cUnique)
            ) {
                continue;
            }
            $isNew = false;
            // hat diese Position schon einen EigenschaftWert ausgewaehlt
            // und ist das dieselbe Eigenschaft wie ausgewaehlt?
            if (!$unique) {
                foreach ($item->WarenkorbPosEigenschaftArr as $wke) {
                    foreach ($attributeValues as $aValue) {
                        // gleiche Eigenschaft suchen
                        if ($aValue->kEigenschaft !== $wke->kEigenschaft) {
                            continue;
                        }
                        // ist es ein Freifeld mit unterschiedlichem Inhalt
                        // oder eine Eigenschaft mit unterschiedlichem Wert?
                        if (
                            ($wke->kEigenschaftWert > 0
                                && $wke->kEigenschaftWert !== $aValue->kEigenschaftWert)
                            || (($wke->cTyp === 'FREIFELD' || $wke->cTyp === 'PFLICHT-FREIFELD')
                                && $wke->cEigenschaftWertName[$iso] !== $aValue->cFreifeldWert)
                        ) {
                            $isNew = true;
                            break;
                        }
                    }
                }
                if (!$isNew) {
                    $item->nZeitLetzteAenderung = \time();
                    $item->nAnzahl              += $qty;
                    if ($setzePositionsPreise === true) {
                        $this->setzePositionsPreise();
                    }
                    \executeHook(\HOOK_WARENKORB_CLASS_FUEGEEIN, [
                        'kArtikel'      => $productID,
                        'oPosition_arr' => &$this->PositionenArr,
                        'nAnzahl'       => &$qty,
                        'exists'        => true
                    ]);

                    return $this;
                }
            }
        }
        $db                    = Shop::Container()->getDB();
        $options               = Artikel::getDefaultOptions();
        $options->nStueckliste = 1;
        $options->nVariationen = 1;
        if ($configItemID > 0) {
            $options->nKeineSichtbarkeitBeachten = 1;
        }
        $cartItem          = new CartItem();
        $cartItem->Artikel = new Artikel($db);
        $cartItem->Artikel->fuelleArtikel($productID, $options, 0, $currentLangID);
        $cartItem->nAnzahl           = $qty;
        $cartItem->kArtikel          = $cartItem->Artikel->kArtikel;
        $cartItem->kVersandklasse    = (int)$cartItem->Artikel->kVersandklasse;
        $cartItem->kSteuerklasse     = (int)$cartItem->Artikel->kSteuerklasse;
        $cartItem->fPreisEinzelNetto = $cartItem->Artikel->gibPreis($cartItem->nAnzahl, [], 0, $unique);
        $cartItem->fPreis            = $cartItem->fPreisEinzelNetto;
        $cartItem->fMwSt             = Tax::getSalesTax($cartItem->kSteuerklasse);
        $cartItem->cArtNr            = (string)$cartItem->Artikel->cArtNr;
        $cartItem->nPosTyp           = $type;
        $cartItem->cEinheit          = $cartItem->Artikel->cEinheit;
        $cartItem->cUnique           = $unique;
        $cartItem->cResponsibility   = $responsibility;
        $cartItem->kKonfigitem       = $configItemID;
        $cartItem->setzeGesamtpreisLocalized();
        $cartItem->cName         = [];
        $cartItem->cLieferstatus = [];
        $cartItem->fVK           = $cartItem->Artikel->Preise?->fVK;
        foreach (Frontend::getLanguages() as $lang) {
            $code                           = $lang->getCode();
            $cartItem->cName[$code]         = $cartItem->Artikel->cName;
            $cartItem->cLieferstatus[$code] = $cartItem->Artikel->cLieferstatus;
            if ($lang->getId() === $currentLangID) {
                continue;
            }
            if ($lang->isDefault() === true) {
                $localized = $db->getSingleObject(
                    'SELECT cName
                        FROM tartikel
                        WHERE kArtikel = :pid',
                    ['pid' => $cartItem->kArtikel]
                );
            } else {
                $localized = $db->getSingleObject(
                    'SELECT cName
                        FROM tartikelsprache
                        WHERE kArtikel = :pid
                            AND kSprache = :lid',
                    ['pid' => $cartItem->kArtikel, 'lid' => $lang->getId()]
                );
            }
            $cartItem->cName[$code] = $localized->cName ?? $cartItem->Artikel->cName;
            if (($cartItem->Artikel->kLieferstatus ?? 0) > 0) {
                $stateLocalized = $db->select(
                    'tlieferstatus',
                    'kLieferstatus',
                    $cartItem->Artikel->kLieferstatus ?? 0,
                    'kSprache',
                    $lang->getId()
                );
                if ($stateLocalized !== null && !empty($stateLocalized->cName)) {
                    $cartItem->cLieferstatus[$code] = $stateLocalized->cName;
                }
            }
        }
        // Grundpreise bei Staffelpreisen
        if (isset($cartItem->Artikel->fVPEWert) && $cartItem->Artikel->fVPEWert > 0) {
            $nLast = 0;
            for ($j = 1; $j <= 5; $j++) {
                $cStaffel = 'nAnzahl' . $j;
                if (
                    isset($cartItem->Artikel->Preise->$cStaffel)
                    && $cartItem->Artikel->Preise->$cStaffel > 0
                    && $cartItem->Artikel->Preise->$cStaffel <= $cartItem->nAnzahl
                ) {
                    $nLast = $j;
                }
            }
            if ($nLast > 0) {
                $cStaffel = 'fPreis' . $nLast;
                $cartItem->Artikel->baueVPE($cartItem->Artikel->Preise->$cStaffel);
            } else {
                $cartItem->Artikel->baueVPE();
            }
        }
        $this->setzeKonfig($cartItem, false);
        foreach ($cartItem->Artikel->Variationen as $variation) {
            foreach ($attributeValues as $aValue) {
                $aValue->kEigenschaft = (int)$aValue->kEigenschaft;
                // gleiche Eigenschaft suchen
                if (!isset($aValue->cFreifeldWert)) {
                    $aValue->cFreifeldWert = '';
                }
                if ($aValue->kEigenschaft !== $variation->kEigenschaft) {
                    continue;
                }
                if ($variation->cTyp === 'FREIFELD' || $variation->cTyp === 'PFLICHT-FREIFELD') {
                    $cartItem->setzeVariationsWert($variation->kEigenschaft, 0, $aValue->cFreifeldWert);
                } elseif ($aValue->kEigenschaftWert > 0) {
                    $value = new EigenschaftWert((int)$aValue->kEigenschaftWert, $db);
                    $attr  = new Eigenschaft($value->kEigenschaft, $db);
                    // Varkombi Kind?
                    if ($cartItem->Artikel->kVaterArtikel > 0) {
                        if ($attr->kArtikel === $cartItem->Artikel->kVaterArtikel) {
                            $cartItem->setzeVariationsWert(
                                $value->kEigenschaft,
                                $value->kEigenschaftWert
                            );
                        }
                    } elseif ($attr->kArtikel === $cartItem->kArtikel) {
                        // Variationswert hat eigene Artikelnummer
                        // und der Artikel hat nur eine Dimension als Variation?
                        if (
                            isset($value->cArtNr)
                            && \count($cartItem->Artikel->Variationen) === 1
                            && \mb_strlen($value->cArtNr) > 0
                        ) {
                            $cartItem->cArtNr          = $value->cArtNr;
                            $cartItem->Artikel->cArtNr = $value->cArtNr;
                        }

                        $cartItem->setzeVariationsWert(
                            $value->kEigenschaft,
                            $value->kEigenschaftWert
                        );
                    }
                }
            }
        }

        $cartItem->fGesamtgewicht       = $cartItem->gibGesamtgewicht();
        $cartItem->nZeitLetzteAenderung = \time();

        switch ($cartItem->nPosTyp) {
            case \C_WARENKORBPOS_TYP_GRATISGESCHENK:
                $cartItem->fPreisEinzelNetto = 0;
                $cartItem->fPreis            = 0;
                $cartItem->setzeGesamtpreisLocalized();
                break;

            case \C_WARENKORBPOS_TYP_VERSANDPOS:
                if (
                    isset($_SESSION['Versandart']->angezeigterHinweistext[Shop::getLanguageCode()])
                    && \mb_strlen($_SESSION['Versandart']->angezeigterHinweistext[Shop::getLanguageCode()]) > 0
                ) {
                    $cartItem->cHinweis = $_SESSION['Versandart']->angezeigterHinweistext[Shop::getLanguageCode()];
                }
                break;

            case \C_WARENKORBPOS_TYP_ZAHLUNGSART:
                if (isset($_SESSION['Zahlungsart']->cHinweisText)) {
                    $cartItem->cHinweis = $_SESSION['Zahlungsart']->cHinweisText;
                }
                break;
        }
        unset($cartItem->Artikel->oKonfig_arr); //#7482
        $this->PositionenArr[] = $cartItem;
        if ($setzePositionsPreise === true) {
            $this->setzePositionsPreise();
        }
        $this->updateCouponValue();
        $this->sortShippingPosition();

        \executeHook(\HOOK_WARENKORB_CLASS_FUEGEEIN, [
            'kArtikel'      => $productID,
            'oPosition_arr' => &$this->PositionenArr,
            'nAnzahl'       => &$qty,
            'exists'        => false
        ]);

        return $this;
    }

    public function sortShippingPosition(): self
    {
        if (\count($this->PositionenArr) <= 1) {
            return $this;
        }
        $shippingItems   = [];
        $i               = 0;
        $allowedPosTypes = [
            \C_WARENKORBPOS_TYP_VERSANDPOS,
            \C_WARENKORBPOS_TYP_VERSANDZUSCHLAG,
            \C_WARENKORBPOS_TYP_VERSAND_ARTIKELABHAENGIG
        ];
        foreach ($this->PositionenArr as $item) {
            $item->nPosTyp = (int)$item->nPosTyp;
            if (\in_array($item->nPosTyp, $allowedPosTypes, true)) {
                $shippingItems[] = (object)['offset' => $i, 'item' => $item];
            }
            $i++;
        }
        foreach ($shippingItems as $shippingItem) {
            unset($this->PositionenArr[$shippingItem->offset]);
            $this->PositionenArr[] = $shippingItem->item;
        }
        $this->PositionenArr = \array_values($this->PositionenArr);

        return $this;
    }

    public function gibLetzteWarenkorbPostionindex(): int
    {
        return \count($this->PositionenArr) - 1;
    }

    public function loescheSpezialPos(int $type, bool $force = false): self
    {
        if (\count($this->PositionenArr) === 0) {
            return $this;
        }
        foreach ($this->PositionenArr as $i => $item) {
            $delete = true;
            \executeHook(\HOOK_CART_DELETE_SPECIAL_CART_ITEM, [
                'positionItem' => $item,
                'delete'       => &$delete
            ]);

            if (isset($item->nPosTyp) && (int)$item->nPosTyp === $type && $delete) {
                unset($this->PositionenArr[$i]);
            }
        }
        $this->PositionenArr = \array_merge($this->PositionenArr);
        if (($force || !empty($_POST['Kuponcode'])) && $type === \C_WARENKORBPOS_TYP_KUPON) {
            if (!empty($_SESSION['Kupon'])) {
                unset($_SESSION['Kupon']);
            } elseif (!empty($_SESSION['oVersandfreiKupon'])) {
                unset($_SESSION['oVersandfreiKupon']);
                if (!empty($_SESSION['VersandKupon'])) {
                    unset($_SESSION['VersandKupon']);
                }
            }
        }

        \executeHook(\HOOK_CART_DELETE_SPECIAL_CART_ITEM_END);

        return $this;
    }

    /**
     * @param object|string[]|string    $name
     * @param float|int|numeric-string  $qty
     * @param float|numeric-string|null $price
     */
    public function erstelleSpezialPos(
        object|array|string $name,
        float|int|string $qty,
        float|string|null $price,
        int $taxClassID,
        int $type,
        bool $delSamePosType = true,
        bool $grossPrice = true,
        string $message = '',
        false|string $unique = false,
        int $configItemID = 0,
        int $productID = 0,
        string $responsibility = 'core'
    ): self {
        if ($delSamePosType) {
            $this->loescheSpezialPos($type);
        }
        $cartItem                  = new CartItem();
        $cartItem->nAnzahl         = $qty;
        $cartItem->nAnzahlEinzel   = $qty;
        $cartItem->kSteuerklasse   = $taxClassID;
        $cartItem->fPreis          = $price;
        $cartItem->cUnique         = $unique;
        $cartItem->cResponsibility = $responsibility;
        $cartItem->kKonfigitem     = $configItemID;
        $cartItem->kArtikel        = $productID;
        $cartItem->fMwSt           = Tax::getSalesTax($cartItem->kSteuerklasse);
        if ($price !== null) {
            if (\is_object($_SESSION['Kundengruppe']) && Frontend::getCustomerGroup()->isMerchant()) {
                if ($grossPrice) {
                    $cartItem->fPreis = $price / (100 + Tax::getSalesTax($taxClassID)) * 100.0;
                }
                // round net price
                $cartItem->fPreis = \round((float)$cartItem->fPreis, 2);
            } elseif ($grossPrice) {
                // calculate net price based on rounded gross price
                $cartItem->fPreis = \round((float)$price, 2) / (100 + Tax::getSalesTax($taxClassID)) * 100.0;
            } else {
                // calculate rounded gross price then calculate net price again.
                $cartItem->fPreis = \round($price * (100 + Tax::getSalesTax($taxClassID)) / 100, 2) /
                    (100 + Tax::getSalesTax($taxClassID)) * 100.0;
            }
        }
        $cartItem->fPreisEinzelNetto = $cartItem->fPreis;
        if ($type === \C_WARENKORBPOS_TYP_KUPON && isset($name->cName)) {
            $cartItem->cName = \is_array($name->cName)
                ? $name->cName
                : [Shop::getLanguageCode() => $name->cName];
            if (isset($name->cArticleNameAffix, $name->discountForArticle)) {
                $cartItem->cArticleNameAffix  = $name->cArticleNameAffix;
                $cartItem->discountForArticle = $name->discountForArticle;
            }
        } else {
            $cartItem->cName = \is_array($name)
                ? $name
                : [Shop::getLanguageCode() => $name];
        }
        $cartItem->nPosTyp  = $type;
        $cartItem->cHinweis = $message;
        $offset             = \array_push($this->PositionenArr, $cartItem);
        $cartItem           = $this->PositionenArr[$offset - 1];
        foreach (Frontend::getCurrencies() as $currency) {
            $currencyName = $currency->getName();
            // Standardartikel
            $cartItem->cGesamtpreisLocalized[0][$currencyName] = Preise::getLocalizedPriceString(
                Tax::getGross(
                    $cartItem->fPreis * $cartItem->nAnzahl,
                    CartItem::getTaxRate($cartItem)
                ),
                $currency
            );
            $cartItem->cGesamtpreisLocalized[1][$currencyName] = Preise::getLocalizedPriceString(
                $cartItem->fPreis * $cartItem->nAnzahl,
                $currency
            );
            $cartItem->cEinzelpreisLocalized[0][$currencyName] = Preise::getLocalizedPriceString(
                Tax::getGross($cartItem->fPreis, CartItem::getTaxRate($cartItem)),
                $currency
            );
            $cartItem->cEinzelpreisLocalized[1][$currencyName] = Preise::getLocalizedPriceString(
                $cartItem->fPreis,
                $currency
            );
            if ($cartItem->kKonfigitem > 0 && \is_string($cartItem->cUnique) && !empty($cartItem->cUnique)) {
                $net       = 0;
                $gross     = 0;
                $parentIdx = null;

                foreach ($this->PositionenArr as $idx => $item) {
                    if ($cartItem->cUnique === $item->cUnique) {
                        $net   += $item->fPreis * $item->nAnzahl;
                        $gross += Tax::getGross(
                            $item->fPreis * $item->nAnzahl,
                            CartItem::getTaxRate($item)
                        );
                        if ($item->kKonfigitem === 0 && \is_string($item->cUnique) && !empty($item->cUnique)) {
                            $parentIdx = $idx;
                        }
                    }
                }

                if ($parentIdx !== null) {
                    $parent = $this->PositionenArr[$parentIdx];
                    if (\is_object($parent)) {
                        $cartItem->nAnzahlEinzel                         = $cartItem->nAnzahl / $parent->nAnzahl;
                        $parent->cKonfigpreisLocalized[0][$currencyName] = Preise::getLocalizedPriceString(
                            $gross,
                            $currency
                        );
                        $parent->cKonfigpreisLocalized[1][$currencyName] = Preise::getLocalizedPriceString(
                            $net,
                            $currency
                        );
                    }
                }
            }
        }
        $this->sortShippingPosition();

        \executeHook(\HOOK_WARENKORB_ERSTELLE_SPEZIAL_POS, [
            'productID'     => $productID,
            'positionItems' => &$this->PositionenArr,
            'qty'           => (float)$qty,
        ]);

        return $this;
    }

    /**
     * stellt fest, ob der Warenkorb alle Eingaben erhalten hat, um den Bestellvorgang durchzufuehren
     *
     * @return int
     * 10 - alles OK, Bestellung kann gemacht werden.
     * 1 - VersandArt fehlt.
     * 2 - Mindestens eine Variation eines Artikels wurde nicht ausgewaehlt
     * 3 - Warenkorb enthaelt keine Positionen
     */
    public function istBestellungMoeglich(): int
    {
        if (\count($this->PositionenArr) < 1) {
            return 3;
        }
        $mbw = Frontend::getCustomerGroup()->getAttribute(\KNDGRP_ATTRIBUT_MINDESTBESTELLWERT);
        if ($mbw > 0 && $this->gibGesamtsummeWarenOhne([\C_WARENKORBPOS_TYP_GUTSCHEIN], true) < $mbw) {
            return 9;
        }
        if (
            (!isset($_SESSION['bAnti_spam_already_checked']) || $_SESSION['bAnti_spam_already_checked'] !== true)
            && $this->getConfiguration(
                'kaufabwicklung',
                'bestellabschluss_spamschutz_nutzen',
                ''
            ) === 'Y'
            && ($ip = Request::getRealIP())
        ) {
            $cnt = Shop::Container()->getDB()->getSingleInt(
                'SELECT COUNT(*) AS cnt
                    FROM tbestellung
                    WHERE cIP = :ip
                        AND dErstellt > NOW() - INTERVAL 1 DAY',
                'cnt',
                ['ip' => $ip]
            );
            if ($cnt > 0) {
                $min = 2 ** $cnt;
                $min = \min([$min, 1440]);
                $ok  = Shop::Container()->getDB()->getSingleObject(
                    'SELECT dErstellt+INTERVAL ' . $min . ' MINUTE < NOW() AS moeglich
                        FROM tbestellung
                        WHERE cIP = :ip
                            AND dErstellt > NOW()-INTERVAL 1 DAY
                        ORDER BY kBestellung DESC',
                    ['ip' => $ip]
                );
                if ($ok === null || !$ok->moeglich) {
                    return 8;
                }
            }
        }

        return 10;
    }

    /**
     * gibt Gesamtanzahl Artikel des Warenkorbs zurueck
     *
     * @param int[] $itemTypes
     */
    public function gibAnzahlArtikelExt(
        array $itemTypes,
        bool $excludeShippingCostAttributes = false,
        string $iso = ''
    ): float|int {
        $count = 0;
        foreach ($this->PositionenArr as $item) {
            if (
                \in_array($item->nPosTyp, $itemTypes, true)
                && (empty($item->cUnique) || (\mb_strlen($item->cUnique) > 0 && $item->kKonfigitem === 0))
                && $item->isUsedForShippingCostCalculation($iso, $excludeShippingCostAttributes)
            ) {
                $count += $item->nAnzahl;
            }
        }

        return $count;
    }

    /**
     * gibt Anzahl der Positionen des Warenkorbs zurueck
     *
     * @param int[] $itemTypes
     * @return int
     */
    public function gibAnzahlPositionenExt(array $itemTypes): int
    {
        $count = 0;
        foreach ($this->PositionenArr as $item) {
            if (
                \in_array($item->nPosTyp, $itemTypes, true)
                && (empty($item->cUnique) || (\mb_strlen($item->cUnique) > 0 && $item->kKonfigitem === 0))
            ) {
                ++$count;
            }
        }

        return $count;
    }

    public function hatTeilbareArtikel(): bool
    {
        foreach ($this->PositionenArr as $item) {
            if ($item->nPosTyp === \C_WARENKORBPOS_TYP_ARTIKEL && ($item->Artikel->cTeilbar ?? 'N') === 'Y') {
                return true;
            }
        }

        return false;
    }

    /**
     * gibt Gesamtanzahl eines bestimmten Artikels im Warenkorb zurueck
     */
    public function gibAnzahlEinesArtikels(
        ?int $productID,
        int $excludePos = -1,
        bool $countParentProducts = false
    ): float|int {
        if (!$productID) {
            return 0;
        }
        $qty = 0;
        foreach ($this->PositionenArr as $i => $item) {
            if ($excludePos === $i) {
                continue;
            }
            $compareID = $countParentProducts && isset($item->Artikel) && $item->Artikel->kVaterArtikel > 0
                ? $item->Artikel->kVaterArtikel
                : $item->kArtikel;
            if ($compareID === $productID) {
                $qty += $item->nAnzahl;
            }
        }

        return $qty;
    }

    public function setzePositionsPreise(): self
    {
        $options               = Artikel::getDefaultOptions();
        $options->nStueckliste = 1;
        $options->nVariationen = 1;

        $bulk            = Settings::boolValue(Checkout::SCALE_PRICES_ACROSS_VARIATIONS);
        $customerGroup   = Frontend::getCustomerGroup();
        $customerGroupID = $customerGroup->getID();
        $currency        = Frontend::getCurrency();
        $langID          = Shop::getLanguageID();
        $db              = Shop::Container()->getDB();
        $cache           = Shop::Container()->getCache();
        $coupon          = $_SESSION['Kupon'] ?? null;
        $reApplyCoupon   = true;
        foreach ($this->PositionenArr as $item) {
            if ($item->kArtikel <= 0 || $item->nPosTyp !== \C_WARENKORBPOS_TYP_ARTIKEL) {
                $this->setzeKonfig($item, true, false);
                continue;
            }
            $oldItem                             = clone $item;
            $product                             = new Artikel($db, $customerGroup, $currency, $cache);
            $options->nKeineSichtbarkeitBeachten = 1;
            if ($item->kKonfigitem === 0) {
                $options->nKeineSichtbarkeitBeachten = 0;
            }
            if (!$product->fuelleArtikel($item->kArtikel, $options, $customerGroupID, $langID)) {
                continue;
            }
            // Baue Variationspreise im Warenkorb neu, aber nur wenn es ein gültiger Artikel ist
            foreach ($item->WarenkorbPosEigenschaftArr as $posAttr) {
                foreach ($product->Variationen as $variation) {
                    if ($posAttr->kEigenschaft !== $variation->kEigenschaft) {
                        continue;
                    }
                    foreach ($variation->Werte as $attrVal) {
                        if ($posAttr->kEigenschaftWert === $attrVal->kEigenschaftWert) {
                            $posAttr->fAufpreis          = $attrVal->fAufpreisNetto ?? null;
                            $posAttr->cAufpreisLocalized = $attrVal->cAufpreisLocalized[1] ?? null;
                            break;
                        }
                    }
                    break;
                }
            }
            if ($product->kVaterArtikel > 0 && $bulk) {
                $qty = $this->gibAnzahlEinesArtikels($product->kVaterArtikel, -1, true);
            } else {
                $qty = $this->gibAnzahlEinesArtikels($product->kArtikel);
            }
            $item->Artikel           = $product;
            $item->fPreisEinzelNetto = $product->gibPreis($qty, [], $customerGroupID, $item->cUnique);
            $item->fPreis            = $product->gibPreis(
                $qty,
                $item->WarenkorbPosEigenschaftArr,
                $customerGroupID,
                $item->cUnique
            );
            $item->fGesamtgewicht    = $item->gibGesamtgewicht();
            $item->fVK               = $product->Preise?->fVK;
            \executeHook(\HOOK_SETZTE_POSITIONSPREISE, [
                'position'    => $item,
                'oldPosition' => $oldItem
            ]);
            $item->setzeGesamtpreisLocalized();
            // notify about price changes when the price difference is greater then .01
            if ($oldItem->cGesamtpreisLocalized !== $item->cGesamtpreisLocalized && $oldItem->fVK !== $item->fVK) {
                $this->notifyPriceChange($item, $oldItem);
            }
            unset($item->cHinweis);
            if (
                ($coupon->kKupon ?? 0) > 0
                && (int)$coupon->nGanzenWKRabattieren === 0
                && Frontend::getCart()->posTypEnthalten(\C_WARENKORBPOS_TYP_KUPON) === false
            ) {
                $item = CartHelper::checkCouponCartItems($item, $coupon);
                $item->setzeGesamtpreisLocalized();
                $reApplyCoupon = false;
            }
            $this->setzeKonfig($item, true, false);
            \executeHook(\HOOK_SET_POSITION_PRICES_END, [
                'position'    => &$item,
                'oldPosition' => $oldItem
            ]);
        }
        if ($reApplyCoupon && $coupon instanceof Kupon) {
            $this->loescheSpezialPos(\C_WARENKORBPOS_TYP_KUPON);
            $coupon->accept();
        }

        return $this;
    }

    private function notifyPriceChange(CartItem $item, CartItem $oldItem): void
    {
        $updated                           = new stdClass();
        $updated->cKonfigpreisLocalized    = $item->cKonfigpreisLocalized;
        $updated->cGesamtpreisLocalized    = $item->cGesamtpreisLocalized;
        $updated->cName                    = $item->cName;
        $updated->cKonfigpreisLocalizedOld = $oldItem->cKonfigpreisLocalized;
        $updated->cGesamtpreisLocalizedOld = $oldItem->cGesamtpreisLocalized;
        $updated->istKonfigVater           = $item->istKonfigVater();
        self::addUpdatedPosition($updated);
        Shop::Container()->getAlertService()->addWarning(
            \sprintf(
                Shop::Lang()->get('priceHasChanged', 'checkout'),
                $item->getName(Shop::getLanguageCode())
            ),
            'priceHasChanged_' . $item->kArtikel,
            [
                'saveInSession' => true,
                'dismissable'   => false,
                'linkHref'      => Shop::Container()->getLinkService()->getStaticRoute('warenkorb.php'),
                'linkText'      => Shop::Lang()->get('gotoBasket'),
            ]
        );
    }

    public function setzeKonfig(CartItem $item, bool $prices = true, bool $name = true): self
    {
        // Falls Konfigitem gesetzt Preise + Name ueberschreiben
        if ($item->kKonfigitem <= 0) {
            return $this;
        }
        $configItem = new Item($item->kKonfigitem);
        if ($configItem->getKonfigitem() > 0) {
            if ($prices) {
                $item->fPreisEinzelNetto = $configItem->getPreis(true);
                $item->fPreis            = $item->fPreisEinzelNetto;
                $item->kSteuerklasse     = $configItem->getSteuerklasse();
                $item->setzeGesamtpreisLocalized();
            }
            if ($name && $configItem->getUseOwnName()) {
                foreach (Frontend::getLanguages() as $language) {
                    $localized                         = new ItemLocalization(
                        $configItem->getKonfigitem(),
                        $language->getId()
                    );
                    $item->cName[$language->getCode()] = $localized->getName();
                }
            }
        }

        return $this;
    }

    /**
     * gibt Gesamtanzahl einer bestimmten Variation im Warenkorb zurueck
     */
    public function gibAnzahlEinerVariation(
        int $productID,
        int $propertyValueID,
        int $excludeItem = -1
    ): float|int {
        if (!$productID || !$propertyValueID) {
            return 0;
        }
        $qty = 0;
        foreach ($this->PositionenArr as $i => $item) {
            if ($item->kArtikel === $productID && $excludeItem !== $i) {
                foreach ($item->WarenkorbPosEigenschaftArr as $attr) {
                    if ($attr->kEigenschaftWert === $propertyValueID) {
                        $qty += $item->nAnzahl;
                    }
                }
            }
        }

        return $qty;
    }

    /**
     * @comment Instead use ShippingService->getTaxRateIDs() directly
     * @deprecated since 5.5.0
     */
    public function gibVersandkostenSteuerklasse(string $countryCode = ''): int
    {
        return $this->getShippingService()->getTaxRateIDs(
            '',
            $this->PositionenArr,
            $countryCode
        )[0]->taxRateID ?? 0;
    }

    /**
     * gibt die Versandkosten als String zurueck
     */
    public function gibVersandKostenText(): string
    {
        return isset($_SESSION['Versandart'])
            ? Shop::Lang()->get('noShippingCosts', 'basket')
            : (Shop::Lang()->get('plus', 'basket') . ' ' . Shop::Lang()->get('shipping', 'basket'));
    }

    public function gibGesamtsummeWaren(bool $gross = false, bool $considerBalance = true): float|int
    {
        $currency         = $this->Waehrung ?? Frontend::getCurrency();
        $conversionFactor = $currency->getConversionFactor();
        $total            = 0;
        foreach ($this->PositionenArr as $item) {
            // Lokalisierte Preise addieren
            if ($gross) {
                $total += $item->fPreis * $conversionFactor * $item->nAnzahl *
                    ((100 + CartItem::getTaxRate($item)) / 100);
            } else {
                $total += $item->fPreis * $conversionFactor * $item->nAnzahl;
            }
        }
        if ($gross) {
            $total = \round(\round($total, 3), 2);
        }
        if (
            !empty($considerBalance)
            && isset(
                $_SESSION['Bestellung']->GuthabenNutzen,
                $_SESSION['Bestellung']->fGuthabenGenutzt,
                $_SESSION['Kunde']->fGuthaben
            )
            && (int)$_SESSION['Bestellung']->GuthabenNutzen === 1
            && $_SESSION['Bestellung']->fGuthabenGenutzt > 0
            && $_SESSION['Kunde']->fGuthaben > 0
        ) {
            // check and correct the SESSION-values for "Guthaben"
            $total -= Order::getOrderCredit() * $conversionFactor;
        }
        $total /= $conversionFactor;
        $this->useSummationRounding();

        return CartHelper::roundOptionalCurrency($total, $this->Waehrung ?? Frontend::getCurrency());
    }

    /**
     * Gibt gesamte Warenkorbsumme eines positionstyps zurueck.
     * @param int[]  $types
     * @param bool   $gross
     * @param string $iso
     * @param bool   $excludeShippingCostAttributes
     * @return float|int
     */
    public function gibGesamtsummeWarenExt(
        array $types,
        bool $gross = false,
        bool $excludeShippingCostAttributes = false,
        string $iso = ''
    ): float|int {
        $total = 0;
        foreach ($this->PositionenArr as $item) {
            if (
                \in_array($item->nPosTyp, $types, true)
                && $item->isUsedForShippingCostCalculation($iso, $excludeShippingCostAttributes)
            ) {
                if ($gross) {
                    $total += $item->fPreis * $item->nAnzahl * ((100 + CartItem::getTaxRate($item)) / 100);
                } else {
                    $total += $item->fPreis * $item->nAnzahl;
                }
            }
        }
        if ($gross) {
            $total = \round(\round($total, 3), 2);
        }
        $this->useSummationRounding();

        return CartHelper::roundOptionalCurrency($total, $this->Waehrung ?? Frontend::getCurrency());
    }

    /**
     * Gibt gesamte Warenkorbsumme ohne bestimmte Positionstypen zurueck.
     * @param int[] $types
     */
    public function gibGesamtsummeWarenOhne(array $types, bool $gross = false): float
    {
        $total    = 0.0;
        $currency = $this->Waehrung ?? Frontend::getCurrency();
        $factor   = $currency->getConversionFactor();
        foreach ($this->PositionenArr as $item) {
            if (\in_array($item->nPosTyp, $types, true)) {
                continue;
            }
            if ($gross) {
                $total += $item->fPreis * $factor * $item->nAnzahl * ((100 + CartItem::getTaxRate($item)) / 100);
            } else {
                $total += $item->fPreis * $factor * $item->nAnzahl;
            }
        }
        if ($gross) {
            $total = \round(\round($total, 3), 2);
        }

        return $total / $factor;
    }

    public function berechnePositionenUst(): self
    {
        foreach ($this->PositionenArr as $item) {
            $item->setzeGesamtpreisLocalized();
        }

        return $this;
    }

    /**
     * Gibt gesamte Warenkorbsumme lokalisiert als array zurueck.
     *
     * @return string[] - Gesamtsumme des Warenkorb
     */
    public function gibGesamtsummeWarenLocalized(): array
    {
        $sum    = [];
        $sum[0] = Preise::getLocalizedPriceString($this->gibGesamtsummeWaren(true));
        $sum[1] = Preise::getLocalizedPriceString($this->gibGesamtsummeWaren());
        \executeHook(\HOOK_CART_GET_LOCALIZED_SUM, [
            'sum' => &$sum
        ]);

        return $sum;
    }

    /**
     * Entfernt Positionen mit nAnzahl 0 im Warenkorb
     */
    public function loescheNullPositionen(): self
    {
        foreach ($this->PositionenArr as $i => $item) {
            if ($item->nAnzahl <= 0) {
                unset($this->PositionenArr[$i]);
            }
        }
        $this->PositionenArr = \array_merge($this->PositionenArr);

        return $this;
    }

    /**
     * schaut, ob eine Position dieses Typs enthalten ist
     */
    public function posTypEnthalten(int $type): bool
    {
        return some($this->PositionenArr, fn($e): bool => (int)$e->nPosTyp === $type);
    }

    /**
     * @return stdClass[]
     */
    public function gibSteuerpositionen(): array
    {
        $taxRates = [];
        $taxItems = [];
        $currency = Frontend::getCurrency();
        foreach ($this->PositionenArr as $item) {
            if ($item->kSteuerklasse <= 0) {
                continue;
            }
            $ust = Tax::getSalesTax($item->kSteuerklasse);
            if (!\in_array($ust, $taxRates, true)) {
                $taxRates[] = $ust;
            }
        }
        \sort($taxRates);
        $isMerchant = Frontend::getCustomerGroup()->isMerchant();
        foreach ($this->PositionenArr as $item) {
            if ($item->kSteuerklasse <= 0) {
                continue;
            }
            $ust = Tax::getSalesTax($item->kSteuerklasse);
            if ($ust <= 0) {
                continue;
            }
            $idx     = \array_search($ust, $taxRates, true);
            $taxItem = $taxItems[$idx] ?? null;
            if ($taxItem === null || !isset($taxItem->fBetrag)) {
                $taxItem                  = new stdClass();
                $taxItem->cName           = Texts::taxItems($ust, $isMerchant);
                $taxItem->fUst            = $ust;
                $taxItem->fBetrag         = round((($item->fPreis * $item->nAnzahl * $ust) / 100.0) * 100) / 100;
                $taxItem->cPreisLocalized = Preise::getLocalizedPriceString($taxItem->fBetrag, $currency);

                $taxItems[$idx] = $taxItem;
            } else {
                $taxItem->fBetrag         += round((($item->fPreis * $item->nAnzahl * $ust) / 100.0) * 100) / 100;
                $taxItem->cPreisLocalized = Preise::getLocalizedPriceString($taxItems[$idx]->fBetrag, $currency);
            }
        }

        return $taxItems;
    }

    public function setzeVersandfreiKupon(): self
    {
        foreach ($this->PositionenArr as $item) {
            if ($item->nPosTyp === \C_WARENKORBPOS_TYP_VERSANDPOS) {
                $item->fPreisEinzelNetto = 0.0;
                $item->fPreis            = 0.0;
                $item->setzeGesamtpreisLocalized();
            }
        }

        return $this;
    }

    /**
     * geht alle Positionen durch, korrigiert Lagerbestaende und entfernt Positionen, die nicht mehr vorraetig sind
     */
    public function pruefeLagerbestaende(): self
    {
        $redirect      = false;
        $depAmount     = $this->getAllDependentAmount(true);
        $reservedStock = [];
        $db            = Shop::Container()->getDB();
        foreach ($this->PositionenArr as $i => $item) {
            if (
                $item->Artikel === null
                || $item->kArtikel <= 0
                || $item->Artikel->cLagerBeachten !== 'Y'
                || $item->Artikel->cLagerKleinerNull === 'Y'
            ) {
                continue;
            }
            // Lagerbestand beachten und keine Überverkäufe möglich
            if (
                !$item->Artikel->kVaterArtikel
                && !$item->Artikel->nIstVater
                && $item->Artikel->cLagerVariation === 'Y'
                && \count($item->WarenkorbPosEigenschaftArr) > 0
            ) {
                // Position mit Variationen, Lagerbestand in Variationen wird beachtet
                foreach ($item->WarenkorbPosEigenschaftArr as $oWarenkorbPosEigenschaft) {
                    if ($oWarenkorbPosEigenschaft->kEigenschaftWert > 0 && $item->nAnzahl > 0) {
                        // schaue in DB, ob Lagerbestand ausreichend
                        $stock = $db->getSingleObject(
                            'SELECT kEigenschaftWert, fLagerbestand >= :cnt AS bAusreichend, fLagerbestand
                                FROM teigenschaftwert
                                WHERE kEigenschaftWert = :vid',
                            ['cnt' => $item->nAnzahl, 'vid' => (int)$oWarenkorbPosEigenschaft->kEigenschaftWert]
                        );
                        if ($stock !== null && $stock->kEigenschaftWert > 0 && !$stock->bAusreichend) {
                            if ($stock->fLagerbestand > 0) {
                                $item->nAnzahl = $stock->fLagerbestand;
                            } else {
                                unset($this->PositionenArr[$i]);
                            }
                            $redirect = true;
                        }
                    }
                }
            } else {
                // Position ohne Variationen bzw. Variationen ohne eigenen Lagerbestand
                // schaue in DB, ob Lagerbestand ausreichend
                $depProducts = $item->Artikel->getAllDependentProducts(true);
                $depStock    = $db->getObjects(
                    'SELECT kArtikel, fLagerbestand
                        FROM tartikel
                        WHERE kArtikel IN (' . \implode(', ', \array_keys($depProducts)) . ')'
                );
                foreach ($depStock as $productStock) {
                    $productID = (int)$productStock->kArtikel;

                    if (
                        $depProducts[$productID]->product->fPackeinheit * $depAmount[$productID]
                        > $productStock->fLagerbestand
                    ) {
                        $newAmount = \floor(
                            ($productStock->fLagerbestand - ($reservedStock[$productID] ?? 0))
                            / $depProducts[$productID]->product->fPackeinheit
                            / $depProducts[$productID]->stockFactor
                        );
                        if ($newAmount > $item->nAnzahl) {
                            $newAmount = $item->nAnzahl;
                        }

                        if ($newAmount > 0) {
                            $item->nAnzahl = $newAmount;
                        } else {
                            unset($this->PositionenArr[$i]);
                        }

                        $reservedStock[$productID] = ($reservedStock[$productID] ?? 0)
                            + $newAmount
                            * $depProducts[$productID]->product->fPackeinheit * $depProducts[$productID]->stockFactor;

                        $depAmount = $this->getAllDependentAmount(true);
                        $redirect  = true;
                    }
                }
            }
        }

        if ($redirect) {
            CartHelper::deleteAllSpecialItems();
            $linkHelper = Shop::Container()->getLinkService();
            \header('Location: ' . $linkHelper->getStaticRoute('warenkorb.php') . '?fillOut=10', true, 303);
            exit;
        }

        return $this;
    }

    public function loadFromDB(int $kWarenkorb): self
    {
        $obj = Shop::Container()->getDB()->select('twarenkorb', 'kWarenkorb', $kWarenkorb);
        if ($obj === null) {
            return $this;
        }
        $this->kWarenkorb     = (int)$obj->kWarenkorb;
        $this->kKunde         = (int)$obj->kKunde;
        $this->kLieferadresse = (int)$obj->kLieferadresse;
        $this->kZahlungsInfo  = (int)$obj->kZahlungsInfo;

        return $this;
    }

    public function insertInDB(): int
    {
        $obj = (object)[
            'kKunde'         => $this->kKunde,
            'kLieferadresse' => $this->kLieferadresse,
            'kZahlungsInfo'  => $this->kZahlungsInfo,
        ];
        if (!isset($obj->kZahlungsInfo) || $obj->kZahlungsInfo === '') {
            $obj->kZahlungsInfo = 0;
        }
        $this->kWarenkorb = Shop::Container()->getDB()->insert('twarenkorb', $obj);

        return $this->kWarenkorb;
    }

    public function updateInDB(): int
    {
        $obj = (object)[
            'kWarenkorb'     => (int)$this->kWarenkorb,
            'kKunde'         => (int)$this->kKunde,
            'kLieferadresse' => (int)$this->kLieferadresse,
            'kZahlungsInfo'  => $this->kZahlungsInfo,
        ];

        return Shop::Container()->getDB()->update('twarenkorb', 'kWarenkorb', $obj->kWarenkorb, $obj);
    }

    public function getLongestMinMaxDelivery(): ?stdClass
    {
        if (\count($this->PositionenArr) === 0) {
            return null;
        }
        $result = (object)[
            'longestMin' => 0,
            'longestMax' => 0,
        ];

        foreach ($this->PositionenArr as $item) {
            if ($item->nPosTyp !== \C_WARENKORBPOS_TYP_ARTIKEL || !$item->Artikel instanceof Artikel) {
                continue;
            }
            try {
                $item->Artikel->getDeliveryTime(
                    $_SESSION['cLieferlandISO'],
                    $item->nAnzahl !== null
                        ? (float)$item->nAnzahl
                        : null,
                );
            } catch (Exception) {
                continue;
            }
            CartItem::setEstimatedDelivery(
                $item,
                $item->Artikel->nMinDeliveryDays,
                $item->Artikel->nMaxDeliveryDays
            );
            if (isset($item->Artikel->nMinDeliveryDays) && $item->Artikel->nMinDeliveryDays > $result->longestMin) {
                $result->longestMin = $item->Artikel->nMinDeliveryDays;
            }
            if (isset($item->Artikel->nMaxDeliveryDays) && $item->Artikel->nMaxDeliveryDays > $result->longestMax) {
                $result->longestMax = $item->Artikel->nMaxDeliveryDays;
            }
        }

        return $result;
    }

    public function getEstimatedDeliveryTime(): string
    {
        $longestMinMaxDeliveryDays = $this->getLongestMinMaxDelivery();

        return $longestMinMaxDeliveryDays === null
            ? ''
            : $this->getShippingService()->getDeliverytimeEstimationText(
                $longestMinMaxDeliveryDays->longestMin,
                $longestMinMaxDeliveryDays->longestMax
            );
    }

    public function gibLetztenWKArtikel(): ?Artikel
    {
        $res        = null;
        $lastUpdate = 0;
        foreach ($this->PositionenArr as $item) {
            if ($item->nPosTyp === \C_WARENKORBPOS_TYP_ARTIKEL && $item->kKonfigitem === 0) {
                if ($item->nZeitLetzteAenderung > $lastUpdate) {
                    $lastUpdate = $item->nZeitLetzteAenderung;
                    $res        = $item->Artikel;
                } elseif ($res === null) {
                    // Wenn keine nZeitLetzteAenderung gesetzt ist letztes Element des WK-Arrays nehmen
                    $res = $item->Artikel;
                }
            }
        }

        return $res;
    }

    public function getWeight(bool $excludeShippingCostAttributes = false, string $iso = ''): float
    {
        $weight = 0.0;
        foreach ($this->PositionenArr as $item) {
            if ($item->isUsedForShippingCostCalculation($iso, $excludeShippingCostAttributes)) {
                $weight += $item->fGesamtgewicht;
            }
        }

        return $weight;
    }

    public function redirectTo(bool $isRedirect = false, false|string $unique = false): void
    {
        if (
            !$isRedirect
            && !$unique
            && !isset($_SESSION['variBoxAnzahl_arr'])
            && $this->getConfiguration(
                'global',
                'global_warenkorb_weiterleitung',
                ''
            ) === 'Y'
        ) {
            $linkHelper = Shop::Container()->getLinkService();
            \header('Location: ' . $linkHelper->getStaticRoute('warenkorb.php'), true, 303);
            exit;
        }
    }

    /**
     * Unique hash to identify any basket changes
     */
    public function getUniqueHash(): string
    {
        return \sha1(\serialize($this));
    }

    /**
     * make sure the applied coupons are still valid after removing items from the cart
     * or updating amounts
     */
    public function checkIfCouponIsStillValid(): bool
    {
        if (!isset($_SESSION['Kupon']->kKupon)) {
            return true;
        }
        $isValid = true;
        require_once \PFAD_ROOT . \PFAD_INCLUDES . 'bestellvorgang_inc.php';
        if ($this->posTypEnthalten(\C_WARENKORBPOS_TYP_KUPON)) {
            // Kupon darf nicht im leeren Warenkorb eingelöst werden
            if (isset($_SESSION['Warenkorb']) && $this->gibAnzahlArtikelExt([\C_WARENKORBPOS_TYP_ARTIKEL]) > 0) {
                $coupon = Shop::Container()->getDB()->select('tkupon', 'kKupon', (int)$_SESSION['Kupon']->kKupon);
                if ($coupon !== null && $coupon->kKupon > 0 && $coupon->cKuponTyp === Kupon::TYPE_STANDARD) {
                    $isValid = (Form::hasNoMissingData(Kupon::checkCoupon($coupon)) === 1);
                    $this->updateCouponValue();
                } elseif (!empty($coupon->kKupon) && $coupon->cKuponTyp === Kupon::TYPE_SHIPPING) {
                    $isValid = true;
                } else {
                    $isValid = false;
                }
            }
            if ($isValid === false) {
                unset($_SESSION['Kupon']);
                $this->loescheSpezialPos(\C_WARENKORBPOS_TYP_KUPON)
                    ->setzePositionsPreise();
            }
        } elseif (
            isset($_SESSION['Kupon']->nGanzenWKRabattieren, $_SESSION['Kupon']->cKuponTyp, $_SESSION['Kupon']->cWertTyp)
            && (int)$_SESSION['Kupon']->nGanzenWKRabattieren === 0
            && $_SESSION['Kupon']->cKuponTyp === Kupon::TYPE_STANDARD
            && $_SESSION['Kupon']->cWertTyp === 'prozent'
        ) {
            if (isset($_SESSION['Warenkorb']) && $this->gibAnzahlArtikelExt([\C_WARENKORBPOS_TYP_ARTIKEL]) > 0) {
                $coupon  = Shop::Container()->getDB()->select('tkupon', 'kKupon', (int)$_SESSION['Kupon']->kKupon);
                $isValid = false;
                if (isset($coupon->kKupon) && $coupon->kKupon > 0 && $coupon->cKuponTyp === Kupon::TYPE_STANDARD) {
                    $isValid = (Form::hasNoMissingData(Kupon::checkCoupon($coupon)) === 1);
                }
            }
            if ($isValid === false) {
                unset($_SESSION['Kupon']);
                $this->loescheSpezialPos(\C_WARENKORBPOS_TYP_KUPON)
                    ->setzePositionsPreise();
            }
        } elseif (
            isset($_SESSION['Kupon']->nGanzenWKRabattieren, $_SESSION['Kupon']->cKuponTyp)
            && (int)$_SESSION['Kupon']->nGanzenWKRabattieren === 0
            && $_SESSION['Kupon']->cKuponTyp === Kupon::TYPE_STANDARD
        ) {
            // we have a coupon in the current session but none in the cart.
            // this happens with coupons tied to special products that are no longer valid.
            unset($_SESSION['Kupon']);
        }

        return $isValid;
    }

    /**
     * update coupon value to avoid negative orders or coupon values under predefined value
     */
    public function updateCouponValue(): void
    {
        if (!isset($_SESSION['Kupon']) || $_SESSION['Kupon']->cWertTyp !== 'festpreis') {
            return;
        }
        require_once \PFAD_ROOT . \PFAD_INCLUDES . 'bestellvorgang_inc.php';
        /** @var Kupon $coupon */
        $coupon        = $_SESSION['Kupon'];
        $maxPreisKupon = $coupon->fWert;
        $db            = Shop::Container()->getDB();
        if ($coupon->fWert > $this->gibGesamtsummeWarenExt([\C_WARENKORBPOS_TYP_ARTIKEL], true)) {
            $maxPreisKupon = $this->gibGesamtsummeWarenExt([\C_WARENKORBPOS_TYP_ARTIKEL], true);
        }
        if (
            $coupon->nGanzenWKRabattieren === 0
            && $coupon->fWert > CartHelper::getCouponProductsTotal($coupon, $this->PositionenArr)
        ) {
            $maxPreisKupon = CartHelper::getCouponProductsTotal($coupon, $this->PositionenArr);
        }
        $specialPosition        = new stdClass();
        $specialPosition->cName = [];
        foreach (Frontend::getLanguages() as $language) {
            $localized                                    = $db->select(
                'tkuponsprache',
                'kKupon',
                (int)$coupon->kKupon,
                'cISOSprache',
                $language->getCode(),
                null,
                null,
                false,
                'cName'
            );
            $specialPosition->cName[$language->getCode()] = $localized->cName ?? '';
        }
        $this->loescheSpezialPos(\C_WARENKORBPOS_TYP_KUPON);
        $this->erstelleSpezialPos(
            $specialPosition->cName,
            1,
            $maxPreisKupon * -1,
            (int)$coupon->kSteuerklasse,
            \C_WARENKORBPOS_TYP_KUPON
        );
    }

    /**
     * use summation rounding to even out discrepancies between total basket sum and sum of basket position totals
     */
    public function useSummationRounding(int $precision = 2): void
    {
        $cumulatedDelta    = 0;
        $cumulatedDeltaNet = 0;
        foreach (Frontend::getCurrencies() as $currency) {
            $currencyName = $currency->getName();
            foreach ($this->PositionenArr as $i => $item) {
                $grossAmount        = Tax::getGross(
                    $item->fPreis * $item->nAnzahl,
                    CartItem::getTaxRate($item),
                    12
                );
                $netAmount          = $item->fPreis * $item->nAnzahl;
                $roundedGrossAmount = Tax::getGross(
                    $item->fPreis * $item->nAnzahl + $cumulatedDelta,
                    CartItem::getTaxRate($item),
                    $precision
                );
                $roundedNetAmount   = \round($item->fPreis * $item->nAnzahl + $cumulatedDeltaNet, $precision);

                if ($i !== 0 && $item->nPosTyp === \C_WARENKORBPOS_TYP_ARTIKEL) {
                    if ($grossAmount !== 0.0) {
                        $item->cGesamtpreisLocalized[0][$currencyName] = Preise::getLocalizedPriceString(
                            $roundedGrossAmount,
                            $currency
                        );
                    }
                    if ((int)$netAmount !== 0) {
                        $item->cGesamtpreisLocalized[1][$currencyName] = Preise::getLocalizedPriceString(
                            $roundedNetAmount,
                            $currency
                        );
                    }
                }
                $cumulatedDelta    += ($grossAmount - $roundedGrossAmount);
                $cumulatedDeltaNet += ($netAmount - $roundedNetAmount);
            }
        }
    }

    public static function getChecksum(Cart $cart): string
    {
        $longestMinMaxDelivery = $cart->getLongestMinMaxDelivery();
        $checks                = [
            'EstimatedDelivery' => $longestMinMaxDelivery === null
                ? ''
                : $longestMinMaxDelivery->longestMin . ':' . $longestMinMaxDelivery->longestMax,
            'PositionenCount'   => \count($cart->PositionenArr),
            'PositionenArr'     => [],
        ];
        foreach ($cart->PositionenArr as $wkPos) {
            $checks['PositionenArr'][] = \md5(
                \serialize([
                    'kArtikel'          => $wkPos->kArtikel ?? 0,
                    'nAnzahl'           => $wkPos->nAnzahl ?? 0,
                    'kVersandklasse'    => $wkPos->kVersandklasse,
                    'nPosTyp'           => $wkPos->nPosTyp ?? 0,
                    'fPreisEinzelNetto' => $wkPos->fPreisEinzelNetto ?? 0.0,
                    'fPreis'            => $wkPos->fPreis ?? 0.0,
                    'cHinweis'          => $wkPos->cHinweis ?? '',
                ])
            );
        }
        \sort($checks['PositionenArr']);

        return \md5(\serialize($checks));
    }

    /**
     * refresh internal cart checksum
     */
    public static function refreshChecksum(Cart $cart): void
    {
        $cart->cChecksumme = self::getChecksum($cart);
    }

    /**
     * Check if basket has digital products.
     */
    public function hasDigitalProducts(): bool
    {
        return Download::hasDownloads($this);
    }

    /**
     * @description Cheapest shipping except shippings that offer cash payment
     */
    public function getFavourableShipping(?int $shippingFreeMinID = null): ?Versandart
    {
        if ($shippingFreeMinID !== null) {
            \trigger_error(
                'The parameter shippingFreeMinID in the method ' . __METHOD__
                . ' is deprecated and should not be used anymore.',
                \E_USER_DEPRECATED
            );
        }

        $customer         = new Customer($this->kKunde ?? Frontend::getCustomer()->kKunde ?? 0);
        $deliveryAddress  = $this->getShippingService()->getDeliveryAddress($customer);
        $possibleMethods  = $this->getShippingService()->getPossibleShippingMethods(
            Frontend::getCustomer(),
            Frontend::getCustomerGroup(),
            (string)$deliveryAddress->cLand,
            $this->Waehrung ?? Frontend::getCurrency(),
            (string)$deliveryAddress->cPLZ,
            $this->PositionenArr ?: Frontend::getCart()->PositionenArr,
        );
        $favourableMethod = $this->getShippingService()->getFavourableShippingMethod($possibleMethods);
        if ($favourableMethod === null) {
            return null;
        }

        $customShippingCosts       = $favourableMethod->customShippingCosts;
        $this->oFavourableShipping = $favourableMethod->toVersandart((string)$deliveryAddress->cLand);
        if (isset($this->oFavourableShipping->cPriceLocalized[0], $this->oFavourableShipping->cPriceLocalized[1])) {
            [$grossPrice, $netPrice] = $this->oFavourableShipping->cPriceLocalized;
            foreach ($customShippingCosts as $customShippingCost) {
                $grossPrice += Tax::getGross(
                    $customShippingCost->netPrice,
                    Tax::getSalesTax($customShippingCost->taxClassID),
                );
                $netPrice   += $customShippingCost->netPrice;
            }
            $this->oFavourableShipping->cPriceLocalized[0] = Preise::getLocalizedPriceString($grossPrice);
            $this->oFavourableShipping->cPriceLocalized[1] = Preise::getLocalizedPriceString($netPrice);
        }
        $this->setFavourableShippingString(\count($possibleMethods));

        return $this->oFavourableShipping;
    }

    public function setFavourableShippingString(int $shippingMethodsCount): void
    {
        if ($this->posTypEnthalten(\C_WARENKORBPOS_TYP_VERSANDPOS)) {
            $this->favourableShippingString = '';

            return;
        }
        if ($this->oFavourableShipping === null || $this->oFavourableShipping->country === null) {
            try {
                $this->favourableShippingString = \sprintf(
                    Shop::Lang()->get('shippingInformation', 'basket'),
                    Shop::Container()->getLinkService()->getSpecialPage(\LINKTYP_VERSAND)->getURL()
                );
            } catch (SpecialPageNotFoundException $e) {
                $this->favourableShippingString = '';
                Shop::Container()->getLogService()->error($e->getMessage());
            }

            return;
        }
        $isMerchant    = Frontend::getCustomerGroup()->getIsMerchant();
        $shippingCosts = $this->oFavourableShipping->cPriceLocalized[$isMerchant];

        if ($isMerchant) {
            $shippingCosts = \sprintf(
                '`%s` %s %s',
                $shippingCosts,
                Shop::Lang()->get('plus', 'basket'),
                Shop::Lang()->get('vat', 'productDetails')
            );
        }
        try {
            if ($shippingMethodsCount === 1) {
                $this->favourableShippingString = \sprintf(
                    Shop::Lang()->get('shippingInformationSpecificSingle', 'basket'),
                    Shop::Container()->getLinkService()->getSpecialPage(\LINKTYP_VERSAND)->getURL(),
                    $shippingCosts,
                    $this->oFavourableShipping->country->getName()
                );
            } else {
                $this->favourableShippingString = \sprintf(
                    Shop::Lang()->get('shippingInformationSpecific', 'basket'),
                    Shop::Container()->getLinkService()->getSpecialPage(\LINKTYP_VERSAND)->getURL(),
                    $shippingCosts,
                    $this->oFavourableShipping->country->getName()
                );
            }
        } catch (SpecialPageNotFoundException $e) {
            $this->favourableShippingString = '';
            Shop::Container()->getLogService()->error($e->getMessage());
        }
    }

    public function getShippingCountry(): string
    {
        return Request::postVar('land')
            ?? Frontend::get('Lieferadresse')->cLand
            ?? Frontend::getCustomer()->cLand
            ?? Frontend::get('cLieferlandISO');
    }

    public function removeParentItems(): int
    {
        $deletedItemCount = 0;
        foreach ($this->PositionenArr as $i => $item) {
            $delete = false;
            if (
                ($item->nPosTyp === \C_WARENKORBPOS_TYP_ARTIKEL
                    || $item->nPosTyp === \C_WARENKORBPOS_TYP_GRATISGESCHENK)
                && (int)$item->Artikel?->nIstVater === 1
            ) {
                $delete = true;
                \executeHook(\HOOK_CART_DELETE_PARENT_CART_ITEM, [
                    'positionItem' => $item,
                    'delete'       => &$delete
                ]);
            }
            if ($delete) {
                Shop::Container()->getLogService()->warning(
                    message: 'Removed item with ID {productID} from basket because it is a parent item.',
                    context: ['productID' => $item->kArtikel]
                );
                $deletedItemCount++;
                self::addDeletedPosition($item);
                unset($this->PositionenArr[$i]);
            }
        }
        $this->PositionenArr = \array_values($this->PositionenArr);

        return $deletedItemCount;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $res           = \get_object_vars($this);
        $res['config'] = '*truncated*';

        return $res;
    }
}
