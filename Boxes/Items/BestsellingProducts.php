<?php

declare(strict_types=1);

namespace JTL\Boxes\Items;

use JTL\Catalog\Product\ArtikelListe;
use JTL\Helpers\SearchSpecial;
use JTL\Session\Frontend;
use JTL\Shop;

/**
 * Class BestsellingProducts
 * @package JTL\Boxes\Items
 */
final class BestsellingProducts extends AbstractBox
{
    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->setShow(false);
        $customerGroupID = Frontend::getCustomerGroup()->getID();
        if ($customerGroupID === 0 || !Frontend::getCustomerGroup()->mayViewCategories()) {
            return;
        }
        $cached         = true;
        $cacheTags      = [\CACHING_GROUP_BOX, \CACHING_GROUP_ARTICLE];
        $stockFilterSQL = Shop::getProductFilter()->getFilterSQL()->getStockFilterSQL();
        $parentSQL      = ' AND tartikel.kVaterArtikel = 0';
        $cacheID        = 'bx_bsp_' . $customerGroupID . '_' . \md5($parentSQL . $stockFilterSQL);
        $cache          = Shop::Container()->getCache();
        $db             = Shop::Container()->getDB();
        /** @var int[]|false $productIDs */
        $productIDs = $cache->get($cacheID);
        if ($productIDs === false) {
            $cached = false;
            $limit  = (int)$this->config['boxen']['box_bestseller_anzahl_basis'] > 0
                ? (int)$this->config['boxen']['box_bestseller_anzahl_basis']
                : 10;

            $productIDs = $db->getInts(
                'SELECT tartikel.kArtikel
                    FROM tbestseller
                        JOIN tartikel ON tbestseller.kArtikel = tartikel.kArtikel
                    LEFT JOIN tartikelsichtbarkeit 
                        ON tartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                        AND tartikelsichtbarkeit.kKundengruppe = :cgid
                    WHERE tartikelsichtbarkeit.kArtikel IS NULL
                        AND tbestseller.isBestseller = 1 ' . $parentSQL . $stockFilterSQL . '
                    ORDER BY fAnzahl DESC LIMIT :lmt ',
                'kArtikel',
                ['cgid' => $customerGroupID, 'lmt' => $limit]
            );
            $cache->set($cacheID, $productIDs, $cacheTags);
        }
        \shuffle($productIDs);
        $res = \array_slice($productIDs, 0, (int)$this->config['boxen']['box_bestseller_anzahl_anzeige']);
        if (\count($res) > 0) {
            $this->setShow(true);
            $products = new ArtikelListe($db, $cache);
            $products->getArtikelByKeys($res, 0, \count($res));
            $this->setProducts($products);
            $this->setURL((new SearchSpecial($db, $cache))->getURL(\SEARCHSPECIALS_BESTSELLER));
        }

        \executeHook(\HOOK_BOXEN_INC_BESTSELLER, [
            'box'        => &$this,
            'cache_tags' => &$cacheTags,
            'cached'     => $cached
        ]);
    }
}
