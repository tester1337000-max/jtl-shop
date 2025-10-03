<?php

declare(strict_types=1);

namespace JTL\Catalog;

use Illuminate\Support\Collection;
use JTL\Contracts\RoutableInterface;
use JTL\DB\SqlObject;
use JTL\Helpers\Text;
use JTL\MagicCompatibilityTrait;
use JTL\Media\Image;
use JTL\Media\MultiSizeImage;
use JTL\Router\RoutableTrait;
use JTL\Router\Router;
use JTL\Session\Frontend;
use JTL\Shop;
use stdClass;

/**
 * Class Hersteller
 * @package JTL\Catalog
 */
class Hersteller implements RoutableInterface
{
    use MultiSizeImage;
    use MagicCompatibilityTrait;
    use RoutableTrait;

    private int $kHersteller = 0;

    /**
     * @var string[]
     */
    private array $names = [];

    private string $originalSeo = '';

    /**
     * @var string[]
     */
    private array $metaTitles = [];

    /**
     * @var string[]
     */
    private array $metaKeywords = [];

    /**
     * @var string[]
     */
    private array $metaDescriptions = [];

    /**
     * @var string[]
     */
    private array $descriptions = [];

    private string $imagePath = '';

    private int $nSortNr = 0;

    private string $cURL = '';

    /**
     * @var array<string, string>
     */
    protected static array $mapping = [
        'cURL'             => 'URL',
        'cURLFull'         => 'URL',
        'nSortNr'          => 'SortNo',
        'cBildpfad'        => 'ImagePath',
        'cBeschreibung'    => 'Description',
        'kHersteller'      => 'ID',
        'cName'            => 'Name',
        'cMetaTitle'       => 'MetaTitle',
        'cMetaKeywords'    => 'MetaKeywords',
        'cMetaDescription' => 'MetaDescription',
        'cSeo'             => 'Seo',
        'originalSeo'      => 'OriginalSeo',
        'cHomepage'        => 'Homepage'
    ];

    private string $imagePathSmall = \BILD_KEIN_HERSTELLERBILD_VORHANDEN;

    private string $imagePathNormal = \BILD_KEIN_HERSTELLERBILD_VORHANDEN;

    private string $imageURLSmall = '';

    private string $imageURLNormal = '';

    private string $homepage = '';

    public function __construct(int $id = 0, int $languageID = 0, bool $noCache = false)
    {
        $this->initLanguageID($languageID);
        $this->setImageType(Image::TYPE_MANUFACTURER);
        $this->setRouteType(Router::TYPE_MANUFACTURER);
        if ($id > 0) {
            $this->loadFromDB($id, $this->currentLanguageID, $noCache);
        }
    }

    public function __wakeup(): void
    {
        $this->initLanguageID();
    }

    public function loadFromDB(int $id, int $languageID = 0, bool $noCache = false): self
    {
        if ($languageID === 0 && $this->currentLanguageID === 0) {
            $this->initLanguageID();
        } elseif ($languageID > 0 && $this->currentLanguageID !== $languageID) {
            $this->initLanguageID($languageID);
        }
        $cacheID   = 'manuf_' . $id;
        $cacheTags = [\CACHING_GROUP_MANUFACTURER];
        $cached    = true;
        $data      = false;
        if ($noCache === false) {
            /** @var stdClass[]|false $data */
            $data = Shop::Container()->getCache()->get($cacheID);
        }
        if ($data === false) {
            $data   = Shop::Container()->getDB()->getObjects(
                "SELECT thersteller.kHersteller, thersteller.cName, thersteller.cHomepage, thersteller.nSortNr, 
                    thersteller.cBildpfad, therstellersprache.cMetaTitle, therstellersprache.cMetaKeywords, 
                    therstellersprache.cMetaDescription, therstellersprache.cBeschreibung,
                    tseo.cSeo, thersteller.cSeo AS originalSeo, therstellersprache.kSprache 
                    FROM thersteller
                    LEFT JOIN therstellersprache 
                        ON therstellersprache.kHersteller = thersteller.kHersteller
                    LEFT JOIN tseo 
                        ON tseo.kKey = thersteller.kHersteller
                        AND tseo.cKey = 'kHersteller'
                        AND tseo.kSprache = therstellersprache.kSprache
                    WHERE thersteller.kHersteller = :manfID
                        AND thersteller.nAktiv = 1
                    GROUP BY kSprache, thersteller.kHersteller",
                ['manfID' => $id]
            );
            $cached = false;
        }
        foreach ($data as $manufacturer) {
            \executeHook(\HOOK_HERSTELLER_CLASS_LOADFROMDB, [
                'oHersteller' => &$manufacturer,
                'cached'      => $cached,
                'cacheTags'   => &$cacheTags
            ]);
        }
        $data = $this->map($data);
        if ($cached === false && $noCache === false) {
            Shop::Container()->getCache()->set($cacheID, $data, $cacheTags);
        }

        return $this;
    }

    /**
     * @param stdClass[] $data
     * @return stdClass[]
     */
    public function map(array $data): array
    {
        $routesLoaded = false;
        foreach ($data as $item) {
            $langID = (int)$item->kSprache;
            $this->setImagePath($item->cBildpfad);
            $this->setID((int)$item->kHersteller);
            $this->setSortNo((int)$item->nSortNr);
            $this->setName($item->cName ?? '', $langID);
            $this->setMetaTitle($item->cMetaTitle ?? '', $langID);
            $this->setMetaKeywords($item->cMetaKeywords ?? '', $langID);
            $this->setMetaDescription($item->cMetaDescription ?? '', $langID);
            $this->setDescription($item->cBeschreibung ?? '', $langID);
            $this->setSlug($item->cSeo ?? '', $langID);
            $this->setOriginalSeo($item->originalSeo ?? '');
            $this->setHomepage(Text::filterURL($item->cHomepage ?? '', true, true) ?: '');
            if (isset($item->urlPath, $item->url)) {
                $routesLoaded = true;
                $this->setURLPath($item->urlPath, $langID);
                $this->setURL($item->url, $langID);
            }
        }
        $this->loadImages();
        if ($routesLoaded === true) {
            return $data;
        }
        $this->createBySlug($this->getID());
        // add urlPath and url for saving to object cache
        foreach ($data as $item) {
            $langID        = (int)$item->kSprache;
            $item->urlPath = $this->getURLPath($langID);
            $item->url     = $this->getURL($langID);
        }

        return $data;
    }

    private function loadImages(): self
    {
        $imageBaseURL          = Shop::getImageBaseURL();
        $this->imagePathSmall  = \BILD_KEIN_HERSTELLERBILD_VORHANDEN;
        $this->imagePathNormal = \BILD_KEIN_HERSTELLERBILD_VORHANDEN;
        if ($this->imagePath !== '') {
            $this->imagePathSmall  = \PFAD_HERSTELLERBILDER_KLEIN . $this->imagePath;
            $this->imagePathNormal = \PFAD_HERSTELLERBILDER_NORMAL . $this->imagePath;
        }
        $this->generateAllImageSizes(true, 1, $this->imagePath);
        if ($this->imagePath !== '') {
            $this->generateAllImageDimensions(1, $this->imagePath);
        }
        $this->imageURLSmall  = $imageBaseURL . $this->imagePathSmall;
        $this->imageURLNormal = $imageBaseURL . $this->imagePathNormal;

        return $this;
    }

    /**
     * @return self[]
     */
    public static function getAll(bool $productLookup = true, int $languageID = 0, int $customerGroupID = 0): array
    {
        $customerGroupID = $customerGroupID ?: Frontend::getCustomerGroup()->getID();
        $sql             = new SqlObject();
        $sql->setWhere('thersteller.nAktiv = 1');
        if ($productLookup) {
            $sql->setWhere(
                'EXISTS (
                    SELECT 1
                    FROM tartikel
                    WHERE tartikel.kHersteller = thersteller.kHersteller
                        ' . Shop::getProductFilter()->getFilterSQL()->getStockFilterSQL() . '
                        AND NOT EXISTS (
                        SELECT 1 FROM tartikelsichtbarkeit
                        WHERE tartikelsichtbarkeit.kArtikel = tartikel.kArtikel
                            AND tartikelsichtbarkeit.kKundengruppe = :cgid))'
            );
            $sql->addParam(':cgid', $customerGroupID);
        }

        return Shop::Container()->getDB()->getCollection(
            "SELECT thersteller.kHersteller, thersteller.cName, thersteller.cHomepage, thersteller.nSortNr, 
                thersteller.cBildpfad, therstellersprache.cMetaTitle, therstellersprache.cMetaKeywords, 
                therstellersprache.cMetaDescription, therstellersprache.cBeschreibung,
                tseo.cSeo, thersteller.cSeo AS originalSeo, therstellersprache.kSprache
                FROM thersteller
                LEFT JOIN therstellersprache 
                    ON therstellersprache.kHersteller = thersteller.kHersteller
                LEFT JOIN tseo 
                    ON tseo.kKey = thersteller.kHersteller
                    AND tseo.cKey = 'kHersteller'
                    AND tseo.kSprache = therstellersprache.kSprache
                WHERE " . $sql->getWhere() . '
                GROUP BY thersteller.kHersteller, therstellersprache.kSprache
                ORDER BY thersteller.nSortNr, thersteller.cName',
            $sql->getParams()
        )->groupBy(['kHersteller'])->map(static function (Collection $data) use ($languageID): self {
            $manufacturer = new self(0, $languageID);
            $manufacturer->map($data->toArray());

            return $manufacturer;
        })->toArray();
    }

    /**
     * @param numeric-string[]|int[] $ids
     * @return self[]
     */
    public static function getByIds(array $ids): array
    {
        $items = Shop::Container()->getDB()->getCollection(
            "SELECT thersteller.kHersteller, thersteller.cName, thersteller.cHomepage, thersteller.nSortNr,
                thersteller.cBildpfad, therstellersprache.cMetaTitle, therstellersprache.cMetaKeywords,
                therstellersprache.cMetaDescription, therstellersprache.cBeschreibung,
                tseo.cSeo, thersteller.cSeo AS originalSeo, therstellersprache.kSprache
                FROM thersteller
                LEFT JOIN therstellersprache
                    ON therstellersprache.kHersteller = thersteller.kHersteller
                LEFT JOIN tseo
                    ON tseo.kKey = thersteller.kHersteller
                    AND tseo.cKey = 'kHersteller'
                    AND tseo.kSprache = therstellersprache.kSprache
                GROUP BY thersteller.kHersteller, therstellersprache.kSprache
                ORDER BY thersteller.nSortNr, thersteller.cName"
        );
        $items = $items->whereIn('kHersteller', $ids);
        $items = $items->groupBy('kHersteller');
        $items = $items->map(static function (Collection $data): self {
            $manufacturer = new self();
            $manufacturer->map($data->toArray());

            return $manufacturer;
        });

        return $items->toArray();
    }

    public function getID(): int
    {
        return $this->kHersteller;
    }

    public function setID(int $id): void
    {
        $this->kHersteller = $id;
    }

    public function getName(?int $idx = null): string
    {
        return $this->names[$idx ?? $this->currentLanguageID] ?? $this->names[$this->fallbackLanguageID] ?? '';
    }

    public function setName(string $name, ?int $idx = null): void
    {
        $this->names[$idx ?? $this->currentLanguageID] = $name;
    }

    public function getOriginalSeo(): string
    {
        return $this->originalSeo;
    }

    public function setOriginalSeo(string $originalSeo): void
    {
        $this->originalSeo = $originalSeo;
    }

    public function getMetaTitle(?int $idx = null): string
    {
        return $this->metaTitles[$idx ?? $this->currentLanguageID]
            ?? $this->metaTitles[$this->fallbackLanguageID]
            ?? '';
    }

    public function setMetaTitle(string $metaTitle, ?int $idx = null): void
    {
        $this->metaTitles[$idx ?? $this->currentLanguageID] = $metaTitle;
    }

    public function getMetaKeywords(?int $idx = null): string
    {
        return $this->metaKeywords[$idx ?? $this->currentLanguageID]
            ?? $this->metaKeywords[$this->fallbackLanguageID]
            ?? '';
    }

    public function setMetaKeywords(string $metaKeywords, ?int $idx = null): void
    {
        $this->metaKeywords[$idx ?? $this->currentLanguageID] = $metaKeywords;
    }

    public function getMetaDescription(?int $idx = null): string
    {
        return $this->metaDescriptions[$idx ?? $this->currentLanguageID]
            ?? $this->metaDescriptions[$this->fallbackLanguageID]
            ?? '';
    }

    public function setMetaDescription(string $metaDescription, ?int $idx = null): void
    {
        $this->metaDescriptions[$idx ?? $this->currentLanguageID] = $metaDescription;
    }

    public function getDescription(?int $idx = null): string
    {
        return $this->descriptions[$idx ?? $this->currentLanguageID]
            ?? $this->descriptions[$this->fallbackLanguageID]
            ?? '';
    }

    public function setDescription(string $description, ?int $idx = null): void
    {
        $this->descriptions[$idx ?? $this->currentLanguageID] = $description;
    }

    public function getImagePath(): string
    {
        return $this->imagePath;
    }

    public function setImagePath(string $imagePath): void
    {
        $this->imagePath = $imagePath;
    }

    public function getSortNo(): int
    {
        return $this->nSortNr;
    }

    public function setSortNo(int $sortNo): void
    {
        $this->nSortNr = $sortNo;
    }

    public function getImagePathSmall(): string
    {
        return $this->imagePathSmall;
    }

    public function setImagePathSmall(string $path): void
    {
        $this->imagePathSmall = $path;
    }

    public function getImagePathNormal(): string
    {
        return $this->imagePathNormal;
    }

    public function setImagePathNormal(string $path): void
    {
        $this->imagePathNormal = $path;
    }

    public function getImageURLSmall(): string
    {
        return $this->imageURLSmall;
    }

    public function setImageURLSmall(string $url): void
    {
        $this->imageURLSmall = $url;
    }

    public function getImageURLNormal(): string
    {
        return $this->imageURLNormal;
    }

    public function setImageURLNormal(string $url): void
    {
        $this->imageURLNormal = $url;
    }

    public function getHomepage(): string
    {
        return $this->homepage;
    }

    public function setHomepage(string $url): void
    {
        $this->homepage = $url;
    }

    public function getSeo(): string
    {
        return $this->getSlug();
    }
}
