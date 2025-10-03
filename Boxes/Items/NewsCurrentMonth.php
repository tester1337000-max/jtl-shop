<?php

declare(strict_types=1);

namespace JTL\Boxes\Items;

use JTL\Helpers\Text;
use JTL\Helpers\URL;
use JTL\Shop;

/**
 * Class NewsCurrentMonth
 * @package JTL\Boxes\Items
 */
final class NewsCurrentMonth extends AbstractBox
{
    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->addMapping('oNewsMonatsUebersicht_arr', 'Items');
        $langID = Shop::getLanguageID();
        $sql    = (int)$config['news']['news_anzahl_box'] > 0
            ? ' LIMIT ' . (int)$config['news']['news_anzahl_box']
            : '';

        $newsOverview = Shop::Container()->getDB()->getObjects(
            "SELECT tseo.cSeo, tnewsmonatsuebersicht.cName, tnewsmonatsuebersicht.kNewsMonatsUebersicht, 
                MONTH(tnews.dGueltigVon) AS nMonat, YEAR( tnews.dGueltigVon ) AS nJahr, COUNT(*) AS nAnzahl
                FROM tnews
                JOIN tnewsmonatsuebersicht 
                    ON tnewsmonatsuebersicht.nMonat = MONTH(tnews.dGueltigVon)
                    AND tnewsmonatsuebersicht.nJahr = YEAR(tnews.dGueltigVon)
                    AND tnewsmonatsuebersicht.kSprache = :lid
                JOIN tnewssprache t 
                    ON tnews.kNews = t.kNews
                LEFT JOIN tseo 
                    ON cKey = 'kNewsMonatsUebersicht'
                    AND kKey = tnewsmonatsuebersicht.kNewsMonatsUebersicht
                    AND tseo.kSprache = :lid
                WHERE tnews.dGueltigVon < NOW()
                    AND tnews.nAktiv = 1
                    AND t.languageID = :lid
                GROUP BY YEAR(tnews.dGueltigVon) , MONTH(tnews.dGueltigVon)
                ORDER BY tnews.dGueltigVon DESC" . $sql,
            ['lid' => $langID]
        );
        $prefix       = Shop::getURL() . '/';
        $locale       = Text::convertISO2ISO639(Shop::getLanguageCode());
        foreach ($newsOverview as $item) {
            $item->kNewsMonatsUebersicht = (int)$item->kNewsMonatsUebersicht;
            $item->nMonat                = (int)$item->nMonat;
            $item->nJahr                 = (int)$item->nJahr;
            $item->nAnzahl               = (int)$item->nAnzahl;
            $item->cURL                  = URL::buildURL($item, \URLART_NEWSMONAT, false, $prefix, $locale);
            $item->cURLFull              = URL::buildURL($item, \URLART_NEWSMONAT, true, $prefix, $locale);
        }
        $this->setShow(\count($newsOverview) > 0);
        $this->setItems($newsOverview);

        \executeHook(\HOOK_BOXEN_INC_NEWS);
    }
}
