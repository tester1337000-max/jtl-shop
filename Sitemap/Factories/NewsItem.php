<?php

declare(strict_types=1);

namespace JTL\Sitemap\Factories;

use Generator;
use JTL\Language\LanguageModel;
use JTL\Sitemap\Items\NewsItem as Item;

use function Functional\first;
use function Functional\map;

/**
 * Class NewsItem
 * @package JTL\Sitemap\Factories
 */
final class NewsItem extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function getCollection(array $languages, array $customerGroups): Generator
    {
        $languageIDs = map($languages, fn(LanguageModel $e): int => $e->getId());
        $res         = $this->db->getPDOStatement(
            "SELECT tnews.dErstellt AS dlm, tnews.kNews, tnews.cPreviewImage AS image, tseo.cSeo, 
            tseo.kSprache AS langID, t.title
                FROM tnews
                JOIN tnewssprache t 
                    ON tnews.kNews = t.kNews
                JOIN tseo 
                    ON tseo.cKey = 'kNews'
                    AND tseo.kKey = tnews.kNews
                    AND tseo.kSprache = t.languageID
                WHERE tnews.nAktiv = 1
                    AND tnews.dGueltigVon <= NOW()
                    AND t.languageID IN (" . \implode(',', $languageIDs) . ")
                    AND (tnews.cKundengruppe LIKE '%;-1;%'
                    OR FIND_IN_SET('" . first($customerGroups) . "', REPLACE(tnews.cKundengruppe, ';',',')) > 0) 
                    ORDER BY tnews.dErstellt"
        );
        while (($ni = $res->fetchObject()) !== false) {
            $item = new Item($this->config, $this->baseURL, $this->baseImageURL);
            $item->generateData($ni, $languages);
            yield $item;
        }
    }
}
