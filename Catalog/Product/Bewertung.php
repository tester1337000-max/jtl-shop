<?php

declare(strict_types=1);

namespace JTL\Catalog\Product;

use JTL\Session\Frontend;
use JTL\Shop;
use stdClass;

use function Functional\each;

/**
 * Class Bewertung
 * @package JTL\Catalog\Product
 */
class Bewertung
{
    /**
     * @var stdClass[]|null
     */
    public ?array $oBewertung_arr = null;

    /**
     * @var int[]|null
     */
    public ?array $nSterne_arr = null;

    public int $nAnzahlSprache = 0;

    /**
     * @var (object{nAnzahl: int, fDurchschnitt: float}&stdClass)|null
     */
    public stdClass|null $oBewertungGesamt = null;

    public int $Sortierung = 0;

    public function __construct(
        int $productID,
        int $languageID,
        int $pageOffset = -1,
        int $page = 1,
        int $stars = 0,
        string $activate = 'N',
        int $option = 0,
        bool $allLanguages = false
    ) {
        if (!$languageID) {
            $languageID = Shop::getLanguageID();
        }
        if ($option === 1) {
            $this->holeHilfreichsteBewertung($productID, $languageID, $allLanguages);
        } else {
            $this->holeProduktBewertungen(
                $productID,
                $languageID,
                $pageOffset,
                $page,
                $stars,
                $activate,
                $option,
                $allLanguages
            );
        }
    }

    public function holeHilfreichsteBewertung(int $productID, int $languageID, bool $allLanguages = false): self
    {
        $this->oBewertung_arr = [];
        if ($productID > 0 && $languageID > 0) {
            $langSQL = $allLanguages ? '' : ' AND tbewertung.kSprache = ' . $languageID . ' ';
            $data    = Shop::Container()->getDB()->getSingleObject(
                "SELECT DISTINCT tbewertung.*,
                        DATE_FORMAT(tbewertung.dDatum, '%d.%m.%Y') AS Datum,
                        DATE_FORMAT(tbewertung.dAntwortDatum, '%d.%m.%Y') AS AntwortDatum,
                        tbewertunghilfreich.nBewertung AS rated,
                        IF(tbewertungguthabenbonus.kBewertungGuthabenBonus, 1, 0) as hasCreditBonus,
                        IF(tbestellung.kKunde IS NOT NULL, 1, 0) AS wasPurchased
                    FROM tbewertung
                    LEFT JOIN tbewertunghilfreich
                      ON tbewertung.kBewertung = tbewertunghilfreich.kBewertung
                      AND tbewertunghilfreich.kKunde = :customerID
                    LEFT JOIN (
                        tbestellung
                        INNER JOIN twarenkorbpos ON tbestellung.kWarenkorb = twarenkorbpos.kWarenkorb
                    ) ON tbestellung.kKunde = tbewertung.kKunde AND twarenkorbpos.kArtikel = tbewertung.kArtikel
                    LEFT JOIN tbewertungguthabenbonus
                      ON tbewertung.kBewertung = tbewertungguthabenbonus.kBewertung
                    WHERE tbewertung.kArtikel = :pid"
                . $langSQL . '
                        AND tbewertung.nAktiv = 1
                    ORDER BY tbewertung.nHilfreich DESC
                    LIMIT 1',
                ['customerID' => Frontend::getCustomer()->getID(), 'pid' => $productID]
            );
            if ($data !== null) {
                $this->sanitizeRatingData($data);
                $data->nAnzahlHilfreich = $data->nHilfreich + $data->nNichtHilfreich;
            }

            \executeHook(\HOOK_BEWERTUNG_CLASS_HILFREICHSTEBEWERTUNG);
            $this->oBewertung_arr[] = $data;
        }

        return $this;
    }

    public function sanitizeRatingData(stdClass $item): void
    {
        $item->kBewertung      = (int)$item->kBewertung;
        $item->kArtikel        = (int)$item->kArtikel;
        $item->kKunde          = (int)$item->kKunde;
        $item->kSprache        = (int)$item->kSprache;
        $item->nHilfreich      = (int)$item->nHilfreich;
        $item->nNichtHilfreich = (int)$item->nNichtHilfreich;
        $item->nSterne         = (int)$item->nSterne;
        $item->nAktiv          = (int)$item->nAktiv;
    }

    private function getOrderSQL(int $option): string
    {
        return match ($option) {
            3       => ' tbewertung.dDatum ASC',
            4       => ' tbewertung.nSterne DESC',
            5       => ' tbewertung.nSterne ASC',
            6       => ' tbewertung.nHilfreich DESC',
            7       => ' tbewertung.nHilfreich ASC',
            default => ' tbewertung.dDatum DESC',
        };
    }

    public function holeProduktBewertungen(
        int $productID,
        int $languageID,
        int $pageOffset,
        int $page = 1,
        int $stars = 0,
        string $activate = 'N',
        int $option = 0,
        bool $allLanguages = false
    ): self {
        $this->oBewertung_arr = [];
        if ($productID <= 0 || $languageID <= 0) {
            return $this;
        }
        $ratingCounts = [];
        $condSQL      = '';
        $orderSQL     = $this->getOrderSQL($option);
        $db           = Shop::Container()->getDB();
        \executeHook(\HOOK_BEWERTUNG_CLASS_SWITCH_SORTIERUNG);

        $activateSQL = $activate === 'Y'
            ? ' AND tbewertung.nAktiv = 1'
            : '';
        $langSQL     = $allLanguages ? '' : ' AND tbewertung.kSprache = ' . $languageID;
        // Anzahl Bewertungen für jeden Stern unabhängig von Sprache SHOP-2313
        if ($stars !== -1) {
            if ($stars > 0) {
                $condSQL = ' AND tbewertung.nSterne = ' . $stars;
            }
            $ratingCounts = $db->getObjects(
                'SELECT COUNT(*) AS nAnzahl, nSterne
                    FROM tbewertung
                    WHERE kArtikel = :pid' . $activateSQL . '
                    GROUP BY nSterne
                    ORDER BY nSterne DESC',
                ['pid' => $productID]
            );
        }
        if ($page > 0) {
            $limitSQL = '';
            if ($pageOffset > 0) {
                $limitSQL = ($page > 1)
                    ? ' LIMIT ' . (($page - 1) * $pageOffset) . ', ' . $pageOffset
                    : ' LIMIT ' . $pageOffset;
            }
            $this->oBewertung_arr = $db->getObjects(
                "SELECT DISTINCT tbewertung.*,
                        DATE_FORMAT(tbewertung.dDatum, '%d.%m.%Y') AS Datum,
                        DATE_FORMAT(tbewertung.dAntwortDatum, '%d.%m.%Y') AS AntwortDatum,
                        tbewertunghilfreich.nBewertung AS rated,
                        IF(tbewertungguthabenbonus.kBewertungGuthabenBonus, 1, 0) as hasCreditBonus,
                        IF(EXISTS (SELECT 1
                                   FROM tbestellung
                                   INNER JOIN twarenkorbpos ON tbestellung.kWarenkorb = twarenkorbpos.kWarenkorb
                                   INNER JOIN tartikel ON twarenkorbpos.kArtikel = tartikel.kArtikel
                                   WHERE tbestellung.kKunde = tbewertung.kKunde
                                        AND tbewertung.kArtikel IN (tartikel.kArtikel,
                                                                    tartikel.kVaterArtikel)), 1, 0) AS wasPurchased
                    FROM tbewertung
                    LEFT JOIN tbewertunghilfreich
                      ON tbewertung.kBewertung = tbewertunghilfreich.kBewertung
                      AND tbewertunghilfreich.kKunde = :customerID
                    LEFT JOIN tbewertungguthabenbonus
                      ON tbewertung.kBewertung = tbewertungguthabenbonus.kBewertung
                    WHERE tbewertung.kArtikel = :pid" . $langSQL . $condSQL . $activateSQL . '
                    ORDER BY' . $orderSQL . $limitSQL,
                ['customerID' => Frontend::getCustomer()->getID(), 'pid' => $productID]
            );
            each($this->oBewertung_arr, $this->sanitizeRatingData(...));
        }
        $total = $db->getSingleObject(
            'SELECT COUNT(*) AS nAnzahl, tartikelext.fDurchschnittsBewertung AS fDurchschnitt
                FROM tartikelext
                JOIN tbewertung 
                    ON tbewertung.kArtikel = tartikelext.kArtikel
                WHERE tartikelext.kArtikel = :pid' . $activateSQL . '
                GROUP BY tartikelext.kArtikel',
            ['pid' => $productID]
        );
        // Anzahl Bewertungen für aktuelle Sprache
        $totalLocalized = $db->getSingleObject(
            'SELECT COUNT(*) AS nAnzahlSprache
                FROM tbewertung
                WHERE kArtikel = :pid' . $langSQL . $activateSQL,
            ['pid' => $productID]
        );
        if ($total !== null && (int)$total->fDurchschnitt > 0) {
            $total->fDurchschnitt = \round($total->fDurchschnitt * 2) / 2;
            $total->nAnzahl       = (int)$total->nAnzahl;
        } else {
            $total                = new stdClass();
            $total->fDurchschnitt = 0;
            $total->nAnzahl       = 0;
        }
        $this->oBewertungGesamt = $total;
        $this->nAnzahlSprache   = (int)($totalLocalized->nAnzahlSprache ?? 0);
        foreach ($this->oBewertung_arr as $i => $rating) {
            $this->oBewertung_arr[$i]->nAnzahlHilfreich = $rating->nHilfreich + $rating->nNichtHilfreich;
        }
        $this->nSterne_arr = [0, 0, 0, 0, 0];
        foreach ($ratingCounts as $item) {
            $this->nSterne_arr[5 - (int)$item->nSterne] = (int)$item->nAnzahl;
        }
        \executeHook(\HOOK_BEWERTUNG_CLASS_BEWERTUNG, ['oBewertung' => &$this]);

        return $this;
    }
}
