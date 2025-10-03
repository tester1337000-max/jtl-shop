<?php

declare(strict_types=1);

namespace JTL\Catalog\Product;

use JTL\Cache\JTLCacheInterface;
use JTL\Catalog\Category\KategorieListe;
use JTL\Catalog\Category\MenuItem;
use JTL\Customer\CustomerGroup;
use JTL\DB\DbInterface;
use JTL\Helpers\GeneralObject;
use JTL\Helpers\Product;
use JTL\Session\Frontend;
use JTL\Settings\Option\Overview;
use JTL\Settings\Settings;
use JTL\Shop;

use function Functional\map;

/**
 * Class ArtikelListe
 * @package JTL\Catalog\Product
 */
class ArtikelListe
{
    /**
     * Array mit Artikeln
     *
     * @var Artikel[]
     */
    public array $elemente = [];

    private JTLCacheInterface $cache;

    private DbInterface $db;

    public function __construct(?DbInterface $db = null, ?JTLCacheInterface $cache = null)
    {
        $this->db    = $db ?? Shop::Container()->getDB();
        $this->cache = $cache ?? Shop::Container()->getCache();
    }

    /**
     * @return Artikel[]
     */
    public function getTopNeuArtikel(
        string $topneu,
        int $limit = 3,
        int $customerGroupID = 0,
        int $languageID = 0
    ): array {
        $this->elemente = [];
        if (!Frontend::getCustomerGroup()->mayViewCategories()) {
            return $this->elemente;
        }
        $cacheID = 'jtl_tpnw_' . $topneu
            . '_' . $limit
            . '_' . $languageID
            . '_' . $customerGroupID;
        $items   = $this->cache->get($cacheID);
        if ($items === false) {
            $qry = ($topneu === 'neu')
                ? "cNeu = 'Y'"
                : "tartikel.cTopArtikel = 'Y'";
            if (!$customerGroupID) {
                $customerGroupID = Frontend::getCustomerGroup()->getID();
            }
            $items = $this->db->getObjects(
                'SELECT tartikel.kArtikel
                    FROM tartikel
                    LEFT JOIN tartikelsichtbarkeit 
                        ON tartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                        AND tartikelsichtbarkeit.kKundengruppe = :cgid
                    WHERE tartikelsichtbarkeit.kArtikel IS NULL
                        AND ' . $qry . '
                    ORDER BY rand() LIMIT ' . $limit,
                ['cgid' => $customerGroupID]
            );
            $this->cache->set($cacheID, $items, [\CACHING_GROUP_CATEGORY]);
        }
        if (\is_array($items)) {
            $defaultOptions = Artikel::getDefaultOptions();
            $currency       = Frontend::getCurrency();
            $customerGroup  = CustomerGroup::getByID($customerGroupID);
            foreach ($items as $item) {
                $product = new Artikel($this->db, $customerGroup, $currency, $this->cache);
                $product->fuelleArtikel((int)$item->kArtikel, $defaultOptions, $customerGroupID, $languageID);
                $this->elemente[] = $product;
            }
        }

        return $this->elemente;
    }

    /**
     * @return Artikel[]
     */
    public function getArtikelFromKategorie(
        int $categoryID,
        int $limitStart,
        int $limitAnzahl,
        string $order,
        int $customerGroupID = 0,
        int $languageID = 0
    ): array {
        $this->elemente = [];
        if (!$categoryID || !Frontend::getCustomerGroup()->mayViewCategories()) {
            return $this->elemente;
        }
        if (!$customerGroupID) {
            $customerGroupID = Frontend::getCustomerGroup()->getID();
        }
        if (!$languageID) {
            $languageID = Shop::getLanguageID();
        }
        $cacheID = 'jtl_top_' . \md5($categoryID . $limitStart . $limitAnzahl . $customerGroupID . $languageID);
        if (($res = $this->cache->get($cacheID)) !== false) {
            $this->elemente = $res;
        } else {
            $productFilter = Shop::getProductFilter();
            $conditionSQL  = '';
            if ($productFilter->hasManufacturer()) {
                $conditionSQL = ' AND tartikel.kHersteller = ' . (int)$productFilter->getManufacturer()->getValue()
                    . ' ';
            }
            $stockFilterSQL = $productFilter->getFilterSQL()->getStockFilterSQL();
            $items          = $this->db->getObjects(
                'SELECT tartikel.kArtikel
                    FROM tkategorieartikel, tartikel
                    LEFT JOIN tartikelsichtbarkeit
                        ON tartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                        AND tartikelsichtbarkeit.kKundengruppe = :cgid '
                . Preise::getPriceJoinSql($customerGroupID) . '
                    WHERE tartikelsichtbarkeit.kArtikel IS NULL
                        AND tartikel.kArtikel = tkategorieartikel.kArtikel ' . $conditionSQL . ' 
                        AND tkategorieartikel.kKategorie = :cid ' . $stockFilterSQL . '
                    ORDER BY ' . $order . ', nSort
                    LIMIT :lmts, :lmte',
                [
                    'cgid' => $customerGroupID,
                    'cid'  => $categoryID,
                    'lmts' => $limitStart,
                    'lmte' => $limitAnzahl
                ]
            );
            $defaultOptions = Artikel::getDefaultOptions();
            $currency       = Frontend::getCurrency();
            $customerGroup  = CustomerGroup::getByID($customerGroupID);
            foreach ($items as $item) {
                $product = new Artikel($this->db, $customerGroup, $currency, $this->cache);
                $product->fuelleArtikel((int)$item->kArtikel, $defaultOptions, $customerGroupID, $languageID);
                if ($product->kArtikel > 0) {
                    $this->elemente[] = $product;
                }
            }
            $this->cache->set(
                $cacheID,
                $this->elemente,
                [\CACHING_GROUP_CATEGORY, \CACHING_GROUP_CATEGORY . '_' . $categoryID]
            );
        }

        return $this->elemente;
    }

    /**
     * @param int[] $productIDs
     * @return Artikel[]
     */
    public function getArtikelByKeys(array $productIDs, int $start, int $limit): array
    {
        $this->elemente = [];
        if (!Frontend::getCustomerGroup()->mayViewCategories()) {
            return $this->elemente;
        }
        $cnt             = \count($productIDs);
        $total           = 0;
        $defaultOptions  = Artikel::getDefaultOptions();
        $languageID      = Shop::getLanguageID();
        $customerGroup   = Frontend::getCustomerGroup();
        $customerGroupID = Frontend::getCustomerGroup()->getID();
        $currency        = Frontend::getCurrency();
        for ($i = $start; $i < $cnt; $i++) {
            $product = new Artikel($this->db, $customerGroup, $currency, $this->cache);
            $product->fuelleArtikel($productIDs[$i], $defaultOptions, $customerGroupID, $languageID);
            if ($product->kArtikel > 0) {
                ++$total;
                $this->elemente[] = $product;
            }
            if ($total >= $limit) {
                break;
            }
        }
        $this->elemente = Product::separateByAvailability($this->elemente, true);

        return $this->elemente;
    }

    /**
     * @return Artikel[]
     */
    public function holeTopArtikel(KategorieListe $categoryList): array
    {
        if (!Frontend::getCustomerGroup()->mayViewCategories()) {
            return $this->elemente;
        }
        $categoryIDs = [];
        $i           = 0;
        if (!empty($categoryList->elemente)) {
            /** @var MenuItem $category */
            foreach ($categoryList->elemente as $category) {
                $categoryIDs[] = $category->getID();
                if (++$i > \PRODUCT_LIST_CATEGORY_LIMIT) {
                    break;
                }
                if ($category->hasChildren()) {
                    foreach ($category->getChildren() as $level2) {
                        $categoryIDs[] = $level2->getID();
                        if (++$i > \PRODUCT_LIST_CATEGORY_LIMIT) {
                            break;
                        }
                    }
                }
            }
        }
        $cacheID         = 'hTA_' . \md5(\json_encode($categoryIDs, \JSON_THROW_ON_ERROR));
        $items           = $this->cache->get($cacheID);
        $customerGroupID = Frontend::getCustomerGroup()->getID();
        if ($items === false && \count($categoryIDs) > 0) {
            $limit          = Settings::intValue(Overview::TOP_ITEMS_QTY);
            $stockFilterSQL = Shop::getProductFilter()->getFilterSQL()->getStockFilterSQL();
            $items          = $this->db->getObjects(
                'SELECT DISTINCT (tartikel.kArtikel)
                    FROM tkategorieartikel, tartikel
                    LEFT JOIN tartikelsichtbarkeit
                        ON tartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                        AND tartikelsichtbarkeit.kKundengruppe = :cgid '
                . Preise::getPriceJoinSql($customerGroupID) . " 
                    WHERE tartikelsichtbarkeit.kArtikel IS NULL
                        AND tartikel.kArtikel = tkategorieartikel.kArtikel
                        AND tartikel.cTopArtikel = 'Y'
                        AND (tkategorieartikel.kKategorie IN (" . \implode(', ', $categoryIDs) . ')) '
                . $stockFilterSQL . '  ORDER BY rand() LIMIT ' . $limit,
                ['cgid' => $customerGroupID]
            );
            $cacheTags      = [\CACHING_GROUP_CATEGORY, \CACHING_GROUP_OPTION];
            foreach ($categoryIDs as $id) {
                $cacheTags[] = \CACHING_GROUP_CATEGORY . '_' . $id;
            }
            $this->cache->set($cacheID, $items, $cacheTags);
        }
        if ($items === false) {
            return $this->elemente;
        }
        $defaultOptions = Artikel::getDefaultOptions();
        $languageID     = Shop::getLanguageID();
        $currency       = Frontend::getCurrency();
        $customerGroup  = CustomerGroup::getByID($customerGroupID);
        foreach ($items as $obj) {
            $product = new Artikel($this->db, $customerGroup, $currency, $this->cache);
            $product->fuelleArtikel((int)$obj->kArtikel, $defaultOptions, $customerGroupID, $languageID);
            if ($product->kArtikel > 0) {
                $this->elemente[] = $product;
            }
        }

        return $this->elemente;
    }

    /**
     * @return Artikel[]
     */
    public function holeBestsellerArtikel(KategorieListe $categoryList, ?ArtikelListe $topProductsList = null): array
    {
        if (!Frontend::getCustomerGroup()->mayViewCategories()) {
            return $this->elemente;
        }
        $categoryIDs = [];
        if (GeneralObject::isCountable('elemente', $categoryList)) {
            $i = 0;
            foreach ($categoryList->elemente as $category) {
                /** @var MenuItem $category */
                $categoryIDs[] = $category->getID();
                if (++$i > \PRODUCT_LIST_CATEGORY_LIMIT) {
                    break;
                }
                if ($category->hasChildren()) {
                    foreach ($category->getChildren() as $level2) {
                        $categoryIDs[] = $level2->getID();
                        if (++$i > \PRODUCT_LIST_CATEGORY_LIMIT) {
                            break;
                        }
                    }
                }
            }
        }
        $keys = null;
        if ($topProductsList instanceof self) {
            $keys = map($topProductsList->elemente, fn($e) => $e->cacheID ?? 0);
        }
        $cacheID         = 'hBsA_'
            . \md5(\json_encode($categoryIDs, \JSON_THROW_ON_ERROR) . \json_encode($keys, \JSON_THROW_ON_ERROR));
        $items           = $this->cache->get($cacheID);
        $customerGroupID = Frontend::getCustomerGroup()->getID();
        if ($items === false && \count($categoryIDs) > 0) {
            // top artikel nicht nochmal in den bestsellen vorkommen lassen
            $excludes = '';
            if (GeneralObject::isCountable('elemente', $topProductsList)) {
                $exclude  = map($topProductsList->elemente, fn($e): int => (int)$e->kArtikel);
                $excludes = \count($exclude) > 0
                    ? ' AND tartikel.kArtikel NOT IN (' . \implode(',', $exclude) . ') '
                    : '';
            }
            $limit          = Settings::intValue(Overview::TOP_ITEMS_QTY);
            $stockFilterSQL = Shop::getProductFilter()->getFilterSQL()->getStockFilterSQL();
            $items          = $this->db->getObjects(
                'SELECT DISTINCT (tartikel.kArtikel)
                    FROM tkategorieartikel, tbestseller, tartikel
                    LEFT JOIN tartikelsichtbarkeit
                        ON tartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                        AND tartikelsichtbarkeit.kKundengruppe = :cgid ' . Preise::getPriceJoinSql($customerGroupID) . '
                    WHERE tartikelsichtbarkeit.kArtikel IS NULL' . $excludes . '
                        AND tartikel.kArtikel = tkategorieartikel.kArtikel
                        AND tartikel.kArtikel = tbestseller.kArtikel
                        AND (tkategorieartikel.kKategorie IN (' . \implode(', ', $categoryIDs) . '))
                        AND tbestseller.isBestseller = 1 '
                . $stockFilterSQL . '
                    ORDER BY tbestseller.fAnzahl DESC LIMIT ' . $limit,
                ['cgid' => $customerGroupID]
            );
            $cacheTags      = [\CACHING_GROUP_CATEGORY, \CACHING_GROUP_OPTION];
            foreach ($categoryIDs as $id) {
                $cacheTags[] = \CACHING_GROUP_CATEGORY . '_' . $id;
            }
            $this->cache->set($cacheID, $items, $cacheTags);
        }
        if (\is_array($items)) {
            $defaultOptions = Artikel::getDefaultOptions();
            $languageID     = Shop::getLanguageID();
            $currency       = Frontend::getCurrency();
            $customerGroup  = CustomerGroup::getByID($customerGroupID);
            foreach ($items as $item) {
                $product = new Artikel($this->db, $customerGroup, $currency, $this->cache);
                $product->fuelleArtikel((int)$item->kArtikel, $defaultOptions, $customerGroupID, $languageID);
                if ($product->kArtikel > 0) {
                    $this->elemente[] = $product;
                }
            }
        }

        return $this->elemente;
    }
}
