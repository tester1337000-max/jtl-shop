<?php

declare(strict_types=1);

namespace JTL\Cart;

use JTL\Catalog\Product\Artikel;
use JTL\Shop;
use stdClass;

/**
 * Class PersistentCartItem
 * @package JTL\Cart
 */
class PersistentCartItem
{
    public int $kWarenkorbPersPos;

    public int $kWarenkorbPers;

    public int $kArtikel;

    /**
     * @var float|int|numeric-string|null
     */
    public float|int|string|null $fAnzahl = null;

    public string $cArtikelName;

    public string $dHinzugefuegt = 'NOW()';

    public string $dHinzugefuegt_de = '';

    /**
     * @var string|false
     */
    public string|bool $cUnique;

    public string $cResponsibility;

    public int $kKonfigitem;

    public int $nPosTyp;

    /**
     * @var PersistentCartItemProperty[]
     */
    public array $oWarenkorbPersPosEigenschaft_arr = [];

    public ?string $cKommentar = null;

    public ?Artikel $Artikel = null;

    /**
     * @param string|false $unique
     */
    public function __construct(
        int $productID,
        string $productName,
        float|int|string $qty,
        int $cartItemID,
        bool|string $unique = '',
        int $configItemID = 0,
        int $type = \C_WARENKORBPOS_TYP_ARTIKEL,
        string $responsibility = 'core'
    ) {
        $this->kArtikel        = $productID;
        $this->cArtikelName    = $productName;
        $this->fAnzahl         = $qty;
        $this->kWarenkorbPers  = $cartItemID;
        $this->cUnique         = $unique;
        $this->cResponsibility = !empty($responsibility) ? $responsibility : 'core';
        $this->kKonfigitem     = $configItemID;
        $this->nPosTyp         = $type;
    }

    /**
     * @param array<mixed> $attrValues
     */
    public function erstellePosEigenschaften(array $attrValues): self
    {
        $langCode = Shop::getLanguageCode();
        foreach ($attrValues as $value) {
            if (!isset($value->kEigenschaft)) {
                continue;
            }
            $attrFreeText = null;
            if (
                isset($value->cEigenschaftWertName[$langCode], $value->cTyp)
                && ($value->cTyp === 'FREIFELD' || $value->cTyp === 'PFLICHT-FREIFELD')
            ) {
                $attrFreeText = $value->cEigenschaftWertName[$langCode];
            }
            $attr = new PersistentCartItemProperty(
                (int)$value->kEigenschaft,
                (int)($value->kEigenschaftWert ?? '0'),
                $attrFreeText ?? $value->cFreifeldWert ?? null,
                $value->cEigenschaftName[$langCode] ?? $value->cEigenschaftName ?? null,
                $value->cEigenschaftWertName[$langCode] ?? $value->cEigenschaftWertName ?? null,
                $this->kWarenkorbPersPos
            );
            $attr->schreibeDB();
            $this->oWarenkorbPersPosEigenschaft_arr[] = $attr;
        }

        return $this;
    }

    public function schreibeDB(): self
    {
        $ins                     = new stdClass();
        $ins->kWarenkorbPers     = $this->kWarenkorbPers;
        $ins->kArtikel           = $this->kArtikel;
        $ins->cArtikelName       = $this->cArtikelName;
        $ins->fAnzahl            = $this->fAnzahl;
        $ins->dHinzugefuegt      = $this->dHinzugefuegt;
        $ins->cUnique            = $this->cUnique;
        $ins->cResponsibility    = !empty($this->cResponsibility) ? $this->cResponsibility : 'core';
        $ins->kKonfigitem        = $this->kKonfigitem;
        $ins->nPosTyp            = $this->nPosTyp;
        $this->kWarenkorbPersPos = Shop::Container()->getDB()->insert('twarenkorbperspos', $ins);

        return $this;
    }

    public function updateDB(): int
    {
        $upd                    = new stdClass();
        $upd->kWarenkorbPersPos = $this->kWarenkorbPersPos;
        $upd->kWarenkorbPers    = $this->kWarenkorbPers;
        $upd->kArtikel          = $this->kArtikel;
        $upd->cArtikelName      = $this->cArtikelName;
        $upd->fAnzahl           = $this->fAnzahl;
        $upd->dHinzugefuegt     = $this->dHinzugefuegt;
        $upd->cUnique           = $this->cUnique;
        $upd->cResponsibility   = !empty($this->cResponsibility) ? $this->cResponsibility : 'core';
        $upd->kKonfigitem       = $this->kKonfigitem;
        $upd->nPosTyp           = $this->nPosTyp;

        return Shop::Container()->getDB()->update(
            'twarenkorbperspos',
            'kWarenkorbPersPos',
            $this->kWarenkorbPersPos,
            $upd
        );
    }

    public function istEigenschaftEnthalten(int $propertyID, ?int $propertyValueID, string $freeText = ''): bool
    {
        foreach ($this->oWarenkorbPersPosEigenschaft_arr as $attr) {
            if (
                (int)$attr->kEigenschaft === $propertyID
                && ((!empty($attr->kEigenschaftWert) && $attr->kEigenschaftWert === $propertyValueID)
                    || ($attr->kEigenschaftWert === 0 && $attr->cFreifeldWert === $freeText))
            ) {
                return true;
            }
        }

        return false;
    }
}
