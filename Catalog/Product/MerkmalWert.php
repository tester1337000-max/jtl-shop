<?php

declare(strict_types=1);

namespace JTL\Catalog\Product;

use JTL\Contracts\RoutableInterface;
use JTL\DB\DbInterface;
use JTL\Language\LanguageHelper;
use JTL\MagicCompatibilityTrait;
use JTL\Media\Image;
use JTL\Media\MultiSizeImage;
use JTL\Router\RoutableTrait;
use JTL\Router\Router;
use JTL\Shop;

use function Functional\select;

/**
 * Class MerkmalWert
 * @package JTL\Catalog\Product
 */
class MerkmalWert implements RoutableInterface
{
    use MultiSizeImage;
    use MagicCompatibilityTrait;
    use RoutableTrait;

    private int $id = 0;

    public int $characteristicID = 0;

    private int $sort = 0;

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

    /**
     * @var string[]
     */
    private array $characteristicNames = [];

    /**
     * @var string[]
     */
    private array $values = [];

    private DbInterface $db;

    /**
     * @var array<string, string>
     */
    protected static array $mapping = [
        'cURL'             => 'URL',
        'cURLFull'         => 'URL',
        'nSort'            => 'Sort',
        'cBeschreibung'    => 'Description',
        'kSprache'         => 'LanguageID',
        'kMerkmal'         => 'CharacteristicID',
        'kMerkmalWert'     => 'ID',
        'cMetaTitle'       => 'MetaTitle',
        'cMetaKeywords'    => 'MetaKeywords',
        'cMetaDescription' => 'MetaDescription',
        'cSeo'             => 'Slug',
        'cWert'            => 'Value'
    ];

    public function __construct(int $id = 0, int $languageID = 0, ?DbInterface $db = null)
    {
        $this->db = $db ?? Shop::Container()->getDB();
        $this->setImageType(Image::TYPE_CHARACTERISTIC_VALUE);
        $this->setRouteType(Router::TYPE_CHARACTERISTIC_VALUE);
        if ($id > 0) {
            $this->loadFromDB($id, $languageID);
        }
    }

    public function __wakeup(): void
    {
        $this->initLanguageID();
    }

    /**
     * @return string[]
     */
    public function __sleep(): array
    {
        return select(\array_keys(\get_object_vars($this)), fn(string $e): bool => $e !== 'db');
    }

    public function loadFromDB(int $id, int $languageID = 0): self
    {
        $languageID = $languageID ?: Shop::getLanguageID();
        $cacheID    = 'mmw_' . $id;
        $this->initLanguageID($languageID);
        if (Shop::has($cacheID)) {
            foreach (\get_object_vars(Shop::get($cacheID)) as $k => $v) {
                $this->$k = $v;
            }
            $this->setCurrentLanguageID($languageID);

            return $this;
        }
        $defaultLanguageID = LanguageHelper::getDefaultLanguage()->getId();
        $data              = $this->db->getObjects(
            'SELECT tmerkmalwert.*, COALESCE(loc.kSprache, def.kSprache) AS kSprache, 
                    COALESCE(loc.cWert, def.cWert) AS cWert,
                    COALESCE(loc.cMetaTitle, def.cMetaTitle) AS cMetaTitle, 
                    COALESCE(loc.cMetaKeywords, def.cMetaKeywords) AS cMetaKeywords,
                    COALESCE(loc.cMetaDescription, def.cMetaDescription) AS cMetaDescription, 
                    COALESCE(loc.cBeschreibung, def.cBeschreibung) AS cBeschreibung,
                    COALESCE(loc.cSeo, def.cSeo) AS cSeo,
                    COALESCE(tmerkmalsprache.cName, tmerkmal.cName) AS cName
                FROM tmerkmalwert 
                INNER JOIN tmerkmalwertsprache AS def 
                    ON def.kMerkmalWert = tmerkmalwert.kMerkmalWert
                    AND def.kSprache = :lid
                JOIN tmerkmal
                    ON tmerkmal.kMerkmal = tmerkmalwert.kMerkmal
                LEFT JOIN tmerkmalwertsprache AS loc 
                    ON loc.kMerkmalWert = tmerkmalwert.kMerkmalWert
                LEFT JOIN tmerkmalsprache
                    ON tmerkmalsprache.kMerkmal = tmerkmalwert.kMerkmal
                    AND tmerkmalsprache.kSprache = loc.kSprache
                WHERE tmerkmalwert.kMerkmalWert = :mid',
            ['mid' => $id, 'lid' => $defaultLanguageID]
        );
        $this->map($data);
        $this->createBySlug($id);
        $this->setCurrentLanguageID($languageID);

        return $this;
    }

    /**
     * @param \stdClass[] $data
     */
    private function map(array $data): void
    {
        $imagePath = null;
        foreach ($data as $item) {
            $languageID = (int)$item->kSprache;
            $imagePath  = $item->cBildpfad;
            $this->setLanguageID($languageID);
            $this->setID((int)$item->kMerkmalWert);
            $this->setSort((int)$item->nSort);
            $this->setCharacteristicID((int)$item->kMerkmal);
            $this->setValue($item->cWert, $languageID);
            $this->setMetaTitle($item->cMetaTitle, $languageID);
            $this->setMetaDescription($item->cMetaDescription, $languageID);
            $this->setMetaKeywords($item->cMetaKeywords, $languageID);
            $this->setDescription($item->cBeschreibung, $languageID);
            $this->setSlug($item->cSeo, $languageID);
            $this->setCharacteristicName($item->cName, $languageID);
            \executeHook(\HOOK_MERKMALWERT_CLASS_LOADFROMDB, ['oMerkmalWert' => &$this]);
        }
        if (empty($imagePath)) {
            return;
        }
        $this->setImagePath($imagePath);
        $this->generateAllImageSizes(true, 1, $imagePath);
        $this->generateAllImageDimensions(1, $imagePath);
    }

    public function getID(): int
    {
        return $this->id;
    }

    public function setID(int $id): void
    {
        $this->id = $id;
    }

    public function getValue(?int $idx = null): ?string
    {
        return $this->values[$idx ?? $this->currentLanguageID] ?? $this->values[$this->fallbackLanguageID] ?? null;
    }

    public function setValue(?string $value, ?int $idx = null): void
    {
        $this->values[$idx ?? $this->currentLanguageID] = $value;
    }

    public function getLanguageID(): int
    {
        return $this->currentLanguageID;
    }

    public function setLanguageID(int $languageID): void
    {
        $this->currentLanguageID = $languageID;
    }

    public function getCharacteristicID(): int
    {
        return $this->characteristicID;
    }

    public function setCharacteristicID(int $id): void
    {
        $this->characteristicID = $id;
    }

    public function getSort(): int
    {
        return $this->sort;
    }

    public function setSort(int $sort): void
    {
        $this->sort = $sort;
    }

    public function getMetaKeywords(?int $idx = null): ?string
    {
        return $this->metaKeywords[$idx ?? $this->currentLanguageID]
            ?? $this->metaKeywords[$this->fallbackLanguageID]
            ?? null;
    }

    public function setMetaKeywords(?string $metaKeywords, ?int $idx = null): void
    {
        $this->metaKeywords[$idx ?? $this->currentLanguageID] = $metaKeywords ?? '';
    }

    public function getMetaDescription(?int $idx = null): ?string
    {
        return $this->metaDescriptions[$idx ?? $this->currentLanguageID]
            ?? $this->metaDescriptions[$this->fallbackLanguageID]
            ?? null;
    }

    public function setMetaDescription(?string $metaDescription, ?int $idx = null): void
    {
        $this->metaDescriptions[$idx ?? $this->currentLanguageID] = $metaDescription ?? '';
    }

    public function getMetaTitle(?int $idx = null): ?string
    {
        return $this->metaTitles[$idx ?? $this->currentLanguageID]
            ?? $this->metaTitles[$this->fallbackLanguageID]
            ?? null;
    }

    public function setMetaTitle(?string $metaTitle, ?int $idx = null): void
    {
        $this->metaTitles[$idx ?? $this->currentLanguageID] = $metaTitle ?? '';
    }

    public function getDescription(?int $idx = null): ?string
    {
        return $this->descriptions[$idx ?? $this->currentLanguageID]
            ?? $this->descriptions[$this->fallbackLanguageID]
            ?? null;
    }

    public function setDescription(?string $description, ?int $idx = null): void
    {
        $this->descriptions[$idx ?? $this->currentLanguageID] = $description ?? '';
    }

    public function getCharacteristicName(?int $idx = null): string
    {
        return $this->characteristicNames[$idx ?? $this->currentLanguageID]
            ?? $this->characteristicNames[$this->fallbackLanguageID]
            ?? '';
    }

    public function setCharacteristicName(string $characteristicName, ?int $idx = null): void
    {
        $this->characteristicNames[$idx ?? $this->currentLanguageID] = $characteristicName;
    }

    public function getSeo(?int $idx = null): ?string
    {
        return $this->getSlug($idx);
    }

    public function setSeo(string $seo, ?int $idx = null): void
    {
        $this->setSlug($seo, $idx);
    }

    public function getImagePath(): string
    {
        return $this->imagePath;
    }

    public function setImagePath(string $path): void
    {
        $this->imagePath = $path;
    }
}
