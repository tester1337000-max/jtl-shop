<?php

declare(strict_types=1);

namespace JTL\Boxes\Items;

use JTL\Helpers\Text;
use JTL\Helpers\URL;
use JTL\Session\Frontend;
use JTL\Shop;

/**
 * Class NewsCategories
 * @package JTL\Boxes\Items
 */
final class NewsCategories extends AbstractBox
{
    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->addMapping('oNewsKategorie_arr', 'Items');
        $sql       = (int)$config['news']['news_anzahl_box'] > 0
            ? ' LIMIT ' . (int)$config['news']['news_anzahl_box']
            : '';
        $langID    = Shop::getLanguageID();
        $cacheID   = 'bnk_' . $langID . '_' . Frontend::getCustomerGroup()->getID() . '_' . \md5($sql);
        $cached    = true;
        $cacheTags = [\CACHING_GROUP_BOX, \CACHING_GROUP_NEWS];
        /** @var \stdClass[]|false $newsCategories */
        $newsCategories = Shop::Container()->getCache()->get($cacheID);
        if ($newsCategories === false) {
            $cached         = false;
            $newsCategories = Shop::Container()->getDB()->getObjects(
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
                            OR FIND_IN_SET(':cid', REPLACE(tnews.cKundengruppe, ';', ',')) > 0)
                        AND t.languageID = :lid
                    GROUP BY tnewskategorienews.kNewsKategorie
                    ORDER BY tnewskategorie.nSort DESC" . $sql,
                ['lid' => $langID, 'cid' => Frontend::getCustomerGroup()->getID()]
            );
            Shop::Container()->getCache()->set($cacheID, $newsCategories, $cacheTags);
        }
        $prefix = Shop::getURL() . '/';
        $locale = Text::convertISO2ISO639(Shop::getLanguageCode());
        foreach ($newsCategories as $newsCategory) {
            $newsCategory->cURL     = URL::buildURL($newsCategory, \URLART_NEWSKATEGORIE, false, $prefix, $locale);
            $newsCategory->cURLFull = URL::buildURL($newsCategory, \URLART_NEWSKATEGORIE, true, $prefix, $locale);
        }
        $this->setShow(\count($newsCategories) > 0);
        $this->setItems($newsCategories);
        \executeHook(\HOOK_BOXEN_INC_NEWSKATEGORIE, [
            'box'        => &$this,
            'cache_tags' => &$cacheTags,
            'cached'     => $cached
        ]);
    }
}
