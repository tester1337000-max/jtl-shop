<?php

declare(strict_types=1);

namespace JTL\Sitemap\Factories;

use Generator;
use JTL\Language\LanguageModel;
use JTL\Sitemap\Items\LiveSearch as Item;

use function Functional\map;

/**
 * Class LiveSearch
 * @package JTL\Sitemap\Factories
 */
final class LiveSearch extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function getCollection(array $languages, array $customerGroups): Generator
    {
        $languageIDs = map($languages, fn(LanguageModel $e): int => $e->getId());
        $res         = $this->db->getPDOStatement(
            "SELECT tsuchanfrage.kSuchanfrage, tseo.cSeo, tsuchanfrage.dZuletztGesucht AS dlm,
            tseo.kSprache AS langID
                FROM tsuchanfrage
                JOIN tseo 
                    ON tseo.cKey = 'kSuchanfrage'
                    AND tseo.kKey = tsuchanfrage.kSuchanfrage
                WHERE tsuchanfrage.nAktiv = 1
                    AND tsuchanfrage.kSprache IN (" . \implode(',', $languageIDs) . ')
                ORDER BY tsuchanfrage.kSuchanfrage'
        );
        while (($ls = $res->fetchObject()) !== false) {
            $item = new Item($this->config, $this->baseURL, $this->baseImageURL);
            $item->generateData($ls, $languages);
            yield $item;
        }
    }
}
