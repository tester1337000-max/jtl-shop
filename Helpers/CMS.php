<?php

declare(strict_types=1);

namespace JTL\Helpers;

use Illuminate\Support\Collection;
use JTL\Catalog\Product\Artikel;
use JTL\Catalog\Product\ArtikelListe;
use JTL\Catalog\Product\Preise;
use JTL\Link\SpecialPageNotFoundException;
use JTL\News\Item;
use JTL\News\ItemList;
use JTL\Session\Frontend;
use JTL\Shop;
use stdClass;

/**
 * Class CMS
 * @package JTL\Helpers
 */
class CMS
{
    /**
     * @return stdClass[]
     * @since  5.0.0
     * @former gibStartBoxen()
     */
    public static function getHomeBoxes(): array
    {
        $customerGroupID = Frontend::getCustomerGroup()->getID();
        if (!$customerGroupID || !Frontend::getCustomerGroup()->mayViewCategories()) {
            return [];
        }
        $boxes         = self::getHomeBoxList(Shop::getSettingSection(\CONF_STARTSEITE));
        $searchSpecial = new SearchSpecial(Shop::Container()->getDB(), Shop::Container()->getCache());
        foreach ($boxes as $box) {
            $type       = 0;
            $productIDs = [];
            switch ($box->name) {
                case 'TopAngebot':
                    $productIDs = $searchSpecial->getTopOffers($box->anzahl, $customerGroupID);
                    $type       = \SEARCHSPECIALS_TOPOFFERS;
                    break;

                case 'Bestseller':
                    $productIDs = $searchSpecial->getBestsellers($box->anzahl, $customerGroupID);
                    $type       = \SEARCHSPECIALS_BESTSELLER;
                    break;

                case 'Sonderangebote':
                    $productIDs = $searchSpecial->getSpecialOffers($box->anzahl, $customerGroupID);
                    $type       = \SEARCHSPECIALS_SPECIALOFFERS;
                    break;

                case 'NeuImSortiment':
                    $productIDs = $searchSpecial->getNewProducts($box->anzahl, $customerGroupID);
                    $type       = \SEARCHSPECIALS_NEWPRODUCTS;
                    break;
            }
            if (\count($productIDs) > 0) {
                $box->cURL    = $searchSpecial->getURL($type);
                $box->Artikel = new ArtikelListe();
                $box->Artikel->getArtikelByKeys($productIDs, 0, \count($productIDs));
            }
        }
        \executeHook(\HOOK_BOXEN_HOME, ['boxes' => &$boxes]);

        return $boxes;
    }

    /**
     * @param array<mixed> $conf
     * @return Collection<int, Item>
     * @since  5.0.0
     * @former gibNews()
     */
    public static function getHomeNews(array $conf): Collection
    {
        $items = new Collection();
        if ((int)($conf['news']['news_anzahl_content'] ?? 0) === 0) {
            return $items;
        }
        try {
            // hide news if news page does not exist or is set to "visible for logged in users only"
            Shop::Container()->getLinkService()->getSpecialPage(\LINKTYP_NEWS);
        } catch (SpecialPageNotFoundException) {
            return $items;
        }
        $limit   = '';
        $cache   = Shop::Container()->getCache();
        $cgID    = Frontend::getCustomerGroup()->getID();
        $langID  = Shop::getLanguageID();
        $cacheID = 'news_' . \md5(\json_encode($conf['news'], \JSON_THROW_ON_ERROR) . '_' . $langID . '_' . $cgID);
        /** @var Collection<int, Item>|false $items */
        $items = $cache->get($cacheID);
        if ($items === false) {
            if ((int)$conf['news']['news_anzahl_content'] > 0) {
                $limit = ' LIMIT ' . (int)$conf['news']['news_anzahl_content'];
            }
            $newsIDs = Shop::Container()->getDB()->getInts(
                "SELECT tnews.kNews
                    FROM tnews
                    JOIN tnewskategorienews 
                        ON tnewskategorienews.kNews = tnews.kNews
                    JOIN tnewssprache t 
                        ON tnews.kNews = t.kNews
                    JOIN tnewskategorie 
                        ON tnewskategorie.kNewsKategorie = tnewskategorienews.kNewsKategorie
                         AND tnewskategorie.nAktiv = 1
                    LEFT JOIN tseo 
                        ON tseo.cKey = 'kNews'
                        AND tseo.kKey = tnews.kNews
                        AND tseo.kSprache = :lid
                    WHERE t.languageID = :lid
                        AND tnews.nAktiv = 1
                        AND tnews.dGueltigVon <= NOW()
                        AND (tnews.cKundengruppe LIKE '%;-1;%' 
                            OR FIND_IN_SET(:cgid, REPLACE(tnews.cKundengruppe, ';', ',')) > 0)
                    GROUP BY tnews.kNews
                    ORDER BY tnews.dGueltigVon DESC" . $limit,
                'kNews',
                ['lid' => $langID, 'cgid' => $cgID]
            );
            $items   = new ItemList(Shop::Container()->getDB(), $cache);
            $items->createItems($newsIDs);
            $items     = $items->getItems();
            $cacheTags = [\CACHING_GROUP_NEWS];
            \executeHook(\HOOK_GET_NEWS, [
                'cached'    => false,
                'cacheTags' => &$cacheTags,
                'oNews_arr' => $items
            ]);
            $cache->set($cacheID, $items, $cacheTags);

            return $items;
        }
        \executeHook(\HOOK_GET_NEWS, [
            'cached'    => true,
            'cacheTags' => [],
            'oNews_arr' => $items
        ]);

        return $items;
    }

    /**
     * @param array<mixed> $conf
     * @return stdClass[]
     * @since 5.0.0
     */
    private static function getHomeBoxList(array $conf): array
    {
        $boxes       = [];
        $obj         = new stdClass();
        $obj->name   = 'Bestseller';
        $obj->anzahl = (int)$conf['startseite_bestseller_anzahl'];
        $obj->sort   = (int)$conf['startseite_bestseller_sortnr'];
        $boxes[]     = $obj;

        $obj         = new stdClass();
        $obj->name   = 'NeuImSortiment';
        $obj->anzahl = (int)$conf['startseite_neuimsortiment_anzahl'];
        $obj->sort   = (int)$conf['startseite_neuimsortiment_sortnr'];
        $boxes[]     = $obj;

        $obj         = new stdClass();
        $obj->name   = 'Sonderangebote';
        $obj->anzahl = (int)$conf['startseite_sonderangebote_anzahl'];
        $obj->sort   = (int)$conf['startseite_sonderangebote_sortnr'];
        $boxes[]     = $obj;

        $obj         = new stdClass();
        $obj->name   = 'TopAngebot';
        $obj->anzahl = (int)$conf['startseite_topangebote_anzahl'];
        $obj->sort   = (int)$conf['startseite_topangebote_sortnr'];
        $boxes[]     = $obj;

        \usort($boxes, static fn(stdClass $a, stdClass $b): int => $a->sort <=> $b->sort);

        return $boxes;
    }

    /**
     * @param array<mixed> $conf
     * @return stdClass[]
     * @since  5.0.0
     * @former gibLivesucheTop()
     */
    public static function getLiveSearchTop(array $conf): array
    {
        $limit      = (int)$conf['sonstiges']['sonstiges_livesuche_all_top_count'] > 0
            ? (int)$conf['sonstiges']['sonstiges_livesuche_all_top_count']
            : 100;
        $searchData = Shop::Container()->getDB()->getObjects(
            "SELECT tsuchanfrage.kSuchanfrage, tsuchanfrage.kSprache, tsuchanfrage.cSuche, tseo.cSeo, 
            tsuchanfrage.nAktiv, tsuchanfrage.nAnzahlTreffer, tsuchanfrage.nAnzahlGesuche, 
            DATE_FORMAT(tsuchanfrage.dZuletztGesucht, '%d.%m.%Y  %H:%i') AS dZuletztGesucht_de
                FROM tsuchanfrage
                LEFT JOIN tseo 
                    ON tseo.cKey = 'kSuchanfrage' 
                    AND tseo.kKey = tsuchanfrage.kSuchanfrage 
                    AND tseo.kSprache = :lid
                WHERE tsuchanfrage.kSprache = :lid
                    AND tsuchanfrage.nAktiv = 1
                ORDER BY tsuchanfrage.nAnzahlGesuche DESC
                LIMIT :lmt",
            ['lid' => Shop::getLanguageID(), 'lmt' => $limit]
        );
        $count      = \count($searchData);
        $search     = [];
        $priority   = $count > 0
            ? (($searchData[0]->nAnzahlGesuche - $searchData[$count - 1]->nAnzahlGesuche) / 9)
            : 0;
        $prefix     = Shop::getURL() . '/';
        $locale     = Text::convertISO2ISO639(Shop::getLanguageCode());
        foreach ($searchData as $item) {
            $item->kSuchanfrage   = (int)$item->kSuchanfrage;
            $item->kSprache       = (int)$item->kSprache;
            $item->nAktiv         = (int)$item->nAktiv;
            $item->nAnzahlTreffer = (int)$item->nAnzahlTreffer;
            $item->nAnzahlGesuche = (int)$item->nAnzahlGesuche;
            $item->Klasse         = $priority < 1
                ? \random_int(1, 10)
                : (\round(($item->nAnzahlGesuche - $searchData[$count - 1]->nAnzahlGesuche) / $priority) + 1);
            $item->cURL           = URL::buildURL($item, \URLART_LIVESUCHE, false, $prefix, $locale);
            $item->cURLFull       = URL::buildURL($item, \URLART_LIVESUCHE, true, $prefix, $locale);
            $search[]             = $item;
        }

        return $search;
    }

    /**
     * @param array<mixed> $conf
     * @return stdClass[]
     * @since  5.0.0
     * @former gibLivesucheLast()
     */
    public static function getLiveSearchLast(array $conf): array
    {
        $limit      = (int)$conf['sonstiges']['sonstiges_livesuche_all_last_count'] > 0
            ? (int)$conf['sonstiges']['sonstiges_livesuche_all_last_count']
            : 100;
        $searchData = Shop::Container()->getDB()->getObjects(
            "SELECT tsuchanfrage.kSuchanfrage, tsuchanfrage.kSprache, tsuchanfrage.cSuche, tseo.cSeo, 
            tsuchanfrage.nAktiv, tsuchanfrage.nAnzahlTreffer, tsuchanfrage.nAnzahlGesuche, 
            DATE_FORMAT(tsuchanfrage.dZuletztGesucht, '%d.%m.%Y  %H:%i') AS dZuletztGesucht_de
                FROM tsuchanfrage
                LEFT JOIN tseo 
                    ON tseo.cKey = 'kSuchanfrage' 
                    AND tseo.kKey = tsuchanfrage.kSuchanfrage 
                    AND tseo.kSprache = :lid
                WHERE tsuchanfrage.kSprache = :lid
                    AND tsuchanfrage.nAktiv = 1
                    AND tsuchanfrage.kSuchanfrage > 0
                ORDER BY tsuchanfrage.dZuletztGesucht DESC
                LIMIT :lmt",
            ['lid' => Shop::getLanguageID(), 'lmt' => $limit]
        );
        $count      = \count($searchData);
        $search     = [];
        $priority   = $count > 0
            ? (($searchData[0]->nAnzahlGesuche - $searchData[$count - 1]->nAnzahlGesuche) / 9)
            : 0;
        $prefix     = Shop::getURL() . '/';
        $locale     = Text::convertISO2ISO639(Shop::getLanguageCode());
        foreach ($searchData as $item) {
            $item->kSuchanfrage   = (int)$item->kSuchanfrage;
            $item->kSprache       = (int)$item->kSprache;
            $item->nAktiv         = (int)$item->nAktiv;
            $item->nAnzahlTreffer = (int)$item->nAnzahlTreffer;
            $item->nAnzahlGesuche = (int)$item->nAnzahlGesuche;
            $item->Klasse         = $priority < 1
                ? \random_int(1, 10)
                : \round(($item->nAnzahlGesuche - $searchData[$count - 1]->nAnzahlGesuche) / $priority) + 1;
            $item->cURL           = URL::buildURL($item, \URLART_LIVESUCHE, false, $prefix, $locale);
            $item->cURLFull       = URL::buildURL($item, \URLART_LIVESUCHE, true, $prefix, $locale);
            $search[]             = $item;
        }

        return $search;
    }

    /**
     * @return stdClass[]
     * @since  5.0.0
     * @former gibNewsletterHistory()
     */
    public static function getNewsletterHistory(): array
    {
        $history = Shop::Container()->getDB()->selectAll(
            'tnewsletterhistory',
            'kSprache',
            Shop::getLanguageID(),
            'kNewsletterHistory, cBetreff, DATE_FORMAT(dStart, \'%d.%m.%Y %H:%i\') AS Datum, cHTMLStatic',
            'dStart DESC'
        );
        $prefix  = Shop::getURL() . '/';
        $locale  = Text::convertISO2ISO639(Shop::getLanguageCode());
        foreach ($history as $item) {
            $item->kNewsletterHistory = (int)$item->kNewsletterHistory;
            $item->cURL               = URL::buildURL($item, \URLART_NEWSLETTER, false, $prefix, $locale);
            $item->cURLFull           = URL::buildURL($item, \URLART_NEWSLETTER, true, $prefix, $locale);
        }

        return $history;
    }

    /**
     * @param array<mixed> $conf
     * @return Artikel[]
     * @since  5.0.0
     * @former gibGratisGeschenkArtikel()
     */
    public static function getFreeGifts(array $conf): array
    {
        $customerGroup   = Frontend::getCustomerGroup();
        $customerGroupID = $customerGroup->getID();
        $gifts           = [];
        $sort            = ' ORDER BY CAST(tartikelattribut.cWert AS DECIMAL) DESC';
        if ($conf['sonstiges']['sonstiges_gratisgeschenk_sortierung'] === 'N') {
            $sort = ' ORDER BY tartikel.cName';
        } elseif ($conf['sonstiges']['sonstiges_gratisgeschenk_sortierung'] === 'L') {
            $sort = ' ORDER BY tartikel.fLagerbestand DESC';
        }
        $limit          = ((int)$conf['sonstiges']['sonstiges_gratisgeschenk_anzahl'] > 0)
            ? ' LIMIT ' . (int)$conf['sonstiges']['sonstiges_gratisgeschenk_anzahl']
            : '';
        $db             = Shop::Container()->getDB();
        $cache          = Shop::Container()->getCache();
        $tmpGifts       = $db->getObjects(
            'SELECT tartikel.kArtikel, tartikelattribut.cWert
                FROM tartikel
                JOIN tartikelattribut 
                    ON tartikelattribut.kArtikel = tartikel.kArtikel
                LEFT JOIN tartikelsichtbarkeit 
                    ON tartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                    AND tartikelsichtbarkeit.kKundengruppe = :cgid
                WHERE tartikelsichtbarkeit.kArtikel IS NULL
                    AND tartikelattribut.cName = :an'
            . Shop::getProductFilter()->getFilterSQL()->getStockFilterSQL() . $sort . $limit,
            ['cgid' => $customerGroupID, 'an' => \FKT_ATTRIBUT_GRATISGESCHENK]
        );
        $currency       = Frontend::getCurrency();
        $defaultOptions = Artikel::getDefaultOptions();
        $languageID     = Shop::getLanguageID();
        foreach ($tmpGifts as $item) {
            $product = new Artikel($db, $customerGroup, $currency, $cache);
            $product->fuelleArtikel((int)$item->kArtikel, $defaultOptions, $customerGroupID, $languageID);
            $product->cBestellwert = Preise::getLocalizedPriceString((float)$item->cWert, $currency);
            if ($product->kEigenschaftKombi > 0 || \count($product->Variationen) === 0) {
                $gifts[] = $product;
            }
        }

        return $gifts;
    }
}
