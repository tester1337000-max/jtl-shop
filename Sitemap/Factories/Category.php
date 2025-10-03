<?php

declare(strict_types=1);

namespace JTL\Sitemap\Factories;

use Generator;
use JTL\Catalog\Category\KategorieListe;
use JTL\Language\LanguageModel;
use JTL\Sitemap\Items\Category as Item;

use function Functional\first;
use function Functional\map;

/**
 * Class Category
 * @package JTL\Sitemap\Factories
 */
final class Category extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function getCollection(array $languages, array $customerGroups): Generator
    {
        $languageIDs    = map($languages, fn(LanguageModel $e): int => $e->getId());
        $customerGroup  = first($customerGroups);
        $categoryHelper = new KategorieListe();
        $res            = $this->db->getPDOStatement(
            "SELECT tkategorie.kKategorie, tkategorie.dLetzteAktualisierung AS dlm, 
                tseo.cSeo, tkategoriepict.cPfad AS image, tseo.kSprache AS langID
                FROM tkategorie
                JOIN tseo 
                    ON tseo.cKey = 'kKategorie'
                    AND tseo.kKey = tkategorie.kKategorie
                    AND tseo.kSprache IN (" . \implode(', ', $languageIDs) . ")
                LEFT JOIN tkategoriesichtbarkeit 
                    ON tkategorie.kKategorie = tkategoriesichtbarkeit.kKategorie
                    AND tkategoriesichtbarkeit.kKundengruppe = :cGrpID
                LEFT JOIN tkategoriepict
                    ON tkategoriepict.kKategorie = tkategorie.kKategorie
                WHERE tkategoriesichtbarkeit.kKategorie IS NULL
                    AND tkategorie.kKategorie NOT IN (
                        SELECT tkategorieattribut.kKategorie
                        FROM tkategorieattribut
                        WHERE tkategorieattribut.kKategorie = tkategorie.kKategorie
                            AND tkategorieattribut.cName = 'noindex'
                    )
                ORDER BY tkategorie.kKategorie",
            ['cGrpID' => $customerGroup]
        );
        while (($category = $res->fetchObject()) !== false) {
            $category->kKategorie = (int)$category->kKategorie;
            $category->langID     = (int)$category->langID;
            if ($categoryHelper->nichtLeer($category->kKategorie, $customerGroup) === true) {
                $item = new Item($this->config, $this->baseURL, $this->baseImageURL);
                $item->generateData($category, $languages);
                yield $item;
            }
        }
    }
}
