<?php

declare(strict_types=1);

namespace JTL\Helpers;

use JTL\Catalog\Category\Kategorie;
use JTL\Catalog\Category\MenuItem;
use JTL\DB\DbInterface;
use JTL\Language\LanguageHelper;
use JTL\Session\Frontend;
use JTL\Shop;
use stdClass;

/**
 * Class Category
 * @package JTL\Helpers
 */
class Category
{
    /**
     * @var Category[]
     */
    private static array $instance = [];

    private int $languageID;

    private int $customerGroupID;

    private static int $depth;

    private string $cacheID;

    /**
     * @var array<mixed>
     */
    private static array $config;

    /**
     * @var array<int, MenuItem[]>
     */
    private static array $fullCategories = [];

    /**
     * @var int|null
     */
    private static ?int $catCount = null;

    /**
     * @var int[]|null
     */
    private static ?array $lostCategories = null;

    private static bool $limitReached = false;

    private static DbInterface $db;

    /**
     * @var array<int, int>|null
     */
    private static ?array $prodCatAssociations = null;

    protected function __construct(int $languageID, int $customerGroupID, string $cacheID)
    {
        $this->languageID      = $languageID;
        $this->customerGroupID = $customerGroupID;
        $this->cacheID         = $cacheID;

        self::$instance[$cacheID] = $this;
    }

    public static function getInstance(int $languageID = 0, int $customerGroupID = 0): self
    {
        $languageID      = $languageID ?: Shop::getLanguageID();
        $customerGroupID = $customerGroupID ?: Frontend::getCustomerGroup()->getID();
        $config          = Shop::getSettings([\CONF_GLOBAL, \CONF_TEMPLATE, \CONF_NAVIGATIONSFILTER]);
        $cacheID         = 'allctgrs_' . $customerGroupID .
            '_' . $languageID .
            '_' . $config['global']['kategorien_anzeigefilter'];

        self::$config = $config;
        self::$db     = Shop::Container()->getDB();

        return self::$instance[$cacheID] ?? new self($languageID, $customerGroupID, $cacheID);
    }

    /**
     * @return string[]|null
     */
    public function getHierarchicalSlugs(int $left): ?array
    {
        $seo = self::$db->getObjects(
            'SELECT tseo.kSprache, GROUP_CONCAT(
                COALESCE(tseo.cSeo, tkategoriesprache.cSeo, tkategorie.kKategorie)
                    ORDER BY tkategorie.lft ASC SEPARATOR \'/\') AS slug,
                COUNT(tseo.cSeo) AS seoCount, COUNT(tkategorie.kKategorie) AS catCount
                FROM tkategorie
                JOIN tsprache
                    ON tsprache.active = 1
                LEFT JOIN tseo
                    ON tseo.kKey = tkategorie.kKategorie
                    AND tseo.cKey = \'kKategorie\'
                    AND tseo.kSprache = tsprache.kSprache
                LEFT JOIN tkategoriesprache 
                    ON tkategoriesprache.kKategorie = tkategorie.kKategorie
                    AND tkategoriesprache.kSprache = tseo.kSprache
                    AND tkategoriesprache.kSprache = tsprache.kSprache
                WHERE :lft BETWEEN tkategorie.lft AND tkategorie.rght
                GROUP BY tsprache.kSprache',
            ['lft' => $left]
        );
        if (\count($seo) === 0) {
            return null;
        }
        $slugs = [];
        foreach ($seo as $item) {
            if ($item->seoCount === $item->catCount) {
                $slugs[(int)$item->kSprache] = $item->slug;
            }
        }

        return $slugs;
    }

    /**
     * @return MenuItem[]|null
     */
    private function getCacheTree(int $categoryID): ?array
    {
        $cacheID = $this->cacheID . '_cid_' . $categoryID;
        $item    = Shop::Container()->getCache()->get($cacheID);
        if (\is_array($item)) {
            self::$limitReached = $item['limitReached'];
            self::$depth        = $item['depth'];

            return $item['tree'];
        }

        return null;
    }

    /**
     * @param MenuItem[] $tree
     */
    private function setCacheTree(int $categoryID, array $tree): void
    {
        $cacheID = $this->cacheID . '_cid_' . $categoryID;
        $cache   = Shop::Container()->getCache();
        $item    = [
            'tree'         => $tree,
            'limitReached' => self::$limitReached,
            'depth'        => self::$depth,
        ];
        $cache->set($cacheID, $item, [\CACHING_GROUP_CATEGORY, 'jtl_category_tree']);
    }

    /**
     * @return MenuItem[]
     */
    public function combinedGetAll(int $startCat = 0, int $startLevel = 0): array
    {
        if ($startCat === 0 && (self::$fullCategories[$this->languageID] ?? null) !== null) {
            return self::$fullCategories[$this->languageID];
        }

        if (($fullCats = $this->getCacheTree($startCat)) === null) {
            $filterEmpty         = (int)self::$config['global']['kategorien_anzeigefilter'] ===
                \EINSTELLUNGEN_KATEGORIEANZEIGEFILTER_NICHTLEERE;
            $functionAttributes  = [];
            $localizedAttributes = [];
            foreach ($this->getAttributes() as $catAttribute) {
                $catID = $catAttribute->kKategorie;
                $idx   = \mb_convert_case($catAttribute->cName, \MB_CASE_LOWER);
                if ($catAttribute->bIstFunktionsAttribut) {
                    $functionAttributes[$catID][$idx] = $catAttribute->cWert;
                } else {
                    $localizedAttributes[$catID][$idx] = $catAttribute;
                }
            }
            $prefix = Shop::getURL() . '/';
            $nodes  = $this->getNodes($startCat, $startLevel);
            $locale = null;
            foreach (LanguageHelper::getAllLanguages() as $language) {
                if ($language->getId() === $this->languageID) {
                    $locale = $language->getIso639();
                }
            }
            foreach ($nodes as $cat) {
                $id = $cat->getID();
                $cat->setURL(URL::buildURL($cat, \URLART_KATEGORIE, true, $prefix, $locale));
                $cat->setFunctionalAttributes($functionAttributes[$id] ?? []);
                $cat->setAttributes($localizedAttributes[$id] ?? []);
                $cat->setShortName($cat->getAttribute(\ART_ATTRIBUT_SHORTNAME)->cWert ?? $cat->getName());
            }
            $fullCats = $this->buildTree($nodes, $startCat);
            $fullCats = $this->setOrphanedCategories($nodes, $fullCats);
            if ($filterEmpty) {
                $fullCats = $this->removeRelicts($this->filterEmpty($fullCats));
            }
            \executeHook(\HOOK_GET_ALL_CATEGORIES, ['categories' => &$fullCats]);
            $this->setCacheTree($startCat, $fullCats);
        }

        return $fullCats;
    }

    /**
     * @return MenuItem[]
     */
    private function getNodes(int $startCat = 0, int $startLevel = 0): array
    {
        $queryParams        = [
            'langID'   => $this->languageID,
            'cgID'     => $this->customerGroupID,
            'startCat' => $startCat,
            'startLvl' => $startLevel,
        ];
        $filterEmpty        = (int)self::$config['global']['kategorien_anzeigefilter'] ===
            \EINSTELLUNGEN_KATEGORIEANZEIGEFILTER_NICHTLEERE;
        $stockFilter        = Shop::getProductFilter()->getFilterSQL()->getStockFilterSQL();
        $showCategoryImages = self::$config['template']['megamenu']['show_category_images'] ?? 'N';
        $extended           = !empty($stockFilter);
        $isDefaultLang      = LanguageHelper::isDefaultLanguageActive(false, $this->languageID);
        self::$catCount     = self::$catCount ?? self::$db->getSingleInt(
            'SELECT COUNT(kKategorie) AS cnt FROM tkategorie WHERE kKategorie > 0',
            'cnt'
        );
        self::$limitReached = self::$catCount >= \CATEGORY_FULL_LOAD_LIMIT;
        self::$depth        = self::$limitReached ? \CATEGORY_FULL_LOAD_MAX_LEVEL : -1;
        $descriptionSelect  = ", '' AS cBeschreibung";
        $depthWhere         = self::$limitReached === true
            ? ' AND node.nLevel <= (:startLvl + ' . \CATEGORY_FULL_LOAD_MAX_LEVEL . ')'
            : '';
        $getDescription     = (self::$catCount < \CATEGORY_FULL_LOAD_LIMIT
            || // always get description if there aren't that many categories
            !(isset(self::$config['template']['megamenu']['show_maincategory_info'])
                // otherwise check template config
                && isset(self::$config['template']['megamenu']['show_categories'])
                && (self::$config['template']['megamenu']['show_categories'] === 'N'
                    || self::$config['template']['megamenu']['show_maincategory_info'] === 'N')));

        if ($getDescription === true) {
            $descriptionSelect = $isDefaultLang === true
                ? ', node.cBeschreibung' // no description needed if we don't show category info in mega menu
                : ', node.cBeschreibung, tkategoriesprache.cBeschreibung AS cBeschreibung_spr';
        }
        $imageSelect = (self::$catCount >= \CATEGORY_FULL_LOAD_LIMIT && $showCategoryImages === 'N')
            ? ", '' AS cPfad" // select empty path if we don't need category images for the mega menu
            : ', tkategoriepict.cPfad, atr.cWert As customImgName';
        $imageJoin   = (self::$catCount >= \CATEGORY_FULL_LOAD_LIMIT && $showCategoryImages === 'N')
            ? '' // the join is not needed if we don't select the category image path
            : ' LEFT JOIN tkategoriepict
                    ON tkategoriepict.kKategorie = node.kKategorie
                LEFT JOIN tkategorieattribut atr
                    ON atr.kKategorie = node.kKategorie
                    AND atr.cName = \'bildname\'';
        $nameSelect  = $isDefaultLang === true
            ? ', node.cName'
            : ', node.cName, tkategoriesprache.cName AS cName_spr';
        $langJoin    = $isDefaultLang === true
            ? ''
            : ' LEFT JOIN tkategoriesprache
                    ON tkategoriesprache.kKategorie = node.kKategorie
                        AND tkategoriesprache.kSprache = :langID ';
        $seoJoin     = " LEFT JOIN tseo
                        ON tseo.cKey = 'kKategorie'
                        AND tseo.kKey = node.kKategorie
                        AND tseo.kSprache = :langID ";
        if ($extended) {
            $countSelect    = ', COALESCE(s1.cnt, 0) cnt';
            $visibilityJoin = ' LEFT JOIN (
                SELECT tkategorieartikel.kKategorie, COUNT(tkategorieartikel.kArtikel) AS cnt
                FROM tkategorieartikel
                INNER JOIN tartikel
                    ON tkategorieartikel.kArtikel = tartikel.kArtikel ' . $stockFilter . '
                LEFT JOIN  tartikelsichtbarkeit
                    ON tkategorieartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                        AND tartikelsichtbarkeit.kKundengruppe = :cgID
                WHERE tartikelsichtbarkeit.kArtikel IS NULL
                GROUP BY tkategorieartikel.kKategorie) AS s1 ON s1.kKategorie = node.kKategorie';
        } elseif ($filterEmpty === true) {
            $countSelect    = ', COALESCE(s1.cnt, 0) cnt';
            $visibilityJoin = ' LEFT JOIN (
                SELECT tkategorieartikel.kKategorie, COUNT(tkategorieartikel.kArtikel) AS cnt
                FROM tkategorieartikel
                LEFT JOIN  tartikelsichtbarkeit
                    ON tkategorieartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                        AND tartikelsichtbarkeit.kKundengruppe = :cgID
                WHERE tartikelsichtbarkeit.kArtikel IS NULL
                GROUP BY tkategorieartikel.kKategorie) AS s1 ON s1.kKategorie = node.kKategorie';
        } else {
            // if we want to display all categories without filtering out empty ones, we don't have to check the
            // product count. this saves a very expensive join - cnt will be always -1
            $countSelect    = ', -1 AS cnt';
            $visibilityJoin = '';
        }

        return \array_map(
            function (stdClass $data): MenuItem {
                $data->languageID       = $this->languageID;
                $data->bUnterKategorien = false;
                $data->Unterkategorien  = [];

                $item = new MenuItem($data);
                if (\CATEGORIES_SLUG_HIERARCHICALLY !== false) {
                    $slugs = $this->getHierarchicalSlugs($item->getLeft());
                    if (($slug = ($slugs[$this->languageID] ?? null)) !== null) {
                        $item->setURL($slug);
                    }
                }

                return $item;
            },
            self::$db->getObjects(
                'SELECT node.kKategorie, node.lft, node.rght, node.nLevel, node.kOberKategorie, tseo.cSeo'
                . $nameSelect . $descriptionSelect . $imageSelect . $countSelect . '
                    FROM (SELECT node.kKategorie, node.nLevel, node.kOberKategorie, node.cName, node.cBeschreibung,
                        node.lft, node.rght
                        FROM tkategorie AS node
                        INNER JOIN tkategorie AS parent ON node.lft BETWEEN parent.lft AND parent.rght
                        WHERE parent.kOberKategorie = :startCat
                            AND node.nLevel > :startLvl
                            AND parent.nLevel > :startLvl ' . $depthWhere .
                ') AS node ' . $langJoin . $seoJoin . $imageJoin . '
                    LEFT JOIN tkategoriesichtbarkeit
                        ON node.kKategorie = tkategoriesichtbarkeit.kKategorie
                        AND tkategoriesichtbarkeit.kKundengruppe = :cgID'
                . $visibilityJoin . '
                    WHERE tkategoriesichtbarkeit.kKategorie IS NULL
                    ORDER BY node.lft',
                $queryParams
            )
        );
    }

    /**
     * @param int|null $categoryID
     * @return stdClass[]
     */
    private function getAttributes(?int $categoryID = null): array
    {
        $condition = $categoryID > 0
            ? ' WHERE tkategorieattribut.kKategorie = ' . $categoryID . ' '
            : '';

        return \array_map(
            static function (stdClass $e): stdClass {
                $e->kKategorie            = (int)$e->kKategorie;
                $e->bIstFunktionsAttribut = (bool)$e->bIstFunktionsAttribut;
                $e->nSort                 = (int)$e->nSort;

                return $e;
            },
            self::$db->getObjects(
                'SELECT tkategorieattribut.kKategorie, 
                    COALESCE(tkategorieattributsprache.cName, tkategorieattribut.cName) AS cName,
                    COALESCE(tkategorieattributsprache.cWert, tkategorieattribut.cWert) AS cWert,
                    tkategorieattribut.bIstFunktionsAttribut, tkategorieattribut.nSort
                FROM tkategorieattribut 
                LEFT JOIN tkategorieattributsprache 
                    ON tkategorieattributsprache.kAttribut = tkategorieattribut.kKategorieAttribut
                    AND tkategorieattributsprache.kSprache = ' . $this->languageID . $condition . '
                ORDER BY tkategorieattribut.kKategorie, tkategorieattribut.bIstFunktionsAttribut DESC, 
                tkategorieattribut.nSort'
            )
        );
    }

    /**
     * @param MenuItem[] $elements
     * @return MenuItem[]
     */
    private function buildTree(array &$elements, int $parentID = 0, int $rght = 0): array
    {
        $branch = [];
        foreach ($elements as $j => $element) {
            if ($element->getParentID() === $parentID) {
                unset($elements[$j]);
                $children = $this->buildTree($elements, $element->getID(), $element->getRight());
                if ($children) {
                    $element->setChildren($children);
                    $element->setHasChildren(\count($children) > 0);
                }
                $branch[$element->getID()] = $element;
            } elseif ($rght !== 0 && $element->getLeft() > $rght) {
                break;
            }
        }

        return $branch;
    }

    /**
     * this must only be used in edge cases where there are very big category trees
     * and someone is looking for a bottom-up * tree for a category that is not already contained in the full tree
     *
     * it's a lot of code duplication but the queries differ
     *
     * @return MenuItem[]
     */
    public function getFallBackFlatTree(int $categoryID, ?bool $filterEmpty = null): array
    {
        $filterEmpty         = $filterEmpty ?? (int)self::$config['global']['kategorien_anzeigefilter'] ===
        \EINSTELLUNGEN_KATEGORIEANZEIGEFILTER_NICHTLEERE;
        $showCategoryImages  = self::$config['template']['megamenu']['show_category_images'] ?? 'N';
        $stockFilter         = Shop::getProductFilter()->getFilterSQL()->getStockFilterSQL();
        $stockJoin           = '';
        $extended            = !empty($stockFilter);
        $functionAttributes  = [];
        $localizedAttributes = [];
        $descriptionSelect   = ", '' AS cBeschreibung";
        $isDefaultLang       = LanguageHelper::isDefaultLanguageActive(languageID: $this->languageID);
        $visibilityWhere     = ' AND tartikelsichtbarkeit.kArtikel IS NULL';
        $getDescription      = (!(isset(self::$config['template']['megamenu']['show_maincategory_info'])
            && isset(self::$config['template']['megamenu']['show_categories'])
            && (self::$config['template']['megamenu']['show_categories'] === 'N'
                || self::$config['template']['megamenu']['show_maincategory_info'] === 'N')));

        if ($getDescription === true) {
            $descriptionSelect = $isDefaultLang === true
                ? ', parent.cBeschreibung' // no category description needed if we don't show category info in mega menu
                : ', parent.cBeschreibung, tkategoriesprache.cBeschreibung AS cBeschreibung_spr';
        }
        $imageSelect           = $showCategoryImages === 'N'
            ? ", '' AS cPfad" // select empty path if we don't need category images for the mega menu
            : ', tkategoriepict.cPfad';
        $imageJoin             = $showCategoryImages === 'N'
            ? '' // the join is not needed if we don't select the category image path
            : ' LEFT JOIN tkategoriepict
                    ON tkategoriepict.kKategorie = node.kKategorie';
        $nameSelect            = $isDefaultLang === true
            ? ', parent.cName'
            : ', parent.cName, tkategoriesprache.cName AS cName_spr';
        $seoSelect             = ', parent.cSeo';
        $langJoin              = $isDefaultLang === true
            ? ''
            : ' LEFT JOIN tkategoriesprache
                    ON tkategoriesprache.kKategorie = node.kKategorie
                        AND tkategoriesprache.kSprache = ' . $this->languageID . ' ';
        $seoJoin               = $isDefaultLang === true
            ? ''
            : " LEFT JOIN tseo
                    ON tseo.cKey = 'kKategorie'
                    AND tseo.kKey = node.kKategorie
                    AND tseo.kSprache = " . $this->languageID . ' ';
        $hasProductssCheckJoin = ' LEFT JOIN tkategorieartikel
                ON tkategorieartikel.kKategorie = node.kKategorie ';
        if ($extended) {
            $countSelect    = ', COUNT(tartikel.kArtikel) AS cnt';
            $stockJoin      = ' LEFT JOIN tartikel
                    ON tkategorieartikel.kArtikel = tartikel.kArtikel ' . $stockFilter;
            $visibilityJoin = ' LEFT JOIN tartikelsichtbarkeit
                ON tartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                AND tartikelsichtbarkeit.kKundengruppe = ' . $this->customerGroupID;
        } elseif ($filterEmpty === true) {
            $countSelect    = ', COUNT(tkategorieartikel.kArtikel) AS cnt';
            $visibilityJoin = ' LEFT JOIN tartikelsichtbarkeit
                ON tkategorieartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                AND tartikelsichtbarkeit.kKundengruppe = ' . $this->customerGroupID;
        } else {
            $countSelect           = ', -1 AS cnt';
            $hasProductssCheckJoin = '';
            $visibilityJoin        = '';
            $visibilityWhere       = '';
        }

        foreach ($this->getAttributes($categoryID) as $catAttribute) {
            $catID = $catAttribute->kKategorie;
            $idx   = \mb_convert_case($catAttribute->cName, \MB_CASE_LOWER);
            if ($catAttribute->bIstFunktionsAttribut) {
                $functionAttributes[$catID][$idx] = $catAttribute->cWert;
            } else {
                $localizedAttributes[$catID][$idx] = $catAttribute;
            }
        }
        $prefix = Shop::getURL() . '/';
        $nodes  = \array_map(
            static function ($item) use ($functionAttributes, $localizedAttributes, $prefix): MenuItem {
                $item->cURL                = URL::buildURL($item, \URLART_KATEGORIE, true, $prefix);
                $item->functionAttributes  = $functionAttributes;
                $item->localizedAttributes = $localizedAttributes;

                return new MenuItem($item);
            },
            self::$db->getObjects(
                'SELECT parent.kKategorie, parent.lft, parent.rght, parent.nLevel, parent.kOberKategorie'
                . $nameSelect . $descriptionSelect . $imageSelect . $seoSelect . $countSelect . '
                    FROM tkategorie AS node INNER JOIN tkategorie AS parent ' . $langJoin . '                    
                    LEFT JOIN tkategoriesichtbarkeit
                        ON node.kKategorie = tkategoriesichtbarkeit.kKategorie
                        AND tkategoriesichtbarkeit.kKundengruppe = ' . $this->customerGroupID
                . $seoJoin . $imageJoin . $hasProductssCheckJoin . $stockJoin . $visibilityJoin . '
                    WHERE node.nLevel > 0 AND parent.nLevel > 0
                        AND tkategoriesichtbarkeit.kKategorie IS NULL AND node.lft BETWEEN parent.lft AND parent.rght
                        AND node.kKategorie = ' . $categoryID . $visibilityWhere . '                    
                    GROUP BY parent.kKategorie
                    ORDER BY parent.lft'
            )
        );

        if ($filterEmpty) {
            $nodes = $this->removeRelicts($this->filterEmpty($nodes));
        }

        return $nodes;
    }

    /**
     * remove items from category list that have no products and no subcategories
     *
     * @param MenuItem[]|MenuItem[][] $catList
     * @return MenuItem[]
     */
    private function filterEmpty(array $catList): array
    {
        foreach ($catList as $i => $cat) {
            if ($cat->hasChildren()) {
                $children = $this->filterEmpty($cat->getChildren());
                $cat->setChildren($children);
                $cat->setHasChildren(\count($children) > 0);
            }
            if ($cat->hasChildren() === false && $cat->getProductCount() === 0) {
                unset($catList[$i]);
            }
        }

        return $catList;
    }

    /**
     * self::filterEmpty() may have removed all sub categories from a category that now may have
     * no products and no sub categories with products in them. in this case, bUnterKategorien
     * has a wrong value and the whole category has to be removed from the result
     *
     * @param MenuItem[]|MenuItem[][] $menuItems
     * @param MenuItem|null           $parentCat
     * @return MenuItem[]
     */
    private function removeRelicts(array $menuItems, ?MenuItem $parentCat = null): array
    {
        foreach ($menuItems as $i => $menuItem) {
            if ($menuItem->hasChildren() === false) {
                continue;
            }
            $menuItem->setHasChildren(\count($menuItem->getChildren()) > 0);
            if ($menuItem->getProductCount() === 0 && $menuItem->hasChildren() === false) {
                unset($menuItems[$i]);
                if ($parentCat !== null && \count($parentCat->getChildren()) === 0) {
                    $parentCat->setHasChildren(false);
                }
            } else {
                $menuItem->setChildren($this->removeRelicts($menuItem->getChildren(), $menuItem));
                if (empty($menuItem->getChildren()) && $menuItem->getProductCount() === 0) {
                    unset($menuItems[$i]);
                    if ($parentCat !== null && empty($parentCat->getChildren())) {
                        $parentCat->setHasChildren(false);
                    }
                }
            }
        }

        return $menuItems;
    }

    public static function categoryExists(int $id): bool
    {
        return Shop::Container()->getDB()->select('tkategorie', 'kKategorie', $id) !== null;
    }

    public static function isLostCategory(int $categoryID): bool
    {
        return self::getInstance()->isCategoryLost($categoryID);
    }

    public function isCategoryLost(int $categoryID): bool
    {
        if (self::$lostCategories !== null) {
            return \in_array($categoryID, self::$lostCategories, true);
        }
        $cache   = Shop::Container()->getCache();
        $cacheID = $this->cacheID . '_lostCategories';
        /** @var int[]|false $lostCategories */
        $lostCategories = $cache->get($cacheID);
        if ($lostCategories === false) {
            /** @var int[] $lostCategories */
            $lostCategories = Shop::Container()->getDB()->getCollection(
                'SELECT child.kKategorie
                    FROM tkategorie
                    LEFT JOIN tkategorie parent
                        ON tkategorie.kOberKategorie = parent.kKategorie
                    LEFT JOIN tkategorie child
                        ON tkategorie.lft <= child.lft
                        AND tkategorie.rght >= child.rght
                    WHERE tkategorie.kOberKategorie > 0
                        AND parent.kKategorie IS NULL'
            )->map(fn(stdClass $item): int => (int)$item->kKategorie)->toArray();

            $cache->set($cacheID, $lostCategories, [\CACHING_GROUP_CATEGORY, 'jtl_category_tree']);
        }
        self::$lostCategories = $lostCategories;

        return \in_array($categoryID, self::$lostCategories, true);
    }

    public function getCategoryById(int $id, int $lft = -1, int $rght = -1): ?MenuItem
    {
        if ((self::$fullCategories[$this->languageID] ?? null) === null) {
            self::$fullCategories[$this->languageID] = $this->combinedGetAll();
        }
        $current = $this->findCategoryInList($id, self::$fullCategories[$this->languageID], $lft, $rght);
        if ($current === null && (self::$limitReached || $this->isCategoryLost($id))) {
            // we have an incomplete category tree (because of high category count)
            // or did not find the desired category (because it is a lost category)
            $fallback = $this->getFallBackFlatTree($id, false);
            if (\count($fallback) === 0) {
                // this category does not exists
                return null;
            }
            $current = \array_pop($fallback);
            $parent  = \array_pop($fallback);
            if ($parent === null) {
                return $current;
            }
            // get real parent category from full categories tree for further use
            $curParent = $this->findCategoryInList(
                $parent->getID(),
                self::$fullCategories[$this->languageID],
                $parent->getLeft(),
                $parent->getRight()
            );
            if ($curParent !== null) {
                // and fill children for current level
                $currentChildren = $this->combinedGetAll($curParent->getID(), $curParent->getLevel());
                if (\count($currentChildren) > 0) {
                    $curParent->setChildren($currentChildren);
                    $curParent->setHasChildren(true);
                    $current = $this->findCategoryInList(
                        $id,
                        $curParent->getChildren(),
                        $current->getLeft(),
                        $current->getRight()
                    );
                }
            }
        }

        return $current;
    }

    /**
     * @return MenuItem[]
     */
    public function getChildCategoriesById(int $id): array
    {
        $current = $this->getCategoryById($id);

        return $current !== null
            ? \array_values($current->getChildren())
            : [];
    }

    /**
     * retrieves a list of categories from a given category ID's furthest ancestor to the category itself
     *
     * @param int  $id - the base category ID
     * @param bool $noChildren - remove child categories from array?
     * @return MenuItem[]
     */
    public function getFlatTree(int $id, bool $noChildren = true): array
    {
        if ((self::$fullCategories[$this->languageID] ?? null) === null) {
            self::$fullCategories[$this->languageID] = $this->combinedGetAll();
        }
        $tree = [];
        $next = $this->getCategoryById($id);
        if ($next === null) {
            return $tree;
        }
        if (isset($next->kKategorie)) {
            if ($noChildren === true) {
                $cat = clone $next;
                $cat->setChildren([]);
            } else {
                $cat = $next;
            }
            $tree[] = $cat;
            while ($next !== null && !empty($next->getParentID())) {
                $next = $this->getCategoryById($next->getParentID(), $next->getLeft(), $next->getRight());
                if ($next !== null) {
                    if ($noChildren === true) {
                        $cat = clone $next;
                        $cat->setChildren([]);
                    } else {
                        $cat = $next;
                    }
                    $tree[] = $cat;
                }
            }
        }

        return \array_reverse($tree);
    }

    /**
     * @param MenuItem[]|MenuItem $haystack
     */
    private function findCategoryInList(int $id, MenuItem|array $haystack, int $lft = -1, int $rght = -1): ?MenuItem
    {
        if (\is_array($haystack)) {
            foreach ($haystack as $category) {
                if (($result = $this->findCategoryInList($id, $category, $lft, $rght)) !== null) {
                    return $result;
                }
            }
        }
        if ($haystack instanceof MenuItem) {
            if ($haystack->getID() === $id) {
                return $haystack;
            }
            if ($haystack->hasChildren()) {
                if ($lft > -1 && $rght > -1 && ($haystack->getLeft() > $lft || $haystack->getRight() < $rght)) {
                    return null;
                }
                return $this->findCategoryInList($id, $haystack->getChildren(), $lft, $rght);
            }
        }

        return null;
    }

    /**
     * @param string|string[]     $attribute
     * @param string|int|string[] $value
     * @param callable|null       $callback
     * @return ($callback is null ? stdClass|null : mixed)
     * @since 5.0.0
     */
    public static function getDataByAttribute(array|string $attribute, mixed $value, ?callable $callback = null): mixed
    {
        $res = Shop::Container()->getDB()->select('tkategorie', $attribute, $value);

        return \is_callable($callback)
            ? $callback($res)
            : $res;
    }

    /**
     * @param string|string[]     $attribute
     * @param string|int|string[] $value
     * @param callable|null       $callback
     * @return ($callback is null ? Kategorie|null : mixed)
     * @since 5.0.0
     */
    public static function getCategoryByAttribute(
        array|string $attribute,
        mixed $value,
        ?callable $callback = null
    ): mixed {
        $cat = ($res = self::getDataByAttribute($attribute, $value)) !== null
            ? new Kategorie($res->kKategorie)
            : null;

        return \is_callable($callback)
            ? $callback($cat)
            : $cat;
    }

    /**
     * @param Kategorie $category
     * @param bool      $asString
     * @return ($asString is true ? string : string[])
     * @since 5.0.0
     * @former gibKategoriepfad()
     */
    public function getPath(Kategorie $category, bool $asString = true): array|string
    {
        if (empty($category->getCategoryPath()) || $category->getLanguageID() !== $this->languageID) {
            if (empty($category->getID())) {
                return $asString ? '' : [];
            }
            $tree  = $this->getFlatTree($category->getID());
            $names = [];
            foreach ($tree as $item) {
                $names[] = $item->getName();
            }
        } else {
            $names = $category->getCategoryPath();
        }

        return $asString ? \implode(' > ', $names) : $names;
    }

    /**
     * @return MenuItem[]
     * @since 5.0.0
     * @former baueUnterkategorieListeHTML()
     */
    public static function getSubcategoryList(int $categoryID, int $left = -1, int $right = -1): array
    {
        if ($categoryID <= 0) {
            return [];
        }
        $instance = self::getInstance();
        $category = $instance->getCategoryById($categoryID, $left, $right);
        if (
            $category !== null
            && ((self::$limitReached && $category->getLevel() % self::$depth < 2)
                || $instance->isCategoryLost($categoryID))
        ) {
            // we have an incomplete category tree and children for next two levels are probably not filled...
            $currentChildren = $instance->combinedGetAll($category->getID(), $category->getLevel());
            if (\count($currentChildren) > 0) {
                $category->setChildren($currentChildren);
                $category->setHasChildren(true);
            }
        }

        return $category?->getChildren() ?? [];
    }

    /**
     * @param MenuItem[] $nodes
     * @param MenuItem[] $fullCats
     * @return array<int, MenuItem>
     */
    private function setOrphanedCategories(array $nodes, array $fullCats): array
    {
        $ids = \array_map(static fn(MenuItem $e): int => $e->getID(), $nodes);

        $orphanedCategories = \array_filter($nodes, static function ($e) use ($ids): bool {
            if ($e->getParentID() === 0) {
                return false;
            }
            return \in_array($e->getParentID(), $ids, true) === false;
        });

        foreach ($orphanedCategories as $category) {
            $children = $this->buildTree($nodes, $category->getID());
            $category->setParentID(0);
            $category->setOrphaned(true);
            $category->setChildren($children);
            $category->setHasChildren(\count($children) > 0);
            $fullCats[$category->getID()] = $category;
        }

        return $fullCats;
    }

    public function categoryHasProducts(int $categoryID): bool
    {
        if (self::$prodCatAssociations === null) {
            self::$prodCatAssociations = [];
            $data                      = Shop::Container()->getDB()->getObjects(
                'SELECT tartikel.kArtikel, tkategorieartikel.kKategorie
                    FROM tkategorieartikel, tartikel
                    LEFT JOIN tartikelsichtbarkeit 
                        ON tartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                        AND tartikelsichtbarkeit.kKundengruppe = :cgid
                    WHERE tartikelsichtbarkeit.kArtikel IS NULL
                        AND tartikel.kArtikel = tkategorieartikel.kArtikel '
                . Shop::getProductFilter()->getFilterSQL()->getStockFilterSQL()
                . ' GROUP BY tkategorieartikel.kKategorie',
                ['cgid' => $this->customerGroupID]
            );
            foreach ($data as $item) {
                self::$prodCatAssociations[(int)$item->kKategorie] = 1;
            }
        }

        return isset(self::$prodCatAssociations[$categoryID]);
    }
}
