<?php

declare(strict_types=1);

namespace JTL;

use JTL\DB\DbInterface;
use stdClass;

/**
 * Class ExtensionPoint
 * @package JTL
 */
class ExtensionPoint
{
    /**
     * @param int          $nSeitenTyp
     * @param array<mixed> $cParam_arr
     * @param int          $kSprache
     * @param int          $kKundengruppe
     */
    public function __construct(
        protected int $nSeitenTyp,
        protected array $cParam_arr,
        protected int $kSprache,
        protected int $kKundengruppe
    ) {
    }

    public function load(?DbInterface $db = null): self
    {
        $db         = $db ?? Shop::Container()->getDB();
        $key        = $this->getPageKey();
        $extensions = $db->getObjects(
            "SELECT cClass, kInitial FROM textensionpoint
                WHERE (kSprache = :lid OR kSprache = 0)
                    AND (kKundengruppe = :cgid OR kKundengruppe = 0)
                    AND (nSeite = :ptype OR nSeite = 0)
                    AND ( (cKey = :cky AND (cValue = :cval OR cValue = '')) OR cValue = '')",
            [
                'lid'   => $this->kSprache,
                'cgid'  => $this->kKundengruppe,
                'ptype' => $this->nSeitenTyp,
                'cky'   => $key->cKey,
                'cval'  => $key->cValue
            ]
        );
        foreach ($extensions as $extension) {
            $instance = null;
            /** @var class-string<IExtensionPoint> $class */
            $class = \ucfirst($extension->cClass);
            if (!\class_exists($class)) {
                $class = '\\JTL\\' . $class;
            }
            if (\class_exists($class)) {
                /** @var IExtensionPoint $instance */
                $instance = new $class($db);
                $instance->init((int)$extension->kInitial);
            } else {
                Shop::Container()->getLogService()->error('Extension {ext} not found', ['ext' => $class]);
            }
        }

        return $this;
    }

    /**
     * @return stdClass
     */
    public function getPageKey(): stdClass
    {
        $key         = new stdClass();
        $key->cValue = '';
        $key->cKey   = null;
        $key->nPage  = $this->nSeitenTyp;

        switch ($key->nPage) {
            case \PAGE_ARTIKEL:
                $key->cKey   = 'kArtikel';
                $key->cValue = isset($this->cParam_arr['kArtikel']) ? (int)$this->cParam_arr['kArtikel'] : null;
                break;

            case \PAGE_NEWS:
                if (isset($this->cParam_arr['kNewsKategorie']) && (int)$this->cParam_arr['kNewsKategorie'] > 0) {
                    $key->cKey   = 'kNewsKategorie';
                    $key->cValue = (int)$this->cParam_arr['kNewsKategorie'];
                } else {
                    $key->cKey   = 'kNews';
                    $key->cValue = isset($this->cParam_arr['kNews']) ? (int)$this->cParam_arr['kNews'] : null;
                }
                break;

            case \PAGE_BEWERTUNG:
                $key->cKey   = 'kArtikel';
                $key->cValue = (int)$this->cParam_arr['kArtikel'];
                break;

            case \PAGE_EIGENE:
                $key->cKey   = 'kLink';
                $key->cValue = (int)$this->cParam_arr['kLink'];
                break;

            case \PAGE_ARTIKELLISTE:
                $productFilter = Shop::getProductFilter();
                // MerkmalWert
                if ($productFilter->hasCharacteristicValue()) {
                    $key->cKey   = 'kMerkmalWert';
                    $key->cValue = $productFilter->getCharacteristicValue()->getValue();
                } elseif ($productFilter->hasCategory()) {
                    // Kategorie
                    $key->cKey   = 'kKategorie';
                    $key->cValue = $productFilter->getCategory()->getValue();
                } elseif ($productFilter->hasManufacturer()) {
                    // Hersteller
                    $key->cKey   = 'kHersteller';
                    $key->cValue = $productFilter->getManufacturer()->getValue();
                } elseif ($productFilter->hasSearch()) {
                    // Suchbegriff
                    $key->cKey   = 'cSuche';
                    $key->cValue = $productFilter->getSearch()->getValue();
                } elseif ($productFilter->hasSearchSpecial()) {
                    // Suchspecial
                    $key->cKey   = 'kSuchspecial';
                    $key->cValue = $productFilter->getSearchSpecial()->getValue();
                }

                break;

            default:
                break;
        }

        return $key;
    }
}
