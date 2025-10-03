<?php

declare(strict_types=1);

namespace JTL\Boxes\Items;

use JTL\Catalog\Product\ArtikelListe;
use JTL\Helpers\SearchSpecial;
use JTL\Session\Frontend;
use JTL\Shop;

/**
 * Class NewProducts
 * @package JTL\Boxes\Items
 */
final class NewProducts extends AbstractBox
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
        $stockFilterSQL = Shop::getProductFilter()->getFilterSQL()->getStockFilterSQL();
        $parentSQL      = ' AND tartikel.kVaterArtikel = 0';
        $limit          = $config['boxen']['box_neuimsortiment_anzahl_basis'];
        $days           = $config['boxen']['box_neuimsortiment_alter_tage'] > 0
            ? (int)$config['boxen']['box_neuimsortiment_alter_tage']
            : 30;
        $cacheID        = 'bx_nwp_' . $customerGroupID
            . '_' . $days . '_'
            . $limit . \md5($stockFilterSQL . $parentSQL);
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
                        ON tartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                        AND tartikelsichtbarkeit.kKundengruppe = :cgid
                    WHERE tartikelsichtbarkeit.kArtikel IS NULL
                        AND tartikel.cNeu = 'Y' " . $stockFilterSQL . $parentSQL . '
                        AND DATE_SUB(NOW(), INTERVAL :dys DAY) < dErstellt
                    LIMIT :lmt',
                'kArtikel',
                ['lmt' => $limit, 'dys' => $days, 'cgid' => $customerGroupID]
            );
            $cache->set($cacheID, $productIDs, $cacheTags);
        }
        \shuffle($productIDs);
        $res = \array_slice($productIDs, 0, (int)$config['boxen']['box_neuimsortiment_anzahl_anzeige']);

        if (\count($res) > 0) {
            $this->setShow(true);
            $products = new ArtikelListe($db, $cache);
            $products->getArtikelByKeys($res, 0, \count($res));
            $this->setProducts($products);
            $this->setURL((new SearchSpecial($db, $cache))->getURL(\SEARCHSPECIALS_NEWPRODUCTS));
            \executeHook(\HOOK_BOXEN_INC_NEUIMSORTIMENT, [
                'box'        => &$this,
                'cache_tags' => &$cacheTags,
                'cached'     => $cached
            ]);
        }
    }
}
