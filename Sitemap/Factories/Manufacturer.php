<?php

declare(strict_types=1);

namespace JTL\Sitemap\Factories;

use Generator;
use JTL\Language\LanguageModel;
use JTL\Sitemap\Items\Manufacturer as Item;

use function Functional\map;

/**
 * Class Manufacturer
 * @package JTL\Sitemap\Factories
 */
final class Manufacturer extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function getCollection(array $languages, array $customerGroups): Generator
    {
        $languageIDs = map($languages, fn(LanguageModel $e): int => $e->getId());
        $res         = $this->db->getPDOStatement(
            "SELECT thersteller.kHersteller, thersteller.cName, thersteller.cBildpfad AS image, 
            tseo.cSeo, tseo.kSprache AS langID
                FROM thersteller
                JOIN tseo 
                    ON tseo.cKey = 'kHersteller'
                    AND tseo.kKey = thersteller.kHersteller
                    AND tseo.kSprache IN (" . \implode(',', $languageIDs) . ')
                WHERE thersteller.nAktiv = 1
                ORDER BY thersteller.kHersteller'
        );
        while (($mf = $res->fetchObject()) !== false) {
            $item = new Item($this->config, $this->baseURL, $this->baseImageURL);
            $item->generateData($mf, $languages);
            yield $item;
        }
    }
}
