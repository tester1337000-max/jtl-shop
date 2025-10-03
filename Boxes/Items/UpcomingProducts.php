<?php

declare(strict_types=1);

namespace JTL\Boxes\Items;

use JTL\Catalog\Product\ArtikelListe;
use JTL\Helpers\SearchSpecial;
use JTL\Session\Frontend;
use JTL\Shop;

/**
 * Class UpcomingProducts
 * @package JTL\Boxes\Items
 */
final class UpcomingProducts extends AbstractBox
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
        $cached         = true;
        $cacheTags      = [\CACHING_GROUP_BOX, \CACHING_GROUP_ARTICLE];
        $stockFilterSQL = Shop::getProductFilter()->getFilterSQL()->getStockFilterSQL();
        $parentSQL      = ' AND tartikel.kVaterArtikel = 0';
        $limit          = (int)$config['boxen']['box_erscheinende_anzahl_basis'];
        $cacheID        = 'box_ucp_' . $customerGroupID . '_' . $limit . \md5($stockFilterSQL . $parentSQL);
        $cache          = Shop::Container()->getCache();
        $db             = Shop::Container()->getDB();
        /** @var int[]|false $productIDs */
        $productIDs = $cache->get($cacheID);
        if ($productIDs === false) {
            $cached     = false;
            $productIDs = $db->getInts(
                'SELECT tartikel.kArtikel
                    FROM tartikel
                    LEFT JOIN tartikelsichtbarkeit 
                        ON tartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                        AND tartikelsichtbarkeit.kKundengruppe = :cid
                    WHERE tartikelsichtbarkeit.kArtikel IS NULL '
                . $stockFilterSQL . ' ' . $parentSQL . '
                        AND NOW() < tartikel.dErscheinungsdatum
                    LIMIT :lmt',
                'kArtikel',
                ['cid' => $customerGroupID, 'lmt' => $limit]
            );
            $cache->set($cacheID, $productIDs, $cacheTags);
        }
        \shuffle($productIDs);
        $res = \array_slice($productIDs, 0, (int)$config['boxen']['box_erscheinende_anzahl_anzeige']);
        if (\count($res) > 0) {
            $this->setShow(true);
            $products = new ArtikelListe($db, $cache);
            $products->getArtikelByKeys($res, 0, \count($res));
            $this->setProducts($products);
            $this->setURL((new SearchSpecial($db, $cache))->getURL(\SEARCHSPECIALS_UPCOMINGPRODUCTS));
            \executeHook(\HOOK_BOXEN_INC_ERSCHEINENDEPRODUKTE, [
                'box'        => &$this,
                'cache_tags' => &$cacheTags,
                'cached'     => $cached
            ]);
        }
    }
}
