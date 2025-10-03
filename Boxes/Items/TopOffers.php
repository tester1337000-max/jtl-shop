<?php

declare(strict_types=1);

namespace JTL\Boxes\Items;

use JTL\Catalog\Product\ArtikelListe;
use JTL\Helpers\SearchSpecial;
use JTL\Session\Frontend;
use JTL\Shop;

/**
 * Class TopOffers
 * @package JTL\Boxes\Items
 */
final class TopOffers extends AbstractBox
{
    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->setShow(false);
        $customerGroupID = Frontend::getCustomerGroup()->getID();
        if ($customerGroupID <= 0 || !Frontend::getCustomerGroup()->mayViewCategories()) {
            return;
        }
        $cacheTags      = [\CACHING_GROUP_BOX, \CACHING_GROUP_ARTICLE];
        $cached         = true;
        $limit          = $config['boxen']['box_topangebot_anzahl_basis'];
        $stockFilterSQL = Shop::getProductFilter()->getFilterSQL()->getStockFilterSQL();
        $parentSQL      = ' AND tartikel.kVaterArtikel = 0';
        $cacheID        = 'bx_tpffr_' . $customerGroupID
            . '_' . $limit . \md5($stockFilterSQL . $parentSQL);
        $cache          = Shop::Container()->getCache();
        $db             = Shop::Container()->getDB();
        /** @var int[]|false $productIDs */
        $productIDs = $cache->get($cacheID);
        if ($productIDs === false) {
            $cached     = false;
            $productIDs = $db->getInts(
                "SELECT tartikel.kArtikel
                    FROM tartikel
                    LEFT JOIN tartikelsichtbarkeit 
                        ON tartikel.kArtikel=tartikelsichtbarkeit.kArtikel
                        AND tartikelsichtbarkeit.kKundengruppe = :cid
                    WHERE tartikelsichtbarkeit.kArtikel IS NULL
                        AND tartikel.cTopArtikel = 'Y' "
                . $stockFilterSQL . $parentSQL . '
                    LIMIT ' . $limit,
                'kArtikel',
                ['cid' => $customerGroupID]
            );
            $cache->set($cacheID, $productIDs, $cacheTags);
        }
        \shuffle($productIDs);
        $res = \array_slice($productIDs, 0, (int)$config['boxen']['box_topangebot_anzahl_anzeige']);

        if (\count($res) > 0) {
            $this->setShow(true);
            $products = new ArtikelListe($db, $cache);
            $products->getArtikelByKeys($res, 0, \count($res));
            $this->setProducts($products);
            $this->setURL((new SearchSpecial($db, $cache))->getURL(\SEARCHSPECIALS_TOPOFFERS));
            \executeHook(\HOOK_BOXEN_INC_TOPANGEBOTE, [
                'box'        => &$this,
                'cache_tags' => &$cacheTags,
                'cached'     => $cached
            ]);
        }
    }
}
