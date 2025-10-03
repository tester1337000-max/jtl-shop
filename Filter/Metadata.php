<?php

declare(strict_types=1);

namespace JTL\Filter;

use Illuminate\Support\Collection;
use JTL\Catalog\Category\Kategorie;
use JTL\Catalog\Category\KategorieListe;
use JTL\Catalog\Category\MenuItem;
use JTL\Catalog\Hersteller;
use JTL\Catalog\Product\MerkmalWert;
use JTL\Helpers\Category;
use JTL\Helpers\Text;
use JTL\MagicCompatibilityTrait;
use JTL\Settings\Option\Overview;
use JTL\Settings\Settings;
use JTL\Shop;
use stdClass;

use function Functional\group;
use function Functional\map;
use function Functional\reduce_left;

/**
 * Class Metadata
 * @package JTL\Filter
 */
class Metadata implements MetadataInterface
{
    use MagicCompatibilityTrait;

    /**
     * @var array<mixed>
     */
    private array $conf;

    private string $breadCrumb = '';

    private string $metaTitle = '';

    private string $metaDescription = '';

    private string $metaKeywords = '';

    private ?Kategorie $category = null;

    private ?Hersteller $manufacturer = null;

    private ?MerkmalWert $characteristicValue = null;

    private string $name = '';

    private string $imageURL = \BILD_KEIN_KATEGORIEBILD_VORHANDEN;

    /**
     * @var array<string, string>
     */
    public static array $mapping = [
        'cMetaTitle'       => 'MetaTitle',
        'cMetaDescription' => 'MetaDescription',
        'cMetaKeywords'    => 'MetaKeywords',
        'cName'            => 'Name',
        'oHersteller'      => 'Manufacturer',
        'cBildURL'         => 'ImageURL',
        'oMerkmalWert'     => 'CharacteristicValue',
        'oKategorie'       => 'Category',
        'cBrotNavi'        => 'BreadCrumb'
    ];

    public function __construct(private readonly ProductFilter $productFilter)
    {
        $this->conf = $productFilter->getFilterConfig()->getConfig();
    }

    /**
     * @inheritdoc
     */
    public function getBreadCrumb(): string
    {
        return $this->breadCrumb;
    }

    /**
     * @inheritdoc
     */
    public function setBreadCrumb(string $breadCrumb): MetadataInterface
    {
        $this->breadCrumb = $breadCrumb;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getMetaTitle(): string
    {
        return $this->metaTitle;
    }

    /**
     * @inheritdoc
     */
    public function setMetaTitle(string $metaTitle): MetadataInterface
    {
        $this->metaTitle = $metaTitle;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getMetaDescription(): string
    {
        return $this->metaDescription;
    }

    /**
     * @inheritdoc
     */
    public function setMetaDescription(string $metaDescription): MetadataInterface
    {
        $this->metaDescription = $metaDescription;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getMetaKeywords(): string
    {
        return $this->metaKeywords;
    }

    /**
     * @inheritdoc
     */
    public function setMetaKeywords(string $metaKeywords): MetadataInterface
    {
        $this->metaKeywords = $metaKeywords;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getCategory(): ?Kategorie
    {
        return $this->category;
    }

    /**
     * @inheritdoc
     */
    public function setCategory(Kategorie $category): MetadataInterface
    {
        $this->category = $category;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getManufacturer(): ?Hersteller
    {
        return $this->manufacturer;
    }

    /**
     * @inheritdoc
     */
    public function setManufacturer(Hersteller $manufacturer): MetadataInterface
    {
        $this->manufacturer = $manufacturer;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getCharacteristicValue(): ?MerkmalWert
    {
        return $this->characteristicValue;
    }

    /**
     * @inheritdoc
     */
    public function setCharacteristicValue(MerkmalWert $value): MetadataInterface
    {
        $this->characteristicValue = $value;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function setName(string $name): MetadataInterface
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getImageURL(): string
    {
        return $this->imageURL ?? \BILD_KEIN_KATEGORIEBILD_VORHANDEN;
    }

    /**
     * @inheritdoc
     */
    public function setImageURL(?string $imageURL): MetadataInterface
    {
        $this->imageURL = $imageURL ?? \BILD_KEIN_KATEGORIEBILD_VORHANDEN;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function hasData(): bool
    {
        return !empty($this->imageURL) || !empty($this->name);
    }

    /**
     * @inheritdoc
     */
    public static function getGlobalMetaData(): array
    {
        return Shop::Container()->getCache()->get(
            'jtl_glob_meta',
            static function ($cache, $id, &$content, &$tags): bool {
                $globalTmp = Shop::Container()->getDB()->getObjects(
                    'SELECT cName, kSprache, cWertName 
                        FROM tglobalemetaangaben
                        ORDER BY kSprache'
                );
                $content   = map(
                    group($globalTmp, static fn(stdClass $g): int => (int)$g->kSprache),
                    static function ($item) {
                        return reduce_left(
                            $item,
                            static function ($value, $index, $collection, $reduction) {
                                $reduction->{$value->cName} = $value->cWertName;

                                return $reduction;
                            },
                            new stdClass()
                        );
                    }
                );
                $tags      = [\CACHING_GROUP_CORE];

                return true;
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function getNavigationInfo(?Kategorie $category = null, ?KategorieListe $list = null): MetadataInterface
    {
        $languageID = $this->productFilter->getFilterConfig()->getLanguageID();
        if ($category !== null && $this->productFilter->hasCategory()) {
            $this->category = $category;
            $this->setName($this->category->getName());
            $this->setImageURL($category->getImage());
        } elseif ($this->productFilter->hasManufacturer()) {
            $this->manufacturer = new Hersteller(
                (int)$this->productFilter->getManufacturer()->getValue(),
                $languageID
            );
            if ($this->manufacturer->getID() > 0) {
                $this->setName($this->manufacturer->getName())
                    ->setImageURL($this->manufacturer->getImage())
                    ->setMetaTitle($this->manufacturer->getMetaTitle($languageID))
                    ->setMetaDescription($this->manufacturer->getMetaDescription($languageID))
                    ->setMetaKeywords($this->manufacturer->getMetaKeywords($languageID));
            }
        } elseif ($this->productFilter->hasCharacteristicValue()) {
            $this->characteristicValue = new MerkmalWert(
                (int)$this->productFilter->getCharacteristicValue()->getValue(),
                $languageID
            );
            if ($this->characteristicValue->getID() > 0) {
                $this->setName($this->characteristicValue->getValue($languageID))
                    ->setImageURL($this->characteristicValue->getImage())
                    ->setMetaTitle($this->characteristicValue->getMetaTitle($languageID))
                    ->setMetaDescription($this->characteristicValue->getMetaDescription($languageID))
                    ->setMetaKeywords($this->characteristicValue->getMetaKeywords($languageID));
            }
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function generateMetaDescription(
        array $products,
        SearchResultsInterface $searchResults,
        array $globalMeta,
        ?Kategorie $category = null
    ): string {
        \executeHook(\HOOK_FILTER_INC_GIBNAVIMETADESCRIPTION);
        $maxLength = !empty($this->conf['metaangaben']['global_meta_maxlaenge_description'])
            ? (int)$this->conf['metaangaben']['global_meta_maxlaenge_description']
            : 0;
        if (!empty($this->metaDescription)) {
            return self::prepareMeta(
                \strip_tags($this->metaDescription),
                null,
                $maxLength
            );
        }
        // Kategorieattribut?
        $catDescription = '';
        $languageID     = $this->productFilter->getFilterConfig()->getLanguageID();
        if ($this->productFilter->hasCategory()) {
            $category = $category ?? new Kategorie((int)$this->productFilter->getCategory()->getValue(), $languageID);
            if (!empty($category->getMetaDescription())) {
                // meta description via new method
                return self::prepareMeta(
                    \strip_tags($category->getMetaDescription()),
                    null,
                    $maxLength
                );
            }
            if (!empty($category->getCategoryAttributeValue('meta_description'))) {
                // Hat die aktuelle Kategorie als Kategorieattribut eine Meta Description gesetzt?
                return self::prepareMeta(
                    \strip_tags($category->getCategoryAttributeValue('meta_description')),
                    null,
                    $maxLength
                );
            }
            // Hat die aktuelle Kategorie eine Beschreibung?
            if (!empty($category->getDescription())) {
                $catDescription = \strip_tags(
                    \str_replace(
                        ['<br>', '<br />'],
                        [' ', ' '],
                        $category->getDescription()
                    )
                );
            } elseif ($category->hasSubcategories()) {
                // Hat die aktuelle Kategorie Unterkategorien?
                $helper = Category::getInstance($languageID);
                $sub    = $helper->getCategoryById($category->getID(), $category->getLeft(), $category->getRight());
                if ($sub !== null && $sub->hasChildren()) {
                    $catNames       = map($sub->getChildren(), fn(MenuItem $e): string => \strip_tags($e->getName()));
                    $catDescription = \implode(', ', \array_filter($catNames));
                }
            }

            if (\mb_strlen($catDescription) > 1) {
                $catDescription  = \str_replace('"', '', $catDescription);
                $catDescription  = Text::htmlentitydecode($catDescription, \ENT_NOQUOTES);
                $metaDescription = !empty($globalMeta[$languageID]->Meta_Description_Praefix)
                    ? \trim(
                        \strip_tags($globalMeta[$languageID]->Meta_Description_Praefix) .
                        ' ' .
                        $catDescription
                    )
                    : \trim($catDescription);
                // Seitenzahl anhaengen ab Seite 2 (Doppelte Meta-Descriptions vermeiden, #5992)
                if (
                    $searchResults->getOffsetStart() > 0
                    && $searchResults->getOffsetEnd() > 0
                    && $searchResults->getPages()->getCurrentPage() > 1
                ) {
                    $metaDescription .= ', ' . Shop::Lang()->get('products') . ' ' .
                        $searchResults->getOffsetStart() . ' - ' . $searchResults->getOffsetEnd();
                }

                return self::prepareMeta($metaDescription, null, $maxLength);
            }
        }
        // Keine eingestellten Metas vorhanden => generiere Standard Metas
        $metaDescription = '';
        if (\count($products) > 0) {
            $maxIdx      = \min(12, \count($products));
            $productName = '';
            for ($i = 0; $i < $maxIdx; ++$i) {
                $productName .= $i > 0
                    ? ' - ' . $products[$i]->cName
                    : $products[$i]->cName;
            }
            $productName = \str_replace('"', '', $productName);
            $productName = Text::htmlentitydecode($productName, \ENT_NOQUOTES);

            $metaDescription = !empty($globalMeta[$languageID]->Meta_Description_Praefix)
                ? $this->getMetaStart($searchResults) .
                ': ' .
                $globalMeta[$languageID]->Meta_Description_Praefix .
                ' ' . $productName
                : $this->getMetaStart($searchResults) . ': ' . $productName;
            // Seitenzahl anhaengen ab Seite 2 (Doppelte Meta-Descriptions vermeiden, #5992)
            if (
                $searchResults->getOffsetStart() > 0
                && $searchResults->getOffsetEnd() > 0
                && $searchResults->getPages()->getCurrentPage() > 1
            ) {
                $metaDescription .= ', ' . Shop::Lang()->get('products') . ' ' .
                    $searchResults->getOffsetStart() . ' - ' . $searchResults->getOffsetEnd();
            }
        }

        return self::prepareMeta(\strip_tags($metaDescription), null, $maxLength);
    }

    /**
     * @inheritdoc
     */
    public function generateMetaKeywords(array $products, ?Kategorie $category = null): string
    {
        \executeHook(\HOOK_FILTER_INC_GIBNAVIMETAKEYWORDS);
        if (!empty($this->metaKeywords)) {
            return \strip_tags($this->metaKeywords);
        }
        // Kategorieattribut?
        if ($this->productFilter->hasCategory()) {
            $category = $category ?? new Kategorie(
                (int)$this->productFilter->getCategory()->getValue(),
                $this->productFilter->getFilterConfig()->getLanguageID()
            );
            if (!empty($category->getMetaKeywords())) {
                // meta keywords via new method
                return \strip_tags($category->getMetaKeywords());
            }
            if (!empty($category->getCategoryAttributeValue('meta_keywords'))) {
                // Hat die aktuelle Kategorie als Kategorieattribut einen Meta Keywords gesetzt?
                return \strip_tags($category->getCategoryAttributeValue('meta_keywords'));
            }
        }

        return '';
    }

    /**
     * @inheritdoc
     */
    public function generateMetaTitle(
        SearchResultsInterface $searchResults,
        array $globalMeta,
        ?Kategorie $category = null
    ): string {
        \executeHook(\HOOK_FILTER_INC_GIBNAVIMETATITLE);
        $languageID = $this->productFilter->getFilterConfig()->getLanguageID();
        $append     = $this->conf['metaangaben']['global_meta_title_anhaengen'] === 'Y';
        if (!empty($this->metaTitle)) {
            $metaTitle = \strip_tags($this->metaTitle);
            if ($append === true && !empty($globalMeta[$languageID]->Title)) {
                return $this->truncateMetaTitle(
                    $metaTitle . ' ' .
                    $globalMeta[$languageID]->Title
                );
            }

            return $this->truncateMetaTitle($metaTitle);
        }
        // Set Default Titles
        $metaTitle = $this->getMetaStart($searchResults);
        $metaTitle = \str_replace('"', "'", $metaTitle);
        $metaTitle = Text::htmlentitydecode($metaTitle, \ENT_NOQUOTES);
        if ($this->productFilter->hasCategory()) {
            $category = $category ?? new Kategorie((int)$this->productFilter->getCategory()->getValue(), $languageID);
            if (!empty($category->getMetaTitle())) {
                // meta title via new method
                $metaTitle = \strip_tags($category->getMetaTitle());
                $metaTitle = \str_replace('"', "'", $metaTitle);
                $metaTitle = Text::htmlentitydecode($metaTitle, \ENT_NOQUOTES);
            } elseif (!empty($category->getCategoryAttributeValue('meta_title'))) {
                // Hat die aktuelle Kategorie als Kategorieattribut einen Meta Title gesetzt?
                $metaTitle = \strip_tags($category->getCategoryAttributeValue('meta_title'));
                $metaTitle = \str_replace('"', "'", $metaTitle);
                $metaTitle = Text::htmlentitydecode($metaTitle, \ENT_NOQUOTES);
            }
        }
        // Seitenzahl anhaengen ab Seite 2 (Doppelte Titles vermeiden, #5992)
        if ($searchResults->getPages()->getCurrentPage() > 1) {
            $metaTitle .= ', ' . Shop::Lang()->get('page') . ' ' .
                $searchResults->getPages()->getCurrentPage();
        }
        if ($append === true && !empty($globalMeta[$languageID]->Title)) {
            $metaTitle .= ' - ' . $globalMeta[$languageID]->Title;
        }
        $metaTitle = \str_replace(['<', '>'], ['&lt;', '&gt;'], $metaTitle);

        return $this->truncateMetaTitle($metaTitle);
    }

    /**
     * @inheritdoc
     */
    public function getMetaStart(SearchResultsInterface $searchResults): string
    {
        $parts = new Collection();
        if ($this->productFilter->hasCharacteristicValue()) {
            $parts->push($this->productFilter->getCharacteristicValue()->getName());
        } elseif ($this->productFilter->hasCategory()) {
            $parts->push($this->productFilter->getCategory()->getName());
        } elseif ($this->productFilter->hasManufacturer()) {
            $parts->push($this->productFilter->getManufacturer()->getName());
        } elseif ($this->productFilter->hasSearch()) {
            $parts->push($this->productFilter->getSearch()->getName());
        } elseif ($this->productFilter->hasSearchQuery()) {
            $parts->push($this->productFilter->getSearchQuery()->getName());
        } elseif ($this->productFilter->hasSearchSpecial()) {
            $parts->push($this->productFilter->getSearchSpecial()->getName());
        }
        if ($this->productFilter->hasCategoryFilter()) {
            $parts->push($this->productFilter->getCategoryFilter()->getName());
        }
        if ($this->productFilter->hasManufacturerFilter()) {
            $parts->push($this->productFilter->getManufacturerFilter()->getName());
        }
        $parts = $parts->merge(
            \collect($this->productFilter->getSearchFilter())
                ->map(fn(FilterInterface $filter): ?string => $filter->getName())
                ->reject(fn($name): bool => $name === null)
        );
        if ($this->productFilter->hasSearchSpecialFilter()) {
            switch ($this->productFilter->getSearchSpecialFilter()->getValue()) {
                case \SEARCHSPECIALS_BESTSELLER:
                    $parts->push(Shop::Lang()->get('bestsellers'));
                    break;

                case \SEARCHSPECIALS_SPECIALOFFERS:
                    $parts->push(Shop::Lang()->get('specialOffers'));
                    break;

                case \SEARCHSPECIALS_NEWPRODUCTS:
                    $parts->push(Shop::Lang()->get('newProducts'));
                    break;

                case \SEARCHSPECIALS_TOPOFFERS:
                    $parts->push(Shop::Lang()->get('topOffers'));
                    break;

                case \SEARCHSPECIALS_UPCOMINGPRODUCTS:
                    $parts->push(Shop::Lang()->get('upcomingProducts'));
                    break;

                case \SEARCHSPECIALS_TOPREVIEWS:
                    $parts->push(Shop::Lang()->get('topReviews'));
                    break;

                default:
                    break;
            }
        }
        // MerkmalWertfilter
        $parts = $parts->merge(
            \collect($this->productFilter->getCharacteristicFilter())
                ->map(fn(FilterInterface $filter): ?string => $filter->getName())
                ->reject(fn($name): bool => $name === null)
        );

        return $parts->implode(' ');
    }

    /**
     * @inheritdoc
     */
    public function truncateMetaTitle(string $title): string
    {
        return ($length = (int)$this->conf['metaangaben']['global_meta_maxlaenge_title']) > 0
            ? \mb_substr($title, 0, $length)
            : $title;
    }

    /**
     * @inheritdoc
     */
    public function getHeader(): string
    {
        if ($this->productFilter->getBaseState()->isNotFound()) {
            return '';
        }
        if ($this->productFilter->hasCategory()) {
            $this->breadCrumb = $this->productFilter->getCategory()->getName() ?? '';

            return $this->breadCrumb;
        }
        if ($this->productFilter->hasManufacturer()) {
            $this->breadCrumb = $this->productFilter->getManufacturer()->getName() ?? '';

            return $this->breadCrumb === ''
                ? ''
                : Shop::Lang()->get('productsFrom') . ' ' . $this->breadCrumb;
        }
        if ($this->productFilter->hasCharacteristicValue()) {
            $this->breadCrumb = $this->productFilter->getCharacteristicValue()->getName() ?? '';

            return $this->breadCrumb === ''
                ? ''
                : Shop::Lang()->get('productsWith') . ' ' . $this->breadCrumb;
        }
        if ($this->productFilter->hasSearchSpecial()) {
            $this->breadCrumb = $this->productFilter->getSearchSpecial()->getName() ?? '';

            return $this->breadCrumb;
        }
        if ($this->productFilter->hasSearch()) {
            $this->breadCrumb = $this->productFilter->getSearch()->getName() ?? '';
        } elseif ($this->productFilter->getSearchQuery()->isInitialized()) {
            $this->breadCrumb = $this->productFilter->getSearchQuery()->getName() ?? '';
        }
        if (
            !empty($this->productFilter->getSearch()->getName())
            || !empty($this->productFilter->getSearchQuery()->getName())
        ) {
            return Shop::Lang()->get('for') . ' ' . $this->breadCrumb;
        }

        return '';
    }

    /**
     * @inheritdoc
     */
    public function getExtendedView(int $viewType = 0): stdClass
    {
        $extendedView = $_SESSION['oErweiterteDarstellung'] ?? null;
        if ($extendedView === null) {
            $extendedView                 = new stdClass();
            $extendedView->cURL_arr       = [];
            $extendedView->nAnzahlArtikel = \ERWDARSTELLUNG_ANSICHT_ANZAHL_STD;
        }
        if (!isset($_SESSION['oErweiterteDarstellung']) || $viewType > 0) {
            if ($this->productFilter->hasCategory()) {
                $category = new Kategorie(
                    (int)$this->productFilter->getCategory()->getValue(),
                    $this->productFilter->getFilterConfig()->getLanguageID()
                );
                if (!empty($category->getCategoryFunctionAttribute(\KAT_ATTRIBUT_DARSTELLUNG))) {
                    $viewType = (int)$category->getCategoryFunctionAttribute(\KAT_ATTRIBUT_DARSTELLUNG);
                }
            }
            $extendedView->nDarstellung = Settings::intValue(Overview::EXTENDED_VIEW_DEFAULT)
                ?: \ERWDARSTELLUNG_ANSICHT_GALERIE;
            if ($viewType === \ERWDARSTELLUNG_ANSICHT_LISTE || $viewType === \ERWDARSTELLUNG_ANSICHT_GALERIE) {
                $extendedView->nDarstellung = $viewType;
            }
            if (isset($_SESSION['ArtikelProSeite'])) {
                $extendedView->nAnzahlArtikel = (int)$_SESSION['ArtikelProSeite'];
            } elseif (Settings::intValue(Overview::PRODUCTS_PER_PAGE) > 0) {
                $extendedView->nAnzahlArtikel = Settings::intValue(Overview::PRODUCTS_PER_PAGE);
            }
            $_SESSION['oErweiterteDarstellung'] = $extendedView;
        }
        $extendedView = $_SESSION['oErweiterteDarstellung'];
        $naviURL      = $this->productFilter->getFilterURL()->getURL();
        $naviURL      .= !\str_contains($naviURL, '?') ? '?ed=' : '&amp;ed=';

        $extendedView->cURL_arr[\ERWDARSTELLUNG_ANSICHT_LISTE]   = $naviURL . \ERWDARSTELLUNG_ANSICHT_LISTE;
        $extendedView->cURL_arr[\ERWDARSTELLUNG_ANSICHT_GALERIE] = $naviURL . \ERWDARSTELLUNG_ANSICHT_GALERIE;

        return $extendedView;
    }

    /**
     * @inheritdoc
     */
    public function checkNoIndex(): bool
    {
        if (!isset($_SERVER['SCRIPT_NAME'])) {
            return false;
        }
        $noIndex = match (\basename($_SERVER['SCRIPT_NAME'])) {
            'wartung.php', 'navi.php', 'bestellabschluss.php', 'bestellvorgang.php',
            'jtl.php', 'pass.php', 'registrieren.php', 'warenkorb.php', 'wunschliste.php' => true,
            default                                                                       => false,
        };
        if ($this->productFilter->hasSearch()) {
            $noIndex = true;
        }
        if (!$noIndex) {
            $noIndex = $this->productFilter->getFilterCount() > 1
                || ($this->conf['global']['global_merkmalwert_url_indexierung'] === 'N'
                    && $this->productFilter->hasCharacteristicValue()
                    && $this->productFilter->getCharacteristicValue()->getValue() > 0);
        }

        return $noIndex;
    }

    public static function truncateMetaDescription(string $description): string
    {
        $maxLength = (int)Shop::getSettingValue(\CONF_METAANGABEN, 'global_meta_maxlaenge_description');

        return self::prepareMeta($description, null, $maxLength);
    }

    /**
     * @param string      $metaProposal the proposed meta text value.
     * @param string|null $metaSuffix appended to already shortened $metaProposal
     * @param int         $maxLength $metaProposal will be truncated to $maxlength
     * @return string truncated meta value with optional suffix (always appended if set)
     */
    public static function prepareMeta(string $metaProposal, ?string $metaSuffix = null, int $maxLength = 0): string
    {
        $metaStr = \trim(\preg_replace('/\s\s+/', ' ', Text::htmlentitiesOnce($metaProposal)));

        return Text::htmlentitiesSubstr($metaStr, $maxLength) . ($metaSuffix ?? '');
    }

    public function __isset(string $name): bool
    {
        if (\property_exists($this, $name)) {
            return true;
        }
        $mapped = self::getMapping($name);
        if (!\is_string($mapped)) {
            return false;
        }
        $method = 'get' . $mapped;
        $result = $this->$method();

        return $result !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $res                  = \get_object_vars($this);
        $res['conf']          = '*truncated*';
        $res['productFilter'] = '*truncated*';

        return $res;
    }
}
