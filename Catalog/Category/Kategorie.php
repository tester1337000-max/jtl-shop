<?php

declare(strict_types=1);

namespace JTL\Catalog\Category;

use JTL\Contracts\RoutableInterface;
use JTL\Customer\CustomerGroup;
use JTL\DB\DbInterface;
use JTL\Helpers\Category;
use JTL\Language\LanguageHelper;
use JTL\MagicCompatibilityTrait;
use JTL\Media\Image;
use JTL\Media\MultiSizeImage;
use JTL\Router\RoutableTrait;
use JTL\Router\Router;
use JTL\Session\Frontend;
use JTL\Shop;
use stdClass;

use function Functional\first;

/**
 * Class Kategorie
 * @package JTL\Catalog\Category
 */
class Kategorie implements RoutableInterface
{
    use MultiSizeImage;
    use MagicCompatibilityTrait;
    use RoutableTrait;

    /**
     * @var array<string, string>
     */
    public static array $mapping = [
        'kSprache'                   => 'CurrentLanguageID',
        'cName'                      => 'Name',
        'bUnterKategorien'           => 'HasSubcategories',
        'kKategorie'                 => 'ID',
        'kOberKategorie'             => 'ParentID',
        'nSort'                      => 'Sort',
        'cBeschreibung'              => 'Description',
        'cTitleTag'                  => 'MetaTitle',
        'cMetaDescription'           => 'MetaDescription',
        'cMetaKeywords'              => 'MetaKeywords',
        'cKurzbezeichnung'           => 'ShortName',
        'lft'                        => 'Left',
        'rght'                       => 'Right',
        'categoryFunctionAttributes' => 'CategoryFunctionAttributes',
        'categoryAttributes'         => 'CategoryAttributes',
        'cSeo'                       => 'Slug',
        'cURL'                       => 'URL',
        'cURLFull'                   => 'URL',
        'cKategoriePfad'             => 'CategoryPathString',
        'cKategoriePfad_arr'         => 'CategoryPath',
        'cBildpfad'                  => 'ImagePath',
        'cBild'                      => 'Image',
        'cBildURL'                   => 'Image',
    ];

    private int $parentID = 0;

    private int $sort = 0;

    /**
     * @var string[]
     */
    private array $names = [];

    /**
     * @var string[]
     */
    private array $shortNames = [];

    /**
     * @var array<int, string>
     */
    private array $categoryPathString = [];

    /**
     * @var array<int, string[]>
     */
    private array $categoryPath = [];

    private string $imagePath;

    private string $image = \BILD_KEIN_KATEGORIEBILD_VORHANDEN;

    private bool $hasImage = false;

    /**
     * @var array<string, string>
     */
    private array $categoryFunctionAttributes = [];

    /**
     * @var array<int, array<string, stdClass>>
     */
    private array $categoryAttributes = [];

    public bool $hasSubcategories = false;

    /**
     * @var array<int, string|null>
     */
    protected array $descriptions = [];

    /**
     * @var string[]
     */
    protected array $metaKeywords = [];

    /**
     * @var string[]
     */
    protected array $metaDescriptions = [];

    /**
     * @var string[]
     */
    protected array $metaTitles = [];

    protected int $languageID;

    protected ?int $id = null;

    protected int $lft = 0;

    protected int $rght = 0;

    /**
     * @var self[]|null
     */
    private ?array $subCategories = null;

    public bool $bAktiv = true;

    public ?string $customImgName = null;

    private ?string $dLetzteAktualisierung = null;

    private bool $compressed = false;

    private DbInterface $db;

    public function __construct(
        int $id = 0,
        int $languageID = 0,
        int $customerGroupID = 0,
        bool $noCache = false,
        ?DbInterface $db = null
    ) {
        $this->db = $db ?? Shop::Container()->getDB();
        $this->setImageType(Image::TYPE_CATEGORY);
        $this->setRouteType(Router::TYPE_CATEGORY);
        $languageID = $languageID ?: Shop::getLanguageID();
        $fallback   = LanguageHelper::getDefaultLanguage()->getId();
        if (!$languageID) {
            $languageID = $fallback;
        }
        $this->initLanguageID($fallback);
        $this->setCurrentLanguageID($languageID);
        if ($id > 0) {
            $this->loadFromDB($id, $languageID, $customerGroupID, $noCache);
        }
    }

    public function loadFromDB(int $id, int $languageID = 0, int $customerGroupID = 0, bool $noCache = false): self
    {
        $customerGroupID = $customerGroupID
            ?: Frontend::getCustomerGroup()->getID()
                ?: CustomerGroup::getDefaultGroupID();
        $languageID      = $languageID ?: $this->currentLanguageID;
        $cacheID         = \CACHING_GROUP_CATEGORY . '_' . $id . '_cg_' . $customerGroupID;
        $category        = false;
        if ($noCache === false) {
            /** @var self|false $category */
            $category = Shop::Container()->getCache()->get($cacheID);
        }
        if ($category !== false) {
            return $this->loadCachedObject($category, $languageID);
        }
        $items = $this->db->getObjects(
            'SELECT tkategorie.kKategorie, tkategorie.kOberKategorie, 
                tkategorie.nSort, tkategorie.dLetzteAktualisierung,
                tkategoriepict.cPfad,
                atr.cWert AS customImgName, tkategorie.lft, tkategorie.rght,
                COALESCE(tseo.cSeo, tkategoriesprache.cSeo, \'\') cSeo,
                COALESCE(tkategoriesprache.cName, tkategorie.cName) cName,
                COALESCE(tkategoriesprache.cBeschreibung, tkategorie.cBeschreibung) cBeschreibung,
                COALESCE(tkategoriesprache.cMetaDescription, \'\') cMetaDescription,
                COALESCE(tkategoriesprache.cMetaKeywords, \'\') cMetaKeywords,
                COALESCE(tkategoriesprache.cTitleTag, \'\') cTitleTag,
                tsprache.kSprache
                FROM tkategorie
                JOIN tsprache
                    ON tsprache.active = 1
                LEFT JOIN tkategoriesichtbarkeit ON tkategoriesichtbarkeit.kKategorie = tkategorie.kKategorie
                    AND tkategoriesichtbarkeit.kKundengruppe = :cgid
                LEFT JOIN tseo ON tseo.cKey = \'kKategorie\'
                    AND tseo.kKey = :kid
                    AND tseo.kSprache = tsprache.kSprache
                LEFT JOIN tkategoriesprache 
                    ON tkategoriesprache.kKategorie = tkategorie.kKategorie
                    AND tkategoriesprache.kSprache = tseo.kSprache
                    AND tkategoriesprache.kSprache = tsprache.kSprache
                LEFT JOIN tkategoriepict 
                    ON tkategoriepict.kKategorie = tkategorie.kKategorie
                LEFT JOIN tkategorieattribut atr
                    ON atr.kKategorie = tkategorie.kKategorie
                    AND atr.cName = \'bildname\' 
                WHERE tkategorie.kKategorie = :kid
                    AND tkategoriesichtbarkeit.kKategorie IS NULL',
            ['kid' => $id, 'cgid' => $customerGroupID]
        );
        $this->mapData($items, $customerGroupID);
        $this->createBySlug($id);
        /** @var stdClass|null $first */
        $first = first($items);
        if ($first !== null) {
            $this->addImage($first);
        }
        $this->addAttributes();
        $this->hasSubcategories = $this->db->select('tkategorie', 'kOberKategorie', $this->getID()) !== null;
        foreach ($items as $item) {
            $currentLangID = (int)$item->kSprache;
            $this->setShortName(
                $this->getCategoryAttributeValue(\ART_ATTRIBUT_SHORTNAME, $currentLangID)
                ?? $this->getName($currentLangID),
                $currentLangID
            );
        }
        $cacheTags = [\CACHING_GROUP_CATEGORY . '_' . $id, \CACHING_GROUP_CATEGORY];
        \executeHook(\HOOK_KATEGORIE_CLASS_LOADFROMDB, [
            'oKategorie' => &$this,
            'cacheTags'  => &$cacheTags,
            'cached'     => false
        ]);
        if ($noCache === false) {
            $this->saveToCache($cacheID, $cacheTags);
        }

        return $this;
    }

    private function loadCachedObject(self $category, int $languageID): self
    {
        foreach (\get_object_vars($category) as $k => $v) {
            $this->$k = $v;
        }
        $this->currentLanguageID = $languageID;
        if ($this->compressed === true) {
            foreach ($this->descriptions as &$description) {
                $description = \gzuncompress($description ?? '');
            }
            unset($description);
            $this->compressed = false;
        }
        \executeHook(\HOOK_KATEGORIE_CLASS_LOADFROMDB, [
            'oKategorie' => &$this,
            'cacheTags'  => [],
            'cached'     => true
        ]);

        return $this;
    }

    /**
     * @param string[] $cacheTags
     */
    private function saveToCache(string $cacheID, array $cacheTags): void
    {
        $toSave = clone $this;
        if (\COMPRESS_DESCRIPTIONS === true) {
            foreach ($toSave->descriptions as &$description) {
                $description = \gzcompress($description ?? '', \COMPRESSION_LEVEL);
            }
            unset($description);
            $toSave->compressed = true;
        }
        Shop::Container()->getCache()->set($cacheID, $toSave, $cacheTags);
    }

    private function addImage(stdClass $item): void
    {
        $imageBaseURL   = Shop::getImageBaseURL();
        $this->image    = $imageBaseURL . \BILD_KEIN_KATEGORIEBILD_VORHANDEN;
        $this->hasImage = false;
        if (isset($item->cPfad) && \mb_strlen($item->cPfad) > 0) {
            $this->imagePath = $item->cPfad;
            $this->image     = $imageBaseURL . \PFAD_KATEGORIEBILDER . $item->cPfad;
            $this->hasImage  = true;
            $this->generateAllImageSizes(true, 1, $this->imagePath);
            $this->generateAllImageDimensions(1, $this->imagePath);
        }
    }

    private function addAttributes(): void
    {
        $this->categoryFunctionAttributes = [];
        $this->categoryAttributes         = [];
        $attributes                       = $this->db->getCollection(
            'SELECT COALESCE(tkategorieattributsprache.cName, tkategorieattribut.cName) cName,
                    COALESCE(tkategorieattributsprache.cWert, tkategorieattribut.cWert) cWert,
                    COALESCE(tkategorieattributsprache.kSprache, tsprache.kSprache) kSprache,
                    tkategorieattribut.bIstFunktionsAttribut, tkategorieattribut.nSort
                FROM tkategorieattribut
                LEFT JOIN tkategorieattributsprache 
                    ON tkategorieattributsprache.kAttribut = tkategorieattribut.kKategorieAttribut
                LEFT JOIN tsprache
                    ON tsprache.cStandard = \'Y\'
                WHERE kKategorie = :cid
                ORDER BY tkategorieattribut.bIstFunktionsAttribut DESC, tkategorieattribut.nSort',
            ['cid' => $this->getID()]
        )->map(static function (stdClass $item): stdClass {
            $item->kSprache              = (int)$item->kSprache;
            $item->bIstFunktionsAttribut = (int)$item->bIstFunktionsAttribut;
            $item->nSort                 = (int)$item->nSort;

            return $item;
        })->groupBy('kSprache')->toArray();
        /** @var array<int, array<stdClass>> $attributes */
        foreach ($attributes as $langID => $localizedAttributes) {
            if ($langID > 0) {
                $this->categoryAttributes[$langID] = [];
            }
            foreach ($localizedAttributes as $attribute) {
                // Aus Kompatibilitätsgründen findet hier KEINE Trennung
                // zwischen Funktions- und lokalisierten Attributen statt
                $this->setLocalizedAttribute($attribute, $langID);
            }
        }
    }

    public function setLocalizedAttribute(stdClass $attribute, int $langID): void
    {
        if ($attribute->cName === 'meta_title' && $this->getMetaTitle($langID) === '') {
            $this->setMetaTitle($attribute->cWert, $langID);
        } elseif ($attribute->cName === 'meta_description' && $this->getMetaDescription($langID) === '') {
            $this->setMetaDescription($attribute->cWert, $langID);
        } elseif ($attribute->cName === 'meta_keywords' && $this->getMetaKeywords($langID) === '') {
            $this->setMetaKeywords($attribute->cWert, $langID);
        }
        $idx = \mb_convert_case($attribute->cName, \MB_CASE_LOWER);
        if ($attribute->bIstFunktionsAttribut) {
            $this->categoryFunctionAttributes[$idx] = $attribute->cWert;
        } else {
            $this->categoryAttributes[$langID][$idx] = $attribute;
        }
    }

    /**
     * @param stdClass[] $data
     */
    public function mapData(array $data, int $customerGroupID): self
    {
        foreach ($data as $item) {
            $languageID                  = (int)$item->kSprache;
            $this->parentID              = (int)$item->kOberKategorie;
            $this->id                    = (int)$item->kKategorie;
            $this->sort                  = (int)$item->nSort;
            $this->dLetzteAktualisierung = $item->dLetzteAktualisierung;
            $this->setDescription($item->cBeschreibung, $languageID);
            $this->customImgName = $item->customImgName;
            $this->lft           = (int)$item->lft;
            $this->rght          = (int)$item->rght;
            if ($item->cSeo !== '') {
                $this->setSlug($item->cSeo, $languageID);
            }
            if (\mb_strlen($item->cName) > 0) {
                // non-localized categories may have an empty string as name - but the fallback uses NULL
                $this->setName($item->cName, $languageID);
            }
            $this->setDescription($item->cBeschreibung, $languageID);
            $this->setMetaDescription($item->cMetaDescription, $languageID);
            $this->setMetaKeywords($item->cMetaKeywords, $languageID);
            $this->setMetaTitle($item->cTitleTag, $languageID);
            $this->setPaths($languageID, $customerGroupID);
        }

        return $this;
    }

    private function setPaths(int $languageID, int $customerGroupID): void
    {
        $col  = Category::getInstance($languageID, $customerGroupID)->getFlatTree($this->getID());
        $path = \array_map(static fn(MenuItem $e): string => $e->getName(), $col);
        $this->setCategoryPath($path, $languageID);
        $this->setCategoryPathString(\implode(' > ', $path), $languageID);
        if (\CATEGORIES_SLUG_HIERARCHICALLY === false) {
            return;
        }
        $this->setSlug(\implode('/', \array_map(static fn(MenuItem $e): string => $e->getSeo(), $col)), $languageID);
    }

    public function existierenUnterkategorien(): bool
    {
        return $this->hasSubcategories === true;
    }

    /**
     * @deprecated since 5.5.0
     */
    public function getKategorieBild(bool $full = false): ?string
    {
        \trigger_error(__METHOD__ . ' is deprecated - use getImageURL() instead.', \E_USER_DEPRECATED);
        $url = $this->getImageURL();
        if ($this->id <= 0 || $url === null) {
            return null;
        }

        return $full === true
            ? $url
            : \ltrim((\parse_url($url, \PHP_URL_PATH) ?: ''), '/');
    }

    public function istUnterkategorie(): int|false
    {
        if ($this->getID() <= 0) {
            return false;
        }

        return $this->parentID > 0 ? $this->parentID : false;
    }

    public static function isVisible(int $id, int $customerGroupID): bool
    {
        if (!Shop::has('checkCategoryVisibility')) {
            Shop::set(
                'checkCategoryVisibility',
                Shop::Container()->getDB()->getAffectedRows('SELECT kKategorie FROM tkategoriesichtbarkeit') > 0
            );
        }
        if (!Shop::get('checkCategoryVisibility')) {
            return true;
        }
        $data = Shop::Container()->getDB()->select(
            'tkategoriesichtbarkeit',
            'kKategorie',
            $id,
            'kKundengruppe',
            $customerGroupID
        );

        return $data === null || empty($data->kKategorie);
    }

    public function getID(): int
    {
        return $this->id ?? 0;
    }

    public function setID(int $id): void
    {
        $this->id = $id;
    }

    public function getName(?int $idx = null): string
    {
        return $this->names[$idx ?? $this->currentLanguageID] ?? $this->names[$this->fallbackLanguageID] ?? '';
    }

    public function setName(string $name, ?int $idx = null): void
    {
        $this->names[$idx ?? $this->currentLanguageID] = $name;
    }

    public function getShortName(?int $idx = null): string
    {
        return $this->shortNames[$idx ?? $this->currentLanguageID]
            ?? $this->shortNames[$this->fallbackLanguageID] ?? '';
    }

    public function setShortName(string $name, ?int $idx = null): void
    {
        $this->shortNames[$idx ?? $this->currentLanguageID] = $name;
    }

    public function getParentID(): int
    {
        return $this->parentID;
    }

    public function setParentID(int $parentID): void
    {
        $this->parentID = $parentID;
    }

    public function getLanguageID(): ?int
    {
        return $this->currentLanguageID;
    }

    public function getSort(): int
    {
        return $this->sort;
    }

    public function setSort(int $sort): void
    {
        $this->sort = $sort;
    }

    public function hasImage(): bool
    {
        return $this->hasImage === true;
    }

    public function getImageURL(): ?string
    {
        return $this->image;
    }

    public function getImageAlt(): string
    {
        return $this->getCategoryAttributeValue('img_alt') ?? '';
    }

    public function getMetaTitle(?int $idx = null): string
    {
        return $this->metaTitles[$idx ?? $this->currentLanguageID] ?? '';
    }

    public function setMetaTitle(string $metaTitle, ?int $idx = null): void
    {
        $this->metaTitles[$idx ?? $this->currentLanguageID] = $metaTitle;
    }

    public function getMetaKeywords(?int $idx = null): string
    {
        return $this->metaKeywords[$idx ?? $this->currentLanguageID] ?? '';
    }

    public function setMetaKeywords(string $metaKeywords, ?int $idx = null): void
    {
        $this->metaKeywords[$idx ?? $this->currentLanguageID] = $metaKeywords;
    }

    public function getMetaDescription(?int $idx = null): string
    {
        return $this->metaDescriptions[$idx ?? $this->currentLanguageID] ?? '';
    }

    public function setMetaDescription(string $metaDescription, ?int $idx = null): void
    {
        $this->metaDescriptions[$idx ?? $this->currentLanguageID] = $metaDescription;
    }

    public function getDescription(?int $idx = null): string
    {
        return $this->descriptions[$idx ?? $this->currentLanguageID] ?? '';
    }

    public function setDescription(?string $description, ?int $idx = null): void
    {
        $this->descriptions[$idx ?? $this->currentLanguageID] = $description;
    }

    public function getCategoryAttribute(string $name, ?int $idx = null): ?stdClass
    {
        return $this->categoryAttributes[$idx ?? $this->currentLanguageID][$name] ?? null;
    }

    public function setCategoryAttribute(string $name, stdClass $attribute, ?int $idx = null): void
    {
        $this->categoryAttributes[$idx ?? $this->currentLanguageID][$name] = $attribute;
    }

    /**
     * @param array<string, stdClass> $attributes
     * @param int|null                $idx
     */
    public function setCategoryAttributes(array $attributes, ?int $idx = null): void
    {
        $this->categoryAttributes[$idx ?? $this->currentLanguageID] = $attributes;
    }

    public function getCategoryAttributeValue(string $name, ?int $idx = null): ?string
    {
        return $this->categoryAttributes[$idx ?? $this->currentLanguageID][$name]->cWert ?? null;
    }

    /**
     * @param int|null $idx
     * @return array<string, stdClass>
     */
    public function getCategoryAttributes(?int $idx = null): array
    {
        return $this->categoryAttributes[$idx ?? $this->currentLanguageID] ?? [];
    }

    public function getCategoryFunctionAttribute(string $name): ?string
    {
        return $this->categoryFunctionAttributes[$name] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function getCategoryFunctionAttributes(): array
    {
        return $this->categoryFunctionAttributes;
    }

    public function setCategoryFunctionAttribute(string $name, string $attribute): void
    {
        $this->categoryFunctionAttributes[$name] = $attribute;
    }

    /**
     * @param array<string, string> $attributes
     */
    public function setCategoryFunctionAttributes(array $attributes): void
    {
        $this->categoryFunctionAttributes = $attributes;
    }

    public function getLeft(): int
    {
        return $this->lft;
    }

    public function setLeft(int $lft): void
    {
        $this->lft = $lft;
    }

    public function getRight(): int
    {
        return $this->rght;
    }

    public function setRight(int $rght): void
    {
        $this->rght = $rght;
    }

    public function hasSubcategories(): bool
    {
        return $this->hasSubcategories;
    }

    public function getHasSubcategories(): bool
    {
        return $this->hasSubcategories;
    }

    public function setHasSubcategories(bool $hasSubcategories): void
    {
        $this->hasSubcategories = $hasSubcategories;
    }

    public function getCategoryPathString(?int $idx = null): string
    {
        return $this->categoryPathString[$idx ?? $this->currentLanguageID] ?? '';
    }

    public function setCategoryPathString(string $categoryPath, ?int $idx = null): void
    {
        $this->categoryPathString[$idx ?? $this->currentLanguageID] = $categoryPath;
    }

    /**
     * @param int|null $idx
     * @return string[]
     */
    public function getCategoryPath(?int $idx = null): array
    {
        return $this->categoryPath[$idx ?? $this->currentLanguageID] ?? [];
    }

    /**
     * @param string[] $categoryPath
     * @param int|null $idx
     */
    public function setCategoryPath(array $categoryPath, ?int $idx = null): void
    {
        $this->categoryPath[$idx ?? $this->currentLanguageID] = $categoryPath;
    }

    /**
     * @return self[]|null
     */
    public function getSubCategories(): ?array
    {
        return $this->subCategories;
    }

    /**
     * @param self[]|null $subCategories
     */
    public function setSubCategories(?array $subCategories): void
    {
        $this->subCategories = $subCategories;
    }

    public function addSubCategory(self $subCategory): void
    {
        $this->subCategories[] = $subCategory;
    }

    public function getImagePath(): string
    {
        return $this->imagePath;
    }

    public function setImagePath(string $imagePath): void
    {
        $this->imagePath = $imagePath;
    }

    public function getDB(): ?DbInterface
    {
        return $this->db;
    }

    public function setDB(DbInterface $db): void
    {
        $this->db = $db;
    }
}
