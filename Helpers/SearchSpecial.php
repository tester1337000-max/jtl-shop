<?php

declare(strict_types=1);

namespace JTL\Helpers;

use JTL\Cache\JTLCacheInterface;
use JTL\Customer\CustomerGroup;
use JTL\DB\DbInterface;
use JTL\Media\Image\Overlay;
use JTL\Shop;
use stdClass;

use function Functional\map;

/**
 * Class SearchSpecial
 * @package JTL\Helpers
 * @since 5.0.0
 */
readonly class SearchSpecial
{
    public function __construct(private DbInterface $db, private JTLCacheInterface $cache)
    {
    }

    /**
     * @return Overlay[]
     * @former holeAlleSuchspecialOverlays()
     * @since 5.0.0
     */
    public static function getAll(int $langID = 0): array
    {
        static $allOverlays = [];

        $langID  = $langID > 0 ? $langID : Shop::getLanguageID();
        $cacheID = 'haso_' . $langID;
        if (isset($allOverlays[$cacheID])) {
            return $allOverlays[$cacheID];
        }
        /** @var Overlay[]|false $overlays */
        $overlays = Shop::Container()->getCache()->get($cacheID);
        if ($overlays === false) {
            $overlays = [];
            $types    = Shop::Container()->getDB()->getInts(
                'SELECT kSuchspecialOverlay
                    FROM tsuchspecialoverlay',
                'kSuchspecialOverlay'
            );
            foreach ($types as $type) {
                $overlay = Overlay::getInstance($type, $langID);
                if ($overlay->getActive() === 1) {
                    $overlays[] = $overlay;
                }
            }
            /** @var Overlay[] $overlays */
            $overlays = \Functional\sort($overlays, static function (Overlay $left, Overlay $right): int {
                return $left->getPriority() <=> $right->getPriority();
            });
            Shop::Container()->getCache()->set($cacheID, $overlays, [\CACHING_GROUP_OPTION]);
        }
        $allOverlays[$cacheID] = $overlays;

        return $overlays;
    }

    /**
     * @return array<int, object{cName: string, cURL: string}&stdClass>
     * @former baueAlleSuchspecialURLs
     * @since 5.0.0
     */
    public static function buildAllURLs(): array
    {
        $lang = Shop::Lang();
        $self = new self(Shop::Container()->getDB(), Shop::Container()->getCache());

        $bestseller       = (object)[
            'cName' => $lang->get('bestseller'),
            'cURL'  => $self->getURL(\SEARCHSPECIALS_BESTSELLER)
        ];
        $specialOffers    = (object)[
            'cName' => $lang->get('specialOffers'),
            'cURL'  => $self->getURL(\SEARCHSPECIALS_SPECIALOFFERS)
        ];
        $topOffers        = (object)[
            'cName' => $lang->get('topOffers'),
            'cURL'  => $self->getURL(\SEARCHSPECIALS_TOPOFFERS)
        ];
        $newProducts      = (object)[
            'cName' => $lang->get('newProducts'),
            'cURL'  => $self->getURL(\SEARCHSPECIALS_NEWPRODUCTS)
        ];
        $upcomingProducts = (object)[
            'cName' => $lang->get('upcomingProducts'),
            'cURL'  => $self->getURL(\SEARCHSPECIALS_UPCOMINGPRODUCTS)
        ];
        $topReviews       = (object)[
            'cName' => $lang->get('topReviews'),
            'cURL'  => $self->getURL(\SEARCHSPECIALS_TOPREVIEWS)
        ];

        return [
            \SEARCHSPECIALS_BESTSELLER       => $bestseller,
            \SEARCHSPECIALS_SPECIALOFFERS    => $specialOffers,
            \SEARCHSPECIALS_TOPOFFERS        => $topOffers,
            \SEARCHSPECIALS_NEWPRODUCTS      => $newProducts,
            \SEARCHSPECIALS_UPCOMINGPRODUCTS => $upcomingProducts,
            \SEARCHSPECIALS_TOPREVIEWS       => $topReviews
        ];
    }

    /**
     * @former baueSuchSpecialURL()
     * @since 5.0.0
     */
    public static function buildURL(int $key): string
    {
        return (new self(Shop::Container()->getDB(), Shop::Container()->getCache()))->getURL($key);
    }

    public function getURL(int $type): string
    {
        $cacheID = 'bsurl_' . $type . '_' . Shop::getLanguageID();
        /** @var string|false $url */
        $url = $this->cache->get($cacheID);
        if ($url !== false) {
            \executeHook(\HOOK_BOXEN_INC_SUCHSPECIALURL);

            return $url;
        }
        $seo = $this->db->select(
            'tseo',
            'kSprache',
            Shop::getLanguageID(),
            'cKey',
            'suchspecial',
            'kKey',
            $type,
            false,
            'cSeo'
        ) ?? new stdClass();

        $seo->kSuchspecial = $type;
        \executeHook(\HOOK_BOXEN_INC_SUCHSPECIALURL);
        $url = URL::buildURL($seo, \URLART_SEARCHSPECIALS, true);

        $this->cache->set($cacheID, $url, [\CACHING_GROUP_OPTION]);

        return $url;
    }

    /**
     * @return array<int<1, 6>, array<int, string>>
     */
    public function getAllSlugs(): array
    {
        $cacheID = 'bsurls_all';
        /** @var array<int<1, 6>, array<int, string>>|false $slugs */
        $slugs = $this->cache->get($cacheID);
        if ($slugs !== false) {
            return $slugs;
        }
        /** @var array<int<1, 6>, stdClass[]> $data */
        $data  = $this->db->getCollection(
            'SELECT *
                FROM tseo
                WHERE cKey = :key',
            ['key' => 'suchspecial'],
        )->map(
            static function (stdClass $ele): stdClass {
                $ele->kSprache = (int)$ele->kSprache;
                $ele->kKey     = (int)$ele->kKey;

                return $ele;
            }
        )->groupBy('kKey')->toArray();
        $slugs = [];
        foreach ($data as $id => $items) {
            $slugs[$id] = [];
            foreach ($items as $item) {
                $slugs[$id][$item->kSprache] = $item->cSeo;
            }
        }
        $this->cache->set($cacheID, $slugs, [\CACHING_GROUP_OPTION]);

        return $slugs;
    }

    /**
     * @former gibVaterSQL()
     * @since 5.0.0
     */
    public static function getParentSQL(): string
    {
        return ' AND tartikel.kVaterArtikel = 0';
    }

    /**
     * @param int[] $arr
     * @param int   $limit
     * @return int[]
     * @former randomizeAndLimit()
     * @since 5.0.0
     */
    public static function randomizeAndLimit(array $arr, int $limit = 1): array
    {
        if ($limit < 0) {
            $limit = 0;
        }

        \shuffle($arr);

        return \array_slice($arr, 0, $limit);
    }

    /**
     * @return int[]
     * @former gibTopAngebote()
     * @since 5.0.0
     */
    public function getTopOffers(int $limit = 20, int $customerGroupID = 0): array
    {
        if (!$customerGroupID) {
            $customerGroupID = CustomerGroup::getDefaultGroupID();
        }
        $cacheID = 'ssp_top_offers_' . $customerGroupID;
        $top     = $this->cache->get($cacheID);
        if ($top === false || !\is_array($top)) {
            $top = $this->db->getInts(
                "SELECT tartikel.kArtikel
                    FROM tartikel
                    LEFT JOIN tartikelsichtbarkeit 
                        ON tartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                        AND tartikelsichtbarkeit.kKundengruppe = :cgid
                    WHERE tartikelsichtbarkeit.kArtikel IS NULL
                        AND tartikel.cTopArtikel = 'Y'
                        " . self::getParentSQL() . '
                        ' . Shop::getProductFilter()->getFilterSQL()->getStockFilterSQL(),
                'kArtikel',
                ['cgid' => $customerGroupID]
            );
            $this->cache->set($cacheID, $top, $this->getCacheTags($top));
        }

        return self::randomizeAndLimit($top, \min(\count($top), $limit));
    }

    /**
     * @return int[]
     * @former gibBestseller()
     * @since 5.0.0
     */
    public function getBestsellers(int $limit = 20, int $customerGroupID = 0): array
    {
        if (!$customerGroupID) {
            $customerGroupID = CustomerGroup::getDefaultGroupID();
        }
        $minAmount   = (float)(Shop::getSettingValue(\CONF_GLOBAL, 'global_bestseller_minanzahl') ?? 10);
        $cacheID     = 'ssp_bestsellers_' . $customerGroupID . '_' . $minAmount;
        $bestsellers = $this->cache->get($cacheID);
        if ($bestsellers === false || !\is_array($bestsellers)) {
            $bestsellers = $this->db->getInts(
                'SELECT tartikel.kArtikel, tbestseller.fAnzahl
                    FROM tbestseller
                    JOIN tartikel ON tbestseller.kArtikel = tartikel.kArtikel
                    LEFT JOIN tartikelsichtbarkeit 
                        ON tartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                        AND tartikelsichtbarkeit.kKundengruppe = :cgid
                    WHERE tartikelsichtbarkeit.kArtikel IS NULL
                        AND tbestseller.isBestseller = 1
                        ' . self::getParentSQL() . '
                        ' . Shop::getProductFilter()->getFilterSQL()->getStockFilterSQL() . '
                    ORDER BY fAnzahl DESC',
                'kArtikel',
                ['cgid' => $customerGroupID]
            );
            $this->cache->set($cacheID, $bestsellers, $this->getCacheTags($bestsellers));
        }

        return self::randomizeAndLimit($bestsellers, \min(\count($bestsellers), $limit));
    }

    /**
     * @return int[]
     * @former gibSonderangebote()
     * @since 5.0.0
     */
    public function getSpecialOffers(int $limit = 20, int $customerGroupID = 0): array
    {
        if (!$customerGroupID) {
            $customerGroupID = CustomerGroup::getDefaultGroupID();
        }
        $cacheID       = 'ssp_special_offers_' . $customerGroupID;
        $specialOffers = $this->cache->get($cacheID);
        if ($specialOffers === false || !\is_array($specialOffers)) {
            $specialOffers = $this->db->getInts(
                "SELECT tartikel.kArtikel, tsonderpreise.fNettoPreis
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
                        AND (tartikelsonderpreis.dEnde IS NULL OR tartikelsonderpreis.dEnde >= CURDATE())
                        AND (tartikelsonderpreis.nAnzahl < tartikel.fLagerbestand OR tartikelsonderpreis.nIstAnzahl = 0)
                        " . self::getParentSQL() . '
                        ' . Shop::getProductFilter()->getFilterSQL()->getStockFilterSQL(),
                'kArtikel',
                ['cgid' => $customerGroupID]
            );
            $this->cache->set($cacheID, $specialOffers, $this->getCacheTags($specialOffers), 3600);
        }

        return self::randomizeAndLimit($specialOffers, \min(\count($specialOffers), $limit));
    }

    /**
     * @return int[]
     * @former gibNeuImSortiment()
     * @since 5.0.0
     */
    public function getNewProducts(int $limit, int $customerGroupID = 0): array
    {
        if (!$customerGroupID) {
            $customerGroupID = CustomerGroup::getDefaultGroupID();
        }
        $config  = Shop::getSettingValue(\CONF_BOXEN, 'box_neuimsortiment_alter_tage');
        $days    = $config > 0 ? (int)$config : 30;
        $cacheID = 'ssp_new_' . $customerGroupID . '_days';
        $new     = $this->cache->get($cacheID);
        if ($new === false || !\is_array($new)) {
            $new = $this->db->getInts(
                "SELECT tartikel.kArtikel
                    FROM tartikel
                    LEFT JOIN tartikelsichtbarkeit 
                        ON tartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                        AND tartikelsichtbarkeit.kKundengruppe = :cgid
                    WHERE tartikelsichtbarkeit.kArtikel IS NULL
                        AND tartikel.cNeu = 'Y'
                        AND DATE_SUB(NOW(), INTERVAL :dys DAY) < tartikel.dErstellt
                        " . self::getParentSQL() . ' ' . Shop::getProductFilter()->getFilterSQL()->getStockFilterSQL(),
                'kArtikel',
                ['cgid' => $customerGroupID, 'dys' => $days]
            );
            $this->cache->set($cacheID, $new, $this->getCacheTags($new), 3600);
        }

        return self::randomizeAndLimit($new, \min(\count($new), $limit));
    }

    /**
     * @param int[] $productIDs
     * @return string[]
     */
    private function getCacheTags(array $productIDs): array
    {
        $tags   = map($productIDs, fn(int $id): string => \CACHING_GROUP_PRODUCT . '_' . $id);
        $tags[] = \CACHING_GROUP_PRODUCT;
        $tags[] = 'jtl_ssp';

        return $tags;
    }
}
