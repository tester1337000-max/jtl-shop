<?php

declare(strict_types=1);

namespace JTL\Boxes\Items;

use JTL\Catalog\Product\Artikel;
use JTL\Helpers\SearchSpecial;
use JTL\Session\Frontend;
use JTL\Shop;

/**
 * Class TopRatedProducts
 * @package JTL\Boxes\Items
 */
final class TopRatedProducts extends AbstractBox
{
    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $products  = [];
        $parentSQL = ' AND tartikel.kVaterArtikel = 0';
        $cacheTags = [\CACHING_GROUP_BOX, \CACHING_GROUP_ARTICLE];
        $limit     = (int)$config['boxen']['boxen_topbewertet_basisanzahl'];
        $cacheID   = 'bx_tprtdp_' . $config['boxen']['boxen_topbewertet_minsterne']
            . '_' . $limit . \md5($parentSQL);
        $cached    = true;
        $cache     = Shop::Container()->getCache();
        $db        = Shop::Container()->getDB();
        /** @var int[]|false $topRated */
        $topRated = $cache->get($cacheID);
        if ($topRated === false) {
            $cached   = false;
            $topRated = $db->getInts(
                'SELECT tartikel.kArtikel, tartikelext.fDurchschnittsBewertung
                    FROM tartikel
                    JOIN tartikelext 
                        ON tartikel.kArtikel = tartikelext.kArtikel
                    WHERE ROUND(fDurchschnittsBewertung) >= :mnr ' . $parentSQL . ' 
                    ORDER BY tartikelext.fDurchschnittsBewertung DESC
                    LIMIT :lmt',
                'kArtikel',
                ['lmt' => $limit, 'mnr' => (int)$config['boxen']['boxen_topbewertet_minsterne']]
            );
            $cache->set($cacheID, $topRated, $cacheTags);
        }
        if (\count($topRated) > 0) {
            \shuffle($topRated);
            $res            = \array_slice($topRated, 0, (int)$config['boxen']['boxen_topbewertet_anzahl']);
            $defaultOptions = Artikel::getDefaultOptions();
            $cgroup         = Frontend::getCustomerGroup();
            $currency       = Frontend::getCurrency();
            foreach ($res as $id) {
                $item = (new Artikel($db, $cgroup, $currency, $cache))->fuelleArtikel($id, $defaultOptions);
                if ($item !== null) {
                    $item->fDurchschnittsBewertung = \round($item->fDurchschnittsBewertung * 2) / 2;
                    $products[]                    = $item;
                }
            }
            $this->setShow(true);
            $this->setProducts($products);
            $this->setURL((new SearchSpecial($db, $cache))->getURL(\SEARCHSPECIALS_TOPREVIEWS));

            \executeHook(\HOOK_BOXEN_INC_TOPBEWERTET, [
                'box'        => &$this,
                'cache_tags' => &$cacheTags,
                'cached'     => $cached
            ]);
        } else {
            $this->setShow(false);
        }
    }
}
