<?php

declare(strict_types=1);

namespace JTL\Catalog\Category;

use JTL\MagicCompatibilityTrait;
use JTL\Media\Image;
use JTL\Media\MultiSizeImage;
use JTL\Shop;
use stdClass;

/**
 * Class MenuItem
 * @package JTL\Catalog\Category
 */
class MenuItem
{
    use MagicCompatibilityTrait;
    use MultiSizeImage;

    /**
     * @var array<string, string>
     */
    public static array $mapping = [
        'kKategorie'                 => 'ID',
        'kOberKategorie'             => 'ParentID',
        'cBeschreibung'              => 'Description',
        'cURL'                       => 'URL',
        'cURLFull'                   => 'URL',
        'cBildURL'                   => 'ImageURL',
        'cBildURLFull'               => 'ImageURL',
        'cName'                      => 'Name',
        'cKurzbezeichnung'           => 'ShortName',
        'cnt'                        => 'ProductCount',
        'categoryAttributes'         => 'Attributes',
        'categoryFunctionAttributes' => 'FunctionalAttributes',
        'bUnterKategorien'           => 'HasChildrenCompat',
        'Unterkategorien'            => 'Children',
        'cSeo'                       => 'URL',
    ];

    private int $id = 0;

    private int $parentID = 0;

    private int $languageID = 0;

    private string $name = '';

    private string $shortName = '';

    private ?string $description = '';

    private string $url = '';

    private string $seo = '';

    private string $imageURL = '';

    /**
     * @var array<string, stdClass>
     */
    private array $attributes = [];

    /**
     * @var array<string, string>
     */
    private array $functionalAttributes = [];

    /**
     * @var array<int, MenuItem[]>
     */
    private array $children = [];

    private bool $hasChildren = false;

    private int $productCount = -1;

    public ?string $customImgName = null;

    public bool $orphaned = false;

    private int $lft = 0;

    private int $rght = 0;

    private int $lvl = 0;

    public function __construct(stdClass $data)
    {
        $this->setLanguageID((int)($data->languageID ?? 0));
        $this->setLeft((int)$data->lft);
        $this->setRight((int)$data->rght);
        $this->setLevel((int)$data->nLevel);
        $this->setImageType(Image::TYPE_CATEGORY);
        $this->setID((int)$data->kKategorie);
        $this->setParentID((int)$data->kOberKategorie);
        if (empty($data->cName_spr)) {
            $this->setName($data->cName);
        } else {
            $this->setName($data->cName_spr);
        }
        if (empty($data->cBeschreibung_spr)) {
            $this->setDescription($data->cBeschreibung);
        } else {
            $this->setDescription($data->cBeschreibung_spr);
        }
        if (isset($data->customImgName)) {
            $this->customImgName = $data->customImgName;
        }
        $this->setURL($data->cURL ?? $data->cSeo ?? '');
        $this->setSeo($data->cSeo ?? '');
        $this->setImageURL($data->cPfad ?? '');
        $this->generateAllImageSizes(true, 1, $data->cPfad ?? null);
        $this->generateAllImageDimensions(1, $data->cPfad ?? null);
        $this->setProductCount((int)($data->cnt ?? 0));
        $this->setFunctionalAttributes($data->functionAttributes[$this->getID()] ?? []);
        $this->setAttributes($data->localizedAttributes[$this->getID()] ?? []);
        $this->setShortName($this->getAttribute(\ART_ATTRIBUT_SHORTNAME)->cWert ?? $this->getName());
    }

    public function getID(): int
    {
        return $this->id;
    }

    public function setID(int $id): void
    {
        $this->id = $id;
    }

    public function getParentID(): int
    {
        return $this->parentID;
    }

    public function setParentID(int $parentID): void
    {
        $this->parentID = $parentID;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getShortName(): string
    {
        return $this->shortName;
    }

    public function setShortName(string $shortName): void
    {
        $this->shortName = $shortName;
    }

    public function getDescription(): string
    {
        return $this->description ?? '';
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getURL(): string
    {
        return $this->url;
    }

    public function setURL(string $url): void
    {
        $this->url = $url;
    }

    public function getSeo(): string
    {
        return $this->seo;
    }

    public function setSeo(string $seo): void
    {
        $this->seo = $seo;
    }

    public function getImageURL(): string
    {
        return $this->imageURL;
    }

    public function setImageURL(?string $imageURL): void
    {
        $this->imageURL = Shop::getImageBaseURL();
        $this->imageURL .= empty($imageURL)
            ? \BILD_KEIN_KATEGORIEBILD_VORHANDEN
            : \PFAD_KATEGORIEBILDER . $imageURL;
    }

    /**
     * @return stdClass[]
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $name): ?stdClass
    {
        return $this->attributes[$name] ?? null;
    }

    /**
     * @param stdClass[] $attributes
     */
    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
    }

    /**
     * @return array<string, string>
     */
    public function getFunctionalAttributes(): array
    {
        return $this->functionalAttributes;
    }

    public function getFunctionalAttribute(string $name): ?string
    {
        return $this->functionalAttributes[$name] ?? null;
    }

    /**
     * @param array<string, string> $functionalAttributes
     */
    public function setFunctionalAttributes(array $functionalAttributes): void
    {
        $this->functionalAttributes = $functionalAttributes;
    }

    /**
     * @return array<int, MenuItem[]>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * @param array<int, MenuItem[]> $children
     */
    public function setChildren(array $children): void
    {
        $this->children = $children;
    }

    public function hasChildren(): bool
    {
        return $this->hasChildren;
    }

    public function getHasChildrenCompat(): int
    {
        return (int)$this->hasChildren;
    }

    public function setHasChildrenCompat(int $has): void
    {
        $this->hasChildren = (bool)$has;
    }

    public function getHasChildren(): bool
    {
        return $this->hasChildren;
    }

    public function setHasChildren(bool $hasChildren): void
    {
        $this->hasChildren = $hasChildren;
    }

    public function getProductCount(): int
    {
        return $this->productCount;
    }

    public function setProductCount(int $productCount): void
    {
        $this->productCount = $productCount;
    }

    public function isOrphaned(): bool
    {
        return $this->orphaned;
    }

    public function setOrphaned(bool $orphaned): void
    {
        $this->orphaned = $orphaned;
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

    public function getLevel(): int
    {
        return $this->lvl;
    }

    public function setLevel(int $lvl): void
    {
        $this->lvl = $lvl;
    }

    public function getLanguageID(): int
    {
        return $this->languageID;
    }

    public function setLanguageID(int $languageID): void
    {
        $this->languageID = $languageID;
    }
}
