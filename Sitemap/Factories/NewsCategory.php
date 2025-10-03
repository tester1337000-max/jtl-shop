<?php

declare(strict_types=1);

namespace JTL\Sitemap\Factories;

use Generator;
use JTL\Language\LanguageModel;
use JTL\Sitemap\Items\NewsCategory as Item;

use function Functional\map;

/**
 * Class NewsCategory
 * @package JTL\Sitemap\Factories
 */
final class NewsCategory extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function getCollection(array $languages, array $customerGroups): Generator
    {
        $languageIDs = map($languages, fn(LanguageModel $e): int => $e->getId());
        $res         = $this->db->getPDOStatement(
            "SELECT tnewskategorie.dLetzteAktualisierung AS dlm, tnewskategorie.kNewsKategorie, 
            tnewskategorie.cPreviewImage AS image, tseo.cSeo, tseo.kSprache AS langID, t.name AS title
                FROM tnewskategorie
                JOIN tnewskategoriesprache t 
                    ON tnewskategorie.kNewsKategorie = t.kNewsKategorie
                JOIN tseo 
                    ON tseo.cKey = 'kNewsKategorie'
                    AND tseo.kKey = tnewskategorie.kNewsKategorie
                    AND tseo.kSprache = t.languageID
                WHERE tnewskategorie.nAktiv = 1
                    AND tseo.kSprache IN (" . \implode(',', $languageIDs) . ')'
        );
        while (($nc = $res->fetchObject()) !== false) {
            $item = new Item($this->config, $this->baseURL, $this->baseImageURL);
            $item->generateData($nc, $languages);
            yield $item;
        }
    }
}
