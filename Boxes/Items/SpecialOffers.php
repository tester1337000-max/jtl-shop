<?php

declare(strict_types=1);

namespace JTL\Boxes\Items;

use JTL\Catalog\Product\ArtikelListe;
use JTL\Helpers\SearchSpecial;
use JTL\Session\Frontend;
use JTL\Shop;

/**
 * Class SpecialOffers
 * @package JTL\Boxes\Items
 */
final class SpecialOffers extends AbstractBox
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
        $stockFilterSQL = Shop::getProductFilter()->getFilterSQL()->getStockFilterSQL();
        $parentSQL      = ' AND tartikel.kVaterArtikel = 0';
        $limit          = $config['boxen']['box_sonderangebote_anzahl_basis'];
        $cacheTags      = [\CACHING_GROUP_BOX, \CACHING_GROUP_ARTICLE];
        $cacheID        = 'box_spclffr_' . $customerGroupID
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
                    JOIN tartikelsonderpreis 
                        ON tartikelsonderpreis.kArtikel = tartikel.kArtikel
                    JOIN tsonderpreise 
                        ON tsonderpreise.kArtikelSonderpreis = tartikelsonderpreis.kArtikelSonderpreis
                    LEFT JOIN tartikelsichtbarkeit 
                        ON tartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                        AND tartikelsichtbarkeit.kKundengruppe = :cgid
                    WHERE tartikelsichtbarkeit.kArtikel IS NULL
                        AND tartikelsonderpreis.kArtikel = tartikel.kArtikel
                        AND tsonderpreise.kKundengruppe = :cgid
                        AND tartikelsonderpreis.cAktiv = 'Y'
                        AND tartikelsonderpreis.dStart <= NOW()
                        AND (tartikelsonderpreis.nIstDatum = 0
                                OR tartikelsonderpreis.dEnde IS NULL
                                OR tartikelsonderpreis.dEnde >= CURDATE()
                            )
                        AND (tartikelsonderpreis.nIstAnzahl = 0
                                OR tartikelsonderpreis.nAnzahl <= tartikel.fLagerbestand
                            )"
                . $stockFilterSQL . $parentSQL . '
                    LIMIT :lmt',
                'kArtikel',
                ['lmt' => $limit, 'cgid' => $customerGroupID]
            );
            $cache->set($cacheID, $productIDs, $cacheTags);
        }
        \shuffle($productIDs);
        $res = \array_slice($productIDs, 0, (int)$config['boxen']['box_sonderangebote_anzahl_anzeige']);

        if (\count($res) > 0) {
            $this->setShow(true);
            $products = new ArtikelListe($db, $cache);
            $products->getArtikelByKeys($res, 0, \count($res));
            $this->setProducts($products);
            $this->setURL((new SearchSpecial($db, $cache))->getURL(\SEARCHSPECIALS_SPECIALOFFERS));
            \executeHook(\HOOK_BOXEN_INC_SONDERANGEBOTE, [
                'box'        => &$this,
                'cache_tags' => &$cacheTags,
                'cached'     => $cached
            ]);
        }
    }
}
