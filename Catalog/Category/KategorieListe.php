<?php

declare(strict_types=1);

namespace JTL\Catalog\Category;

use JTL\Helpers\Category;
use JTL\Language\LanguageHelper;
use JTL\Session\Frontend;
use JTL\Settings\Option\Filter;
use JTL\Settings\Option\Globals;
use JTL\Settings\Settings;
use JTL\Shop;

/**
 * Class KategorieListe
 * @package JTL\Catalog\Category
 */
class KategorieListe
{
    /**
     * @var Kategorie[]|MenuItem[]
     */
    public array $elemente = [];

    public static bool $wasModified = false;

    /**
     * temporary array to store list of all categories
     * used since getCategoryList() is called very often
     * and may create overhead on unserialize() in the caching class
     *
     * @var array<string, array<mixed>>
     */
    private static array $allCats = [];

    /**
     * Holt UnterKategorien für die spezifizierte kKategorie, jeweils nach nSort, Name sortiert
     * @return Kategorie[]
     */
    public function getAllCategoriesOnLevel(int $categoryID, int $customerGroupID = 0, int $languageID = 0): array
    {
        $this->elemente = [];
        if (!Frontend::getCustomerGroup()->mayViewCategories()) {
            return $this->elemente;
        }
        $customerGroupID = $customerGroupID ?: Frontend::getCustomerGroup()->getID();
        $languageID      = $languageID ?: Shop::getLanguageID();
        $showLevel2      = Settings::boolValue(Filter::SUB_CATEGORIES_LVL2_SHOW);
        if ($categoryID > 0 && \count(self::$allCats) === 0) {
            $this->getAllCategoriesOnLevel(0, $customerGroupID, $languageID);
        }
        foreach ($this->getChildCategories($categoryID, $customerGroupID, $languageID) as $category) {
            $category->bAktiv = (Shop::$kKategorie > 0 && $category->getID() === Shop::$kKategorie);
            $category->setSubCategories([]);
            if ($showLevel2) {
                $category->setSubCategories(
                    $this->getChildCategories(
                        $category->getID(),
                        $customerGroupID,
                        $languageID
                    )
                );
            }
            $this->elemente[] = $category;
        }
        if ($categoryID === 0 && self::$wasModified === true) {
            $cacheID = \CACHING_GROUP_CATEGORY . '_list_' . $customerGroupID . '_' . $languageID;
            Shop::Container()->getCache()->set(
                $cacheID,
                self::$allCats[$cacheID],
                [\CACHING_GROUP_CATEGORY]
            );
        }

        return $this->elemente;
    }

    /**
     * @return array<mixed>
     */
    public static function getCategoryList(int $customerGroupID, int $languageID): array
    {
        $cacheID = \CACHING_GROUP_CATEGORY . '_list_' . $customerGroupID . '_' . $languageID;
        if (isset(self::$allCats[$cacheID])) {
            return self::$allCats[$cacheID];
        }
        if (($allCategories = Shop::Container()->getCache()->get($cacheID)) !== false) {
            self::$allCats[$cacheID] = $allCategories;

            return $allCategories;
        }

        return [
            'oKategorie_arr'                   => [],
            'kKategorieVonUnterkategorien_arr' => [],
            'ks'                               => []
        ];
    }

    /**
     * @param array<mixed> $categoryList
     */
    public static function setCategoryList(array $categoryList, int $customerGroupID, int $languageID): void
    {
        $cacheID                 = \CACHING_GROUP_CATEGORY . '_list_' . $customerGroupID . '_' . $languageID;
        self::$allCats[$cacheID] = $categoryList;
    }

    /**
     * Holt alle augeklappten Kategorien für eine gewählte Kategorie, jeweils nach Name sortiert
     * @return Kategorie[]
     */
    public function getOpenCategories(Kategorie $currentCategory, int $customerGroupID = 0, int $languageID = 0): array
    {
        $this->elemente = [];
        if (empty($currentCategory->getID()) || !Frontend::getCustomerGroup()->mayViewCategories()) {
            return $this->elemente;
        }
        $this->elemente[] = $currentCategory;
        $currentParent    = $currentCategory->getParentID();
        $customerGroupID  = $customerGroupID ?: Frontend::getCustomerGroup()->getID();
        $languageID       = $languageID ?: Shop::getLanguageID();
        $allCategories    = static::getCategoryList($customerGroupID, $languageID);
        $db               = Shop::Container()->getDB();
        while ($currentParent > 0) {
            $category = $allCategories['oKategorie_arr'][$currentParent]
                ?? new Kategorie($currentParent, $languageID, $customerGroupID, false, $db);
            $category->setCurrentLanguageID($languageID);
            $this->elemente[] = $category;
            $currentParent    = $category->getParentID();
        }

        return $this->elemente;
    }

    /**
     * @return Kategorie[]
     */
    public function getChildCategories(int $categoryID, int $customerGroupID, int $languageID): array
    {
        if (!Frontend::getCustomerGroup()->mayViewCategories()) {
            return [];
        }
        $db              = Shop::Container()->getDB();
        $categories      = [];
        $customerGroupID = $customerGroupID ?: Frontend::getCustomerGroup()->getID();
        $languageID      = $languageID ?: Shop::getLanguageID();
        $categoryList    = self::getCategoryList($customerGroupID, $languageID);
        $subCategories   = $categoryList['kKategorieVonUnterkategorien_arr'][$categoryID] ?? null;
        if (\is_array($subCategories)) {
            foreach ($subCategories as $subCatID) {
                $categories[$subCatID] = $categoryList['oKategorie_arr'][$subCatID]
                    ?? new Kategorie($subCatID, $languageID, $customerGroupID, false, $db);
                $categories[$subCatID]->setCurrentLanguageID($languageID);
            }

            return $categories;
        }

        if ($categoryID > 0) {
            self::$wasModified = true;
        }
        // ist nicht im cache, muss holen
        $defaultLanguageActive = LanguageHelper::isDefaultLanguageActive(languageID: $languageID);
        $orderByName           = $defaultLanguageActive ? '' : 'tkategoriesprache.cName, ';
        $data                  = $db->getObjects(
            'SELECT tkategorie.kKategorie AS id
                FROM tkategorie
                LEFT JOIN tkategoriesprache 
                    ON tkategoriesprache.kKategorie = tkategorie.kKategorie
                    AND tkategoriesprache.kSprache = :lid
                LEFT JOIN tkategoriesichtbarkeit 
                    ON tkategorie.kKategorie = tkategoriesichtbarkeit.kKategorie
                AND tkategoriesichtbarkeit.kKundengruppe = :cgid
                WHERE tkategoriesichtbarkeit.kKategorie IS NULL
                    AND tkategorie.kOberKategorie = :cid
                ORDER BY tkategorie.nSort, ' . $orderByName . 'tkategorie.cName',
            ['lid' => $languageID, 'cid' => $categoryID, 'cgid' => $customerGroupID]
        );

        $categoryList['kKategorieVonUnterkategorien_arr'][$categoryID] = [];
        foreach ($data as $item) {
            $category = new Kategorie((int)$item->id, $languageID, $customerGroupID, false, $db);
            if (!$this->nichtLeer($category->getID(), $customerGroupID)) {
                $categoryList['ks'][$category->getID()] = 2;
                continue;
            }
            // ks = ist kategorie leer 1 = nein, 2 = ja
            $categoryList['ks'][$category->getID()]                          = 1;
            $categoryList['kKategorieVonUnterkategorien_arr'][$categoryID][] = $category->getID();
            $categoryList['oKategorie_arr'][$category->getID()]              = $category;
        }
        $categories = \array_merge($categories);
        self::setCategoryList($categoryList, $customerGroupID, $languageID);

        return $categories;
    }

    public function nichtLeer(int $categoryID, int $customerGroupID): bool
    {
        $conf = Settings::intValue(Globals::EMPTY_CATEGORY_FILTER);
        if ($conf === \EINSTELLUNGEN_KATEGORIEANZEIGEFILTER_ALLE) {
            return true;
        }
        $languageID = LanguageHelper::getDefaultLanguage()->getId();
        if ($conf === \EINSTELLUNGEN_KATEGORIEANZEIGEFILTER_NICHTLEERE) {
            $categoryList = self::getCategoryList($customerGroupID, $languageID);
            if (isset($categoryList['ks'][$categoryID])) {
                if ($categoryList['ks'][$categoryID] === 1) {
                    return true;
                }
                if ($categoryList['ks'][$categoryID] === 2) {
                    return false;
                }
            }
            $db            = Shop::Container()->getDB();
            $categoryIDs   = [];
            $categoryIDs[] = $categoryID;
            while (\count($categoryIDs) > 0) {
                $category = \array_pop($categoryIDs);
                if ($this->hasProducts($languageID, $category, $customerGroupID)) {
                    $categoryList['ks'][$categoryID] = 1;
                    self::setCategoryList($categoryList, $customerGroupID, $languageID);

                    return true;
                }
                $catData = $db->getObjects(
                    'SELECT tkategorie.kKategorie
                        FROM tkategorie
                        LEFT JOIN tkategoriesichtbarkeit 
                            ON tkategorie.kKategorie = tkategoriesichtbarkeit.kKategorie
                            AND tkategoriesichtbarkeit.kKundengruppe = :cgid
                        WHERE tkategoriesichtbarkeit.kKategorie IS NULL
                            AND tkategorie.kOberKategorie = :pcid
                            AND tkategorie.kKategorie != :cid',
                    ['cid' => $categoryID, 'pcid' => $category, 'cgid' => $customerGroupID]
                );
                foreach ($catData as $obj) {
                    $categoryIDs[] = (int)$obj->kKategorie;
                }
            }
            $categoryList['ks'][$categoryID] = 2;
            self::setCategoryList($categoryList, $customerGroupID, $languageID);

            return false;
        }
        $categoryList['ks'][$categoryID] = 1;
        self::setCategoryList($categoryList, $customerGroupID, $languageID);

        return true;
    }

    private function hasProducts(int $languageID, int $categoryID, int $customerGroupID): bool
    {
        return Category::getInstance($languageID, $customerGroupID)->categoryHasProducts($categoryID);
    }
}
