<?php

declare(strict_types=1);

namespace JTL\Extensions\Config;

use JsonSerializable;
use JTL\Catalog\Product\Artikel;
use JTL\Catalog\Product\Preise;
use JTL\Helpers\Tax;
use JTL\Helpers\Text;
use JTL\Language\LanguageHelper;
use JTL\Nice;
use JTL\Session\Frontend;
use JTL\Shop;
use stdClass;

use function Functional\select;

/**
 * Class Item
 * @package JTL\Extensions\Config
 */
class Item implements JsonSerializable
{
    protected ?int $kKonfigitem = null;

    protected int $kArtikel = 0;

    protected int $nPosTyp = 0;

    protected int $kKonfiggruppe = 0;

    protected int $bSelektiert = 0;

    protected int $bEmpfohlen = 0;

    protected int $bPreis = 0;

    protected int $bName = 0;

    protected int $bRabatt = 0;

    protected int $bZuschlag = 0;

    protected int $bIgnoreMultiplier = 0;

    protected float $fMin = 0.0;

    protected float $fMax = 0.0;

    protected string|float $fInitial = 0.0;

    protected ?ItemLocalization $oSprache = null;

    protected ?ItemPrice $oPreis = null;

    protected ?Artikel $oArtikel = null;

    protected int $kSprache = 0;

    protected int $kKundengruppe = 0;

    protected int $nSort = 0;

    public ?float $fAnzahl = null;

    public ?float $fAnzahlWK = null;

    public bool $bAktiv = false;

    /**
     * @var array<mixed>|null
     */
    public ?array $oEigenschaftwerte_arr = null;

    public function __construct(int $id = 0, int $languageID = 0, int $customerGroupID = 0)
    {
        if ($id > 0) {
            $this->loadFromDB($id, $languageID, $customerGroupID);
        }
    }

    /**
     * @return string[]
     */
    public function __sleep(): array
    {
        return select(\array_keys(\get_object_vars($this)), fn(string $e): bool => $e !== 'oArtikel');
    }

    public function __wakeup(): void
    {
        if ($this->kArtikel > 0) {
            $this->addProduct($this->kKundengruppe, $this->kSprache);
        }
    }

    public static function checkLicense(): bool
    {
        return Nice::getInstance()->checkErweiterung(\SHOP_ERWEITERUNG_KONFIGURATOR);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $shortDescription = $this->getKurzBeschreibung();
        $virtual          = ['bAktiv' => $this->bAktiv];
        $override         = [
            'kKonfigitem'   => $this->getKonfigitem(),
            'cName'         => $this->getName(),
            'kArtikel'      => $this->getArtikelKey(),
            'cBeschreibung' => !empty($shortDescription)
                ? $shortDescription
                : $this->getBeschreibung(),

            'bAnzahl'         => $this->getMin() !== $this->getMax(),
            'fInitial'        => $this->getInitial(),
            'fMin'            => $this->getMin(),
            'fMax'            => $this->getMax(),
            'cBildPfad'       => $this->getBildPfad(),
            'fPreis'          => [
                (float)$this->getPreis(),
                (float)$this->getPreis(true)
            ],
            'fPreisLocalized' => [
                Preise::getLocalizedPriceString($this->getPreis()),
                Preise::getLocalizedPriceString($this->getPreis(true))
            ]
        ];

        return Text::utf8_convert_recursive(\array_merge($override, $virtual));
    }

    private function loadFromDB(int $id = 0, int $languageID = 0, int $customerGroupID = 0): self
    {
        if (!self::checkLicense()) {
            return $this;
        }
        $item = Shop::Container()->getDB()->select('tkonfigitem', 'kKonfigitem', $id);
        if ($item === null || $item->kKonfigitem <= 0) {
            return $this;
        }
        if (!$languageID) {
            $languageID = Shop::getLanguageID() ?: LanguageHelper::getDefaultLanguage()->kSprache;
        }
        if (!$customerGroupID) {
            $customerGroupID = Frontend::getCustomerGroup()->getID();
        }

        return $this->assignData($item, $languageID, $customerGroupID);
    }

    public function assignData(stdClass $item, int $languageID, int $customerGroupID): self
    {
        $this->kKonfiggruppe     = (int)$item->kKonfiggruppe;
        $this->kKonfigitem       = (int)$item->kKonfigitem;
        $this->kArtikel          = (int)$item->kArtikel;
        $this->nPosTyp           = (int)$item->nPosTyp;
        $this->bSelektiert       = (int)$item->bSelektiert;
        $this->bEmpfohlen        = (int)$item->bEmpfohlen;
        $this->bName             = (int)$item->bName;
        $this->bPreis            = (int)$item->bPreis;
        $this->bRabatt           = (int)$item->bRabatt;
        $this->bZuschlag         = (int)$item->bZuschlag;
        $this->bIgnoreMultiplier = (int)$item->bIgnoreMultiplier;
        $this->fMin              = (float)$item->fMin;
        $this->fMax              = (float)$item->fMax;
        $this->fInitial          = (float)$item->fInitial;
        $this->nSort             = (int)$item->nSort;
        $this->kSprache          = $languageID;
        $this->kKundengruppe     = $customerGroupID;
        $this->oSprache          = new ItemLocalization($this->kKonfigitem, $languageID);
        $this->oPreis            = new ItemPrice($this->kKonfigitem, $customerGroupID);
        $this->oArtikel          = null;
        if ($this->kArtikel > 0) {
            $this->addProduct($customerGroupID, $languageID);
        }

        return $this;
    }

    private function addProduct(int $customerGroupID, int $languageID): void
    {
        $options                             = Artikel::getDefaultOptions();
        $options->nKeineSichtbarkeitBeachten = 1;

        $this->oArtikel = new Artikel();
        $this->oArtikel->fuelleArtikel($this->kArtikel, $options, $customerGroupID, $languageID);
    }

    public function isValid(): bool
    {
        return !($this->kArtikel > 0 && empty($this->oArtikel->kArtikel));
    }

    /**
     * @return Item[]
     */
    public static function fetchAll(int $groupID, int $languageID = 0): array
    {
        $customerGroupID = Frontend::getCustomerGroup()->getID();

        return Shop::Container()->getDB()->getCollection(
            'SELECT *
                FROM tkonfigitem 
                WHERE kKonfiggruppe = :groupID 
                ORDER BY nSort ASC',
            ['groupID' => $groupID]
        )
            ->map(fn(stdClass $item): self => (new self())->assignData($item, $languageID, $customerGroupID))
            ->filter(fn(Item $item): bool => $item->isValid())
            ->toArray();
    }

    public function setKonfigitem(int $id): self
    {
        $this->kKonfigitem = $id;

        return $this;
    }

    public function setArtikelKey(int $productID): self
    {
        $this->kArtikel = $productID;

        return $this;
    }

    public function setArtikel(Artikel $product): self
    {
        $this->oArtikel = $product;

        return $this;
    }

    public function setPosTyp(int $type): self
    {
        $this->nPosTyp = $type;

        return $this;
    }

    public function getKonfigitem(): int
    {
        return (int)$this->kKonfigitem;
    }

    public function getKonfiggruppe(): int
    {
        return $this->kKonfiggruppe;
    }

    public function getArtikelKey(): int
    {
        return $this->kArtikel;
    }

    public function getArtikel(): ?Artikel
    {
        return $this->oArtikel;
    }

    public function getPosTyp(): ?int
    {
        return $this->nPosTyp;
    }

    public function getSelektiert(): ?int
    {
        return $this->bSelektiert;
    }

    public function getEmpfohlen(): ?int
    {
        return $this->bEmpfohlen;
    }

    public function getSprache(): ?ItemLocalization
    {
        return $this->oSprache;
    }

    public function getName(): ?string
    {
        if ($this->oArtikel && $this->bName) {
            return $this->oArtikel->cName;
        }

        return $this->oSprache
            ? $this->oSprache->getName()
            : '';
    }

    public function getBeschreibung(): ?string
    {
        if ($this->oArtikel && $this->bName) {
            return $this->oArtikel->cBeschreibung;
        }

        return $this->oSprache
            ? $this->oSprache->getBeschreibung()
            : '';
    }

    public function getKurzBeschreibung(): ?string
    {
        if ($this->oArtikel && $this->bName) {
            return $this->oArtikel->cKurzBeschreibung;
        }

        return $this->oSprache
            ? $this->oSprache->getBeschreibung()
            : '';
    }

    public function getBildPfad(): ?string
    {
        return $this->oArtikel && $this->oArtikel->Bilder[0]->cPfadKlein !== \BILD_KEIN_ARTIKELBILD_VORHANDEN
            ? $this->oArtikel->Bilder[0]->cPfadKlein
            : null;
    }

    public function getUseOwnName(): bool
    {
        return !$this->bName;
    }

    public function getPreis(bool $forceNet = false, bool $convertCurrency = false): float|int
    {
        $fVKPreis    = 0.0;
        $isConverted = false;
        if ($this->oArtikel && $this->bPreis && $this->oPreis !== null) {
            $fVKPreis = $this->oArtikel->Preise->fVKNetto ?? 0.0;
            $fSpecial = $this->oPreis->getPreis($convertCurrency);
            if ($fSpecial !== null && $fSpecial !== 0.0) {
                if ($this->oPreis->getTyp() === ItemPrice::PRICE_TYPE_SUM) {
                    $fVKPreis += $fSpecial;
                } elseif ($this->oPreis->getTyp() === ItemPrice::PRICE_TYPE_PERCENTAGE) {
                    $fVKPreis *= (100 + $fSpecial) / 100;
                }
            }
        } elseif ($this->oPreis?->getPreis() !== null) {
            $fVKPreis    = $this->oPreis->getPreis($convertCurrency);
            $isConverted = true;
        }
        if ($convertCurrency && !$isConverted) {
            $fVKPreis *= Frontend::getCurrency()->getConversionFactor();
        }
        if (!$forceNet && !Frontend::getCustomerGroup()->isMerchant()) {
            $fVKPreis = Tax::getGross($fVKPreis, Tax::getSalesTax($this->getSteuerklasse()), 4);
        }

        \executeHook(\HOOK_CONFIG_ITEM_GETPREIS, [
            'configItem' => $this,
            'fVKPreis'   => &$fVKPreis,
        ]);

        return $fVKPreis;
    }

    public function getFullPrice(bool $forceNet = false, bool $convertCurrency = false, int $totalAmount = 1): float|int
    {
        return $this->getPreis($forceNet, $convertCurrency) * $this->fAnzahl * $totalAmount;
    }

    public function hasPreis(): bool
    {
        return (int)$this->getPreis(true) !== 0;
    }

    public function hasRabatt(): bool
    {
        return $this->getRabatt() > 0;
    }

    public function getRabatt(): float
    {
        $discount = 0.0;
        if ($this->oArtikel && $this->bPreis && $this->oPreis !== null) {
            $tmp = $this->oPreis->getPreis();
            if ($tmp < 0) {
                $discount = $tmp * -1;
                if (
                    $this->oPreis->getTyp() === ItemPrice::PRICE_TYPE_SUM
                    && !Frontend::getCustomerGroup()->isMerchant()
                ) {
                    $discount = Tax::getGross($discount, Tax::getSalesTax($this->getSteuerklasse()));
                }
            }
        }

        return $discount;
    }

    public function hasZuschlag(): bool
    {
        return $this->getZuschlag() > 0;
    }

    public function getZuschlag(): float
    {
        $fee = 0.0;
        if ($this->oArtikel && $this->bPreis && $this->oPreis !== null) {
            $tmp = $this->oPreis->getPreis();
            if ($tmp > 0) {
                $fee = $tmp;
                if (
                    $this->oPreis->getTyp() === ItemPrice::PRICE_TYPE_SUM
                    && !Frontend::getCustomerGroup()->isMerchant()
                ) {
                    $fee = Tax::getGross($fee, Tax::getSalesTax($this->getSteuerklasse()));
                }
            }
        }

        return $fee;
    }

    public function getRabattLocalized(bool $html = true): string
    {
        return $this->oPreis !== null && $this->oPreis->getTyp() === ItemPrice::PRICE_TYPE_SUM
            ? Preise::getLocalizedPriceString($this->getRabatt(), null, $html)
            : $this->getRabatt() . '%';
    }

    public function getZuschlagLocalized(bool $html = true): string
    {
        return $this->oPreis !== null && $this->oPreis->getTyp() === ItemPrice::PRICE_TYPE_SUM
            ? Preise::getLocalizedPriceString($this->getZuschlag(), null, $html)
            : $this->getZuschlag() . '%';
    }

    public function getSteuerklasse(): int
    {
        $kSteuerklasse = 0;
        if ($this->oArtikel && $this->bPreis) {
            $kSteuerklasse = $this->oArtikel->kSteuerklasse;
        } elseif ($this->oPreis) {
            $kSteuerklasse = $this->oPreis->getSteuerklasse();
        }

        return $kSteuerklasse;
    }

    public function getPreisLocalized(bool $html = true, bool $signed = true, bool $bForceNetto = false): string
    {
        $localized = Preise::getLocalizedPriceString($this->getPreis($bForceNetto), false, $html);
        if ($signed && $this->getPreis() > 0) {
            $localized = '+' . $localized;
        }

        return $localized;
    }

    public function getFullPriceLocalized(bool $html = true, bool $forceNet = false, int $totalAmount = 1): string
    {
        return Preise::getLocalizedPriceString($this->getFullPrice($forceNet, false, $totalAmount), 0, $html);
    }

    public function getMin(): float
    {
        return $this->fMin;
    }

    public function getMax(): float
    {
        return $this->fMax;
    }

    public function getInitial(): float
    {
        if ($this->fInitial < 0) {
            $this->fInitial = 0.0;
        }
        if ($this->fInitial < $this->getMin()) {
            $this->fInitial = $this->getMin();
        }
        if ($this->fInitial > $this->getMax()) {
            $this->fInitial = $this->getMax();
        }

        return $this->fInitial;
    }

    public function showRabatt(): ?int
    {
        return $this->bRabatt;
    }

    public function showZuschlag(): ?int
    {
        return $this->bZuschlag;
    }

    public function ignoreMultiplier(): ?int
    {
        return $this->bIgnoreMultiplier;
    }

    public function getSprachKey(): ?int
    {
        return $this->kSprache;
    }

    public function getKundengruppe(): ?int
    {
        return $this->kKundengruppe;
    }

    public function isInStock(): bool
    {
        $tmpPro = $this->getArtikel();
        if ($tmpPro === null) {
            return true;
        }

        return empty($this->kArtikel)
            || (!($tmpPro->cLagerBeachten === 'Y'
                && $tmpPro->cLagerKleinerNull === 'N'
                && (float)$tmpPro->fLagerbestand < $this->fMin));
    }

    /**
     * @param array<int, mixed>|bool $configItemCounts
     * @param array<int, mixed>      $configGroupCounts
     */
    public function setQuantities(int|float $amount, array|bool $configItemCounts, array $configGroupCounts): self
    {
        $this->fAnzahl = (float)($configItemCounts[$this->kKonfigitem]
            ?? $configGroupCounts[$this->getKonfiggruppe()] ?? $this->getInitial());
        if ($configItemCounts && isset($configItemCounts[$this->getKonfigitem()])) {
            $this->fAnzahl = (float)$configItemCounts[$this->getKonfigitem()];
        }
        // Todo: Mindestbestellanzahl / Abnahmeinterval beachten
        if ($this->fAnzahl < 1) {
            $this->fAnzahl = 1.0;
        }
        $count           = \max($amount, 1);
        $this->fAnzahlWK = $this->fAnzahl;
        if (!$this->ignoreMultiplier()) {
            $this->fAnzahlWK *= $count;
        }

        return $this;
    }
}
