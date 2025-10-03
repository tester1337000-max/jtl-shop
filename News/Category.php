<?php

declare(strict_types=1);

namespace JTL\News;

use DateTime;
use Illuminate\Support\Collection;
use JTL\Cache\JTLCacheInterface;
use JTL\Contracts\RoutableInterface;
use JTL\DB\DbInterface;
use JTL\MagicCompatibilityTrait;
use JTL\Media\Image;
use JTL\Media\MultiSizeImage;
use JTL\Router\RoutableTrait;
use JTL\Router\Router;
use JTL\Shop;
use stdClass;

/**
 * Class Category
 * @package JTL\News
 */
class Category implements CategoryInterface, RoutableInterface
{
    use MagicCompatibilityTrait;
    use MultiSizeImage;
    use RoutableTrait;

    /**
     * @var array<string, string>
     */
    protected static array $mapping = [
        'dLetzteAktualisierung_de' => 'DateLastModified',
        'nSort'                    => 'Sort',
        'nAktiv'                   => 'IsActive',
        'kNewsKategorie'           => 'ID',
        'cName'                    => 'Name',
        'nLevel'                   => 'Level',
        'children'                 => 'Children'
    ];

    protected int $id = -1;

    protected int $parentID = 0;

    protected int $lft = 0;

    protected int $rght = 0;

    protected int $level = 1;

    /**
     * @var int[]
     */
    protected array $languageIDs = [];

    /**
     * @var string[]
     */
    protected array $languageCodes = [];

    /**
     * @var array<int, string>
     */
    protected array $names = [];

    /**
     * @var array<int, string>
     */
    protected array $seo = [];

    /**
     * @var array<int, string>
     */
    protected array $descriptions = [];

    /**
     * @var string[]
     */
    protected array $metaTitles = [];

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
    protected array $previewImages = [];

    protected int $sort = 0;

    protected bool $isActive = true;

    protected DateTime $dateLastModified;

    /**
     * @var Collection<int, Category>
     */
    protected Collection $children;

    /**
     * @var Collection<int, Item>
     */
    protected Collection $items;

    private JTLCacheInterface $cache;

    public function __construct(private readonly DbInterface $db, ?JTLCacheInterface $cache = null)
    {
        $this->items            = new Collection();
        $this->children         = new Collection();
        $this->cache            = $cache ?? Shop::Container()->getCache();
        $this->dateLastModified = new DateTime();
        $this->setRouteType(Router::TYPE_NEWS);
        $this->setImageType(Image::TYPE_NEWSCATEGORY);
    }

    /**
     * @inheritdoc
     */
    public function load(int $id, bool $activeOnly = true): self
    {
        $this->id          = $id;
        $activeFilter      = $activeOnly ? ' AND tnewskategorie.nAktiv = 1 ' : '';
        $categoryLanguages = $this->db->getObjects(
            "SELECT tnewskategorie.*, t.*, tseo.cSeo
                FROM tnewskategorie
                JOIN tnewskategoriesprache t
                    ON tnewskategorie.kNewsKategorie = t.kNewsKategorie
                JOIN tseo
                    ON tseo.cKey = 'kNewsKategorie'
                    AND tseo.kSprache = t.languageID
                    AND tseo.kKey = :cid
                WHERE tnewskategorie.kNewsKategorie = :cid" . $activeFilter,
            ['cid' => $this->id]
        );
        if (\count($categoryLanguages) === 0) {
            $this->setID(-1);

            return $this;
        }

        return $this->map($categoryLanguages, $activeOnly);
    }

    /**
     * @param stdClass[] $categoryLanguages
     * @param bool       $activeOnly
     * @return $this
     */
    public function map(array $categoryLanguages, bool $activeOnly = true): self
    {
        foreach ($categoryLanguages as $groupLanguage) {
            $langID                          = (int)$groupLanguage->languageID;
            $this->languageIDs[]             = $langID;
            $this->names[$langID]            = $groupLanguage->name;
            $this->metaDescriptions[$langID] = $groupLanguage->metaDescription;
            $this->metaTitles[$langID]       = $groupLanguage->metaTitle;
            $this->descriptions[$langID]     = $groupLanguage->description;
            $this->sort                      = (int)$groupLanguage->nSort;
            $this->previewImages[$langID]    = $groupLanguage->cPreviewImage;
            $this->isActive                  = (bool)$groupLanguage->nAktiv;
            $this->dateLastModified          = new DateTime($groupLanguage->dLetzteAktualisierung);
            $this->parentID                  = (int)($groupLanguage->kParent ?? 0);
            $this->level                     = (int)$groupLanguage->lvl;
            $this->lft                       = (int)$groupLanguage->lft;
            $this->rght                      = (int)$groupLanguage->rght;
            $this->seo[$langID]              = $groupLanguage->cSeo;
            $this->slugs[$langID]            = $groupLanguage->cSeo;
        }
        if (($preview = $this->getPreviewImage()) !== '') {
            $preview = \str_replace(\PFAD_NEWSKATEGORIEBILDER, '', $preview);
            $this->generateAllImageSizes(true, 1, $preview);
            $this->generateAllImageDimensions(1, $preview);
        }
        $this->createBySlug($this->id);
        $this->items = (new ItemList($this->db, $this->cache))->createItems(
            $this->db->getInts(
                'SELECT tnewskategorienews.kNews
                    FROM tnewskategorienews
                    JOIN tnews
                        ON tnews.kNews = tnewskategorienews.kNews 
                    WHERE kNewsKategorie = :cid' . ($activeOnly ? ' AND tnews.dGueltigVon <= NOW()' : ''),
                'kNews',
                ['cid' => $this->id]
            )
        );

        return $this;
    }

    public function getMonthOverview(int $id): self
    {
        $this->setID($id);
        $overview = $this->db->getSingleObject(
            'SELECT tnewsmonatsuebersicht.*, tseo.cSeo
                FROM tnewsmonatsuebersicht
                LEFT JOIN tseo
                    ON tseo.cKey = :cky
                    AND tseo.kKey = :oid
                WHERE tnewsmonatsuebersicht.kNewsMonatsUebersicht = :oid',
            [
                'cky' => 'kNewsMonatsUebersicht',
                'oid' => $id
            ]
        );
        if ($overview === null) {
            return $this;
        }
        $this->urls[Shop::getLanguageID()] = Shop::getURL() . '/' . $overview->cSeo;
        $this->setMetaTitle(
            Shop::Lang()->get('newsArchiv') . ' - ' . $overview->nMonat . '/' . $overview->nJahr,
            Shop::getLanguageID()
        );

        $this->items = (new ItemList($this->db, $this->cache))->createItems(
            $this->db->getInts(
                'SELECT tnews.kNews
                    FROM tnews
                    JOIN tnewskategorienews 
                        ON tnewskategorienews.kNews = tnews.kNews 
                    JOIN tnewskategorie 
                        ON tnewskategorie.kNewsKategorie = tnewskategorienews.kNewsKategorie
                        AND tnewskategorie.nAktiv = 1
                    WHERE MONTH(tnews.dGueltigVon) = :mnth 
                        AND YEAR(tnews.dGueltigVon) = :yr',
                'kNews',
                [
                    'mnth' => (int)$overview->nMonat,
                    'yr'   => (int)$overview->nJahr
                ]
            )
        );

        return $this;
    }

    public function getOverview(stdClass $filterSQL): self
    {
        $this->setID(0);
        $this->items = (new ItemList($this->db, $this->cache))->createItems(
            $this->db->getInts(
                'SELECT tnews.kNews
                    FROM tnews
                    JOIN tnewssprache 
                        ON tnews.kNews = tnewssprache.kNews
                    JOIN tnewskategorienews 
                        ON tnewskategorienews.kNews = tnews.kNews 
                    JOIN tnewskategorie 
                        ON tnewskategorie.kNewsKategorie = tnewskategorienews.kNewsKategorie
                WHERE tnewskategorie.nAktiv = 1 AND tnews.dGueltigVon <= NOW() '
                . $filterSQL->cNewsKatSQL . $filterSQL->cDatumSQL,
                'kNews'
            )
        );

        return $this;
    }

    public function buildMetaKeywords(): string
    {
        return \implode(
            ',',
            \array_filter(
                $this->items->slice(0, \min($this->items->count(), 6))
                    ->map(fn(Item $i): string => $i->getMetaKeyword())->all()
            )
        );
    }

    /**
     * @return Collection<int, Item>
     */
    public function filterAndSortItems(int $customerGroupID = 0, int $languageID = 0): Collection
    {
        switch ($_SESSION['NewsNaviFilter']->nSort) {
            case -1:
            case 1:
            default: // Datum absteigend
                $order = 'getDateValidFromNumeric';
                $dir   = 'desc';
                break;
            case 2: // Datum aufsteigend
                $order = 'getDateValidFromNumeric';
                $dir   = 'asc';
                break;
            case 3: // Name a ... z
                $order = 'getTitleUppercase';
                $dir   = 'asc';
                break;
            case 4: // Name z ... a
                $order = 'getTitleUppercase';
                $dir   = 'desc';
                break;
            case 5: // Anzahl Kommentare absteigend
                $order = 'getCommentCount';
                $dir   = 'desc';
                break;
            case 6: // Anzahl Kommentare aufsteigend
                $order = 'getCommentCount';
                $dir   = 'asc';
                break;
        }
        $cb = static function (Item $e) use ($order) {
            return $e->$order();
        };
        if ($customerGroupID > 0) {
            $this->items = $this->items->filter(fn(Item $i): bool => $i->checkVisibility($customerGroupID));
        }
        if ($languageID > 0) {
            $this->items = $this->items->filter(fn(Item $i): bool => $i->getTitle($languageID) !== '');
        }

        return $dir === 'asc'
            ? $this->items->sortBy($cb)
            : $this->items->sortByDesc($cb);
    }

    /**
     * @return Collection<int, Item>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /**
     * @param Collection<int, Item> $items
     */
    public function setItems(Collection $items): void
    {
        $this->items = $items;
    }

    /**
     * @inheritdoc
     */
    public function getName(?int $idx = null): string
    {
        return $this->names[$idx ?? Shop::getLanguageID()] ?? '';
    }

    /**
     * @inheritdoc
     */
    public function getNames(): array
    {
        return $this->names;
    }

    /**
     * @inheritdoc
     */
    public function setName(string $name, ?int $idx = null): void
    {
        $this->names[$idx ?? Shop::getLanguageID()] = $name;
    }

    /**
     * @inheritdoc
     */
    public function setNames(array $names): void
    {
        $this->names = $names;
    }

    /**
     * @inheritdoc
     */
    public function getMetaTitles(): array
    {
        return $this->metaTitles;
    }

    /**
     * @inheritdoc
     */
    public function getMetaTitle(?int $idx = null): string
    {
        return $this->metaTitles[$idx ?? Shop::getLanguageID()] ?? '';
    }

    /**
     * @inheritdoc
     */
    public function setMetaTitle(string $metaTitle, ?int $idx = null): void
    {
        $this->metaTitles[$idx ?? Shop::getLanguageID()] = $metaTitle;
    }

    /**
     * @inheritdoc
     */
    public function setMetaTitles(array $metaTitles): void
    {
        $this->metaTitles = $metaTitles;
    }

    /**
     * @inheritdoc
     */
    public function getMetaKeyword(?int $idx = null): string
    {
        return $this->metaKeywords[$idx ?? Shop::getLanguageID()] ?? '';
    }

    /**
     * @inheritdoc
     */
    public function getMetaKeywords(): array
    {
        return $this->metaKeywords;
    }

    /**
     * @inheritdoc
     */
    public function setMetaKeyword(string $metaKeyword, ?int $idx = null): void
    {
        $this->metaKeywords[$idx ?? Shop::getLanguageID()] = $metaKeyword;
    }

    /**
     * @inheritdoc
     */
    public function setMetaKeywords(array $metaKeywords): void
    {
        $this->metaKeywords = $metaKeywords;
    }

    /**
     * @inheritdoc
     */
    public function getMetaDescription(?int $idx = null): string
    {
        return $this->metaDescriptions[$idx ?? Shop::getLanguageID()] ?? '';
    }

    /**
     * @inheritdoc
     */
    public function getMetaDescriptions(): array
    {
        return $this->metaDescriptions;
    }

    /**
     * @inheritdoc
     */
    public function setMetaDescription(string $metaDescription, ?int $idx = null): void
    {
        $this->metaDescriptions[$idx ?? Shop::getLanguageID()] = $metaDescription;
    }

    /**
     * @inheritdoc
     */
    public function setMetaDescriptions(array $metaDescriptions): void
    {
        $this->metaDescriptions = $metaDescriptions;
    }

    /**
     * @inheritdoc
     */
    public function getURL(?int $idx = null): string
    {
        // @todo: category or month overview?
        // return $this->urls[$idx ?? Shop::getLanguageID()] ?? '/?nm=' . $this->getID();
        return $this->urls[$idx ?? Shop::getLanguageID()] ?? '/?nk=' . $this->getID();
    }

    /**
     * @inheritdoc
     */
    public function getURLs(): array
    {
        return $this->urls;
    }

    /**
     * @inheritdoc
     */
    public function getSEO(?int $idx = null): string
    {
        return $this->seo[$idx ?? Shop::getLanguageID()] ?? '';
    }

    /**
     * @inheritdoc
     */
    public function getSEOs(): array
    {
        return $this->seo;
    }

    /**
     * @inheritdoc
     */
    public function setSEOs(array $seos): void
    {
        $this->seo = $seos;
    }

    /**
     * @inheritdoc
     */
    public function setSEO(string $seo, ?int $idx = null): void
    {
        $this->seo[$idx ?? Shop::getLanguageID()] = $seo;
    }

    /**
     * @inheritdoc
     */
    public function getID(): int
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     */
    public function setID(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @inheritdoc
     */
    public function getParentID(): int
    {
        return $this->parentID;
    }

    /**
     * @inheritdoc
     */
    public function setParentID(int $parentID): void
    {
        $this->parentID = $parentID;
    }

    /**
     * @inheritdoc
     */
    public function getLanguageID(?int $idx = null): int
    {
        return $this->languageIDs[$idx ?? Shop::getLanguageID()] ?? 0;
    }

    /**
     * @inheritdoc
     */
    public function getLanguageIDs(): array
    {
        return $this->languageIDs;
    }

    /**
     * @inheritdoc
     */
    public function setLanguageIDs(array $languageIDs): void
    {
        $this->languageIDs = $languageIDs;
    }

    /**
     * @inheritdoc
     */
    public function getLanguageCode(?int $idx = null): string
    {
        return $this->languageCodes[$idx ?? Shop::getLanguageID()] ?? '';
    }

    /**
     * @inheritdoc
     */
    public function getLanguageCodes(): array
    {
        return $this->languageCodes;
    }

    /**
     * @inheritdoc
     */
    public function setLanguageCodes(array $languageCodes): void
    {
        $this->languageCodes = $languageCodes;
    }

    /**
     * @inheritdoc
     */
    public function getDescription(?int $idx = null): string
    {
        return $this->descriptions[$idx ?? Shop::getLanguageID()] ?? '';
    }

    /**
     * @inheritdoc
     */
    public function getDescriptions(): array
    {
        return $this->descriptions;
    }

    /**
     * @inheritdoc
     */
    public function setDescription(string $description, ?int $idx = null): void
    {
        $this->descriptions[$idx ?? Shop::getLanguageID()] = $description;
    }

    /**
     * @inheritdoc
     */
    public function setDescriptions(array $descriptions): void
    {
        $this->descriptions = $descriptions;
    }

    /**
     * @inheritdoc
     */
    public function getPreviewImage(?int $idx = null): string
    {
        return $this->previewImages[$idx ?? Shop::getLanguageID()] ?? '';
    }

    /**
     * @inheritdoc
     */
    public function getPreviewImages(): array
    {
        return $this->previewImages;
    }

    /**
     * @inheritdoc
     */
    public function setPreviewImage(string $image, ?int $idx = null): void
    {
        $this->previewImages[$idx ?? Shop::getLanguageID()] = $image;
    }

    /**
     * @inheritdoc
     */
    public function setPreviewImages(array $previewImages): void
    {
        $this->previewImages = $previewImages;
    }

    /**
     * @inheritdoc
     */
    public function getSort(): int
    {
        return $this->sort;
    }

    /**
     * @inheritdoc
     */
    public function setSort(int $sort): void
    {
        $this->sort = $sort;
    }

    /**
     * @inheritdoc
     */
    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    /**
     * @inheritdoc
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * @inheritdoc
     */
    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    /**
     * @inheritdoc
     */
    public function getDateLastModified(): DateTime
    {
        return $this->dateLastModified;
    }

    /**
     * @inheritdoc
     */
    public function setDateLastModified(DateTime $dateLastModified): void
    {
        $this->dateLastModified = $dateLastModified;
    }

    /**
     * @inheritdoc
     */
    public function getLevel(): int
    {
        return $this->level;
    }

    /**
     * @inheritdoc
     */
    public function setLevel(int $level): void
    {
        $this->level = $level;
    }

    /**
     * @inheritdoc
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    /**
     * @inheritdoc
     */
    public function addChild(Category $child): void
    {
        $this->children->push($child);
    }

    /**
     * @inheritdoc
     */
    public function setChildren(Collection $children): void
    {
        $this->children = $children;
    }

    /**
     * @inheritdoc
     */
    public function getLft(): int
    {
        return $this->lft;
    }

    /**
     * @inheritdoc
     */
    public function setLft(int $lft): void
    {
        $this->lft = $lft;
    }

    /**
     * @inheritdoc
     */
    public function getRght(): int
    {
        return $this->rght;
    }

    /**
     * @inheritdoc
     */
    public function setRght(int $rght): void
    {
        $this->rght = $rght;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $res       = \get_object_vars($this);
        $res['db'] = '*truncated*';

        return $res;
    }
}
