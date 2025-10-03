<?php

declare(strict_types=1);

namespace JTL\Sitemap;

use JTL\Cache\JTLCacheInterface;
use JTL\Catalog\Category\KategorieListe;
use JTL\Catalog\Hersteller;
use JTL\DB\DbInterface;
use JTL\Helpers\Category as CategoryHelper;
use JTL\Helpers\URL;
use JTL\Session\Frontend;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;

/**
 * Class Sitemap
 * @package JTL\Sitemap
 */
class Sitemap
{
    private int $langID;

    private int $customerGroupID;

    /**
     * @param array<string, string[]> $conf
     */
    public function __construct(
        private readonly DbInterface $db,
        private readonly JTLCacheInterface $cache,
        private readonly array $conf
    ) {
        $this->langID          = Shop::getLanguageID();
        $this->customerGroupID = Frontend::getCustomerGroup()->getID();
    }

    public function assignData(JTLSmarty $smarty): void
    {
        $smarty->assign('oKategorieliste', $this->getCategories())
            ->assign('oHersteller_arr', $this->getManufacturers())
            ->assign('oNewsMonatsUebersicht_arr', $this->getNews())
            ->assign('oNewsKategorie_arr', $this->getNewsCategories());
    }

    private function getCategories(): KategorieListe
    {
        $catList           = new KategorieListe();
        $catList->elemente = $this->conf['sitemap']['sitemap_kategorien_anzeigen'] === 'Y'
        && (
            (int)$this->conf['global']['global_sichtbarkeit'] !== 3
            || Frontend::getCustomer()->getID() > 0
        )
            ? CategoryHelper::getInstance($this->langID, $this->customerGroupID)->combinedGetAll()
            : [];

        return $catList;
    }

    /**
     * @return \stdClass[]
     */
    public function getNewsCategories(): array
    {
        if ($this->conf['sitemap']['sitemap_newskategorien_anzeigen'] !== 'Y') {
            return [];
        }
        $cacheID = 'news_category_' . $this->langID . '_' . $this->customerGroupID;
        /** @var \stdClass[]|false $newsCategories */
        $newsCategories = $this->cache->get($cacheID);
        if ($newsCategories !== false) {
            return $newsCategories;
        }
        $prefix         = Shop::getURL() . '/';
        $newsCategories = $this->db->getObjects(
            "SELECT tnewskategorie.kNewsKategorie, t.languageID AS kSprache, t.name AS cName,
            t.description AS cBeschreibung, t.metaTitle AS cMetaTitle, t.metaDescription AS cMetaDescription,
            tnewskategorie.nSort, tnewskategorie.nAktiv, tnewskategorie.dLetzteAktualisierung, 
            tnewskategorie.cPreviewImage, tseo.cSeo,
            COUNT(DISTINCT(tnewskategorienews.kNews)) AS nAnzahlNews
                FROM tnewskategorie
                JOIN tnewskategoriesprache t 
                    ON tnewskategorie.kNewsKategorie = t.kNewsKategorie
                LEFT JOIN tnewskategorienews 
                    ON tnewskategorienews.kNewsKategorie = tnewskategorie.kNewsKategorie
                LEFT JOIN tnews 
                    ON tnews.kNews = tnewskategorienews.kNews
                LEFT JOIN tseo 
                    ON tseo.cKey = 'kNewsKategorie'
                    AND tseo.kKey = tnewskategorie.kNewsKategorie
                    AND tseo.kSprache = :lid
                WHERE t.languageID = :lid
                    AND tnewskategorie.nAktiv = 1
                    AND tnews.nAktiv = 1
                    AND tnews.dGueltigVon <= NOW()
                    AND (tnews.cKundengruppe LIKE '%;-1;%' 
                        OR FIND_IN_SET(:cgid, REPLACE(tnews.cKundengruppe, ';', ',')) > 0)
                GROUP BY tnewskategorienews.kNewsKategorie
                ORDER BY tnewskategorie.nSort DESC",
            [
                'lid'  => $this->langID,
                'cgid' => $this->customerGroupID
            ]
        );
        foreach ($newsCategories as $newsCategory) {
            $newsCategory->kNewsKategorie = (int)$newsCategory->kNewsKategorie;
            $newsCategory->kSprache       = (int)$newsCategory->kSprache;
            $newsCategory->nSort          = (int)$newsCategory->nSort;
            $newsCategory->nAktiv         = (int)$newsCategory->nAktiv;
            $newsCategory->nAnzahlNews    = (int)$newsCategory->nAnzahlNews;
            $newsCategory->cURL           = URL::buildURL($newsCategory, \URLART_NEWSKATEGORIE);
            $newsCategory->cURLFull       = URL::buildURL($newsCategory, \URLART_NEWSKATEGORIE, true, $prefix);

            $items = $this->db->getObjects(
                "SELECT tnews.kNews, t.languageID AS kSprache, tnews.cKundengruppe, t.title AS cBetreff, 
                t.content AS cText, t.preview AS cVorschauText, t.metaTitle AS cMetaTitle, 
                t.metaDescription AS cMetaDescription, t.metaKeywords AS cMetaKeywords, 
                tnews.nAktiv, tnews.dErstellt, tseo.cSeo, 
                DATE_FORMAT(tnews.dGueltigVon, '%d.%m.%Y  %H:%i') AS dGueltigVon_de
                    FROM tnews
                    JOIN tnewssprache t 
                        ON tnews.kNews = t.kNews
                    JOIN tnewskategorienews 
                        ON tnewskategorienews.kNews = tnews.kNews
                    LEFT JOIN tseo 
                        ON tseo.cKey = 'kNews'
                        AND tseo.kKey = tnews.kNews
                        AND tseo.kSprache = :lid
                    WHERE t.languageID = :lid
                        AND tnewskategorienews.kNewsKategorie = :cid
                        AND tnews.nAktiv = 1
                        AND tnews.dGueltigVon <= NOW()
                        AND (tnews.cKundengruppe LIKE '%;-1;%' 
                            OR FIND_IN_SET(:cgid, REPLACE(tnews.cKundengruppe, ';', ',')) > 0)
                    GROUP BY tnews.kNews
                    ORDER BY tnews.dGueltigVon DESC",
                [
                    'lid'  => $this->langID,
                    'cgid' => $this->customerGroupID,
                    'cid'  => $newsCategory->kNewsKategorie
                ]
            );
            foreach ($items as $item) {
                $item->kNews    = (int)$item->kNews;
                $item->kSprache = (int)$item->kSprache;
                $item->nAktiv   = (int)$item->nAktiv;
                $item->cURL     = URL::buildURL($item, \URLART_NEWS);
                $item->cURLFull = URL::buildURL($item, \URLART_NEWS, true, $prefix);
            }
            $newsCategory->oNews_arr = $items;
        }
        $this->cache->set($cacheID, $newsCategories, [\CACHING_GROUP_NEWS]);

        return $newsCategories;
    }

    /**
     * @return \stdClass[]
     */
    public function getNews(): array
    {
        if ($this->conf['news']['news_benutzen'] !== 'Y') {
            return [];
        }
        $cacheID = 'sitemap_news_' . $this->langID;
        /** @var \stdClass[]|false $overview */
        $overview = $this->cache->get($cacheID);
        if ($overview !== false) {
            return $overview;
        }
        $prefix   = Shop::getURL() . '/';
        $overview = $this->db->getObjects(
            "SELECT tseo.cSeo, tnewsmonatsuebersicht.cName, tnewsmonatsuebersicht.kNewsMonatsUebersicht, 
            MONTH(tnews.dGueltigVon) AS nMonat, YEAR(tnews.dGueltigVon) AS nJahr, COUNT(*) AS nAnzahl
                FROM tnews
                JOIN tnewssprache t 
                    ON tnews.kNews = t.kNews
                JOIN tnewsmonatsuebersicht 
                    ON tnewsmonatsuebersicht.nMonat = MONTH(tnews.dGueltigVon)
                    AND tnewsmonatsuebersicht.nJahr = YEAR(tnews.dGueltigVon)
                    AND tnewsmonatsuebersicht.kSprache = :lid
                LEFT JOIN tseo 
                    ON cKey = 'kNewsMonatsUebersicht'
                    AND kKey = tnewsmonatsuebersicht.kNewsMonatsUebersicht
                    AND tseo.kSprache = :lid
                WHERE tnews.dGueltigVon < NOW()
                    AND tnews.nAktiv = 1
                    AND t.languageID = :lid
                GROUP BY YEAR(tnews.dGueltigVon), MONTH(tnews.dGueltigVon)
                ORDER BY tnews.dGueltigVon DESC",
            ['lid' => $this->langID]
        );
        foreach ($overview as $news) {
            $items = $this->db->getObjects(
                "SELECT tnews.kNews, t.languageID AS kSprache, tnews.cKundengruppe, 
                t.title AS cBetreff, t.content AS cText, t.preview AS cVorschauText, 
                t.metaTitle AS cMetaTitle, t.metaDescription AS cMetaDescription, t.metaKeywords AS cMetaKeywords,
                tnews.nAktiv, tnews.dErstellt, tseo.cSeo,
                COUNT(tnewskommentar.kNewsKommentar) AS nNewsKommentarAnzahl, 
                DATE_FORMAT(tnews.dGueltigVon, '%d.%m.%Y  %H:%i') AS dGueltigVon_de
                    FROM tnews
                    JOIN tnewssprache t 
                        ON tnews.kNews = t.kNews
                    LEFT JOIN tnewskommentar 
                        ON tnews.kNews = tnewskommentar.kNews
                    LEFT JOIN tseo 
                        ON tseo.cKey = 'kNews'
                        AND tseo.kKey = tnews.kNews
                        AND tseo.kSprache = :lid
                    WHERE t.languageID = :lid
                        AND tnews.nAktiv = 1
                        AND (tnews.cKundengruppe LIKE '%;-1;%' 
                            OR FIND_IN_SET(:cgid, REPLACE(tnews.cKundengruppe, ';', ',')) > 0)
                        AND (MONTH(tnews.dGueltigVon) = :mnth)  
                        AND (tnews.dGueltigVon <= NOW())
                        AND (YEAR(tnews.dGueltigVon) = :yr) 
                        AND (tnews.dGueltigVon <= NOW())
                    GROUP BY tnews.kNews
                    ORDER BY dGueltigVon DESC",
                [
                    'lid'  => $this->langID,
                    'cgid' => $this->customerGroupID,
                    'mnth' => $news->nMonat,
                    'yr'   => $news->nJahr
                ]
            );
            foreach ($items as $item) {
                $item->cURL     = URL::buildURL($item, \URLART_NEWS);
                $item->cURLFull = URL::buildURL($item, \URLART_NEWS, true, $prefix);
            }
            $news->oNews_arr = $items;
            $news->cURL      = URL::buildURL($news, \URLART_NEWSMONAT);
            $news->cURLFull  = URL::buildURL($news, \URLART_NEWSMONAT, true, $prefix);
        }
        $this->cache->set($cacheID, $overview, [\CACHING_GROUP_NEWS]);

        return $overview;
    }

    /**
     * @return Hersteller[]
     */
    public function getManufacturers(): array
    {
        return $this->conf['sitemap']['sitemap_hersteller_anzeigen'] === 'Y'
            ? Hersteller::getAll(true, $this->langID, $this->customerGroupID)
            : [];
    }
}
