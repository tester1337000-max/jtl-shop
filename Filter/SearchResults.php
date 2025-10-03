<?php

declare(strict_types=1);

namespace JTL\Filter;

use Illuminate\Support\Collection;
use JTL\Boxes\Items\AbstractBox;
use JTL\Catalog\Category\Kategorie;
use JTL\Catalog\Product\Artikel;
use JTL\Filter\Pagination\Info;
use JTL\Helpers\Text;
use JTL\MagicCompatibilityTrait;
use stdClass;

use function Functional\every;
use function Functional\filter;
use function Functional\invoke;
use function Functional\map;

/**
 * Class SearchResults
 * @package JTL\Filter
 */
class SearchResults implements SearchResultsInterface
{
    use MagicCompatibilityTrait;

    /**
     * @var Collection<int, Artikel>
     * @former Artikel
     */
    private Collection $products;

    /**
     * @former GesamtanzahlArtikel
     */
    private int $productCount = 0;

    private int $visibileProductCount = 0;

    /**
     * former ArtikelVon
     */
    private int $offsetStart = 0;

    /**
     * @former ArtikelBis
     */
    private int $offsetEnd = 0;

    /**
     * @former Seitenzahlen
     */
    private Info $pages;

    /**
     * @former cSuche
     */
    private ?string $searchTerm = null;

    /**
     * @former SuchausdruckWrite
     */
    private ?string $searchTermWrite = null;

    /**
     * @former SucheErfolglos
     */
    private bool $searchUnsuccessful = false;

    /**
     * @var Option[]
     * @former Herstellerauswahl
     */
    private array $manufacturerFilterOptions = [];

    /**
     * @var Option[]
     * @former Bewertung
     */
    private array $ratingFilterOptions = [];

    /**
     * @var Option[]
     * @former MerkmalFilter
     */
    private array $characteristicFilterOptions = [];

    /**
     * @var Option[]
     * @former Preisspanne
     */
    private array $priceRangeFilterOptions = [];

    /**
     * @var Option[]
     * @former Kategorieauswahl
     */
    private array $categoryFilterOptions = [];

    /**
     * @var Option[]
     * @former SuchFilter
     */
    private array $searchFilterOptions = [];

    /**
     * @var Option[]
     * @former Suchspecialauswahl
     */
    private array $searchSpecialFilterOptions = [];

    /**
     * @var Option[]
     */
    private array $availabilityFilterOptions = [];

    /**
     * @var Option[]
     */
    private array $customFilterOptions = [];

    /**
     * @var Collection<int, int>
     */
    private Collection $productKeys;

    /**
     * @former cFehler
     */
    private ?string $error = null;

    public ?string $searchFilterJSON = null;

    /**
     * @var Option[]
     */
    private array $sortingOptions = [];

    /**
     * @var Option[]
     */
    private array $limitOptions = [];

    /**
     * @var array<string, string>
     */
    public static array $mapping = [
        'Artikel'             => 'ProductsCompat',
        'GesamtanzahlArtikel' => 'ProductCount',
        'ArtikelBis'          => 'OffsetEnd',
        'ArtikelVon'          => 'OffsetStart',
        'Seitenzahlen'        => 'Pages',
        'SuchausdruckWrite'   => 'SearchTermWrite',
        'cSuche'              => 'SearchTerm',
        'cFehler'             => 'Error',
        'SucheErfolglos'      => 'SearchUnsuccessful',
        'Herstellerauswahl'   => 'ManufacturerFilterOptions',
        'Bewertung'           => 'RatingFilterOptions',
        'MerkmalFilter'       => 'CharacteristicFilterOptions',
        'Preisspanne'         => 'PriceRangeFilterOptions',
        'Kategorieauswahl'    => 'CategoryFilterOptions',
        'SuchFilter'          => 'SearchFilterOptions',
        'Suchspecialauswahl'  => 'SearchSpecialFilterOptions',
        'SuchFilterJSON'      => 'SearchFilterJSON',
    ];

    public function __construct()
    {
        $this->products    = new Collection();
        $this->productKeys = new Collection();
        $this->pages       = new Info();
    }

    /**
     * @inheritdoc
     */
    public function convert(SearchResultsInterface|stdClass $legacy): SearchResultsInterface
    {
        if (\get_class($legacy) === __CLASS__) {
            return $legacy;
        }
        \trigger_error('Using a stdClass object for search results is deprecated.', \E_USER_DEPRECATED);
        foreach (\get_object_vars($legacy) as $var => $value) {
            $this->$var = $value;
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getProductsCompat(): stdClass
    {
        $compat              = new stdClass();
        $compat->elemente    = $this->getProducts();
        $compat->productKeys = $this->getProductKeys();

        return $compat;
    }

    /**
     * @inheritdoc
     */
    public function setProductsCompat(): SearchResultsInterface
    {
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getProductKeys(): Collection
    {
        return $this->productKeys;
    }

    /**
     * @inheritdoc
     */
    public function setProductKeys(Collection $keys): SearchResultsInterface
    {
        $this->productKeys = $keys;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    /**
     * @inheritdoc
     */
    public function setProducts($products): SearchResultsInterface
    {
        $this->products = $products;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getProductCount(): int
    {
        return $this->productCount;
    }

    /**
     * @inheritdoc
     */
    public function setProductCount(int $productCount): SearchResultsInterface
    {
        $this->productCount = $productCount;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getVisibleProductCount(): int
    {
        return $this->visibileProductCount;
    }

    /**
     * @inheritdoc
     */
    public function setVisibleProductCount(int $count): SearchResultsInterface
    {
        $this->visibileProductCount = $count;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getOffsetStart(): int
    {
        return $this->offsetStart;
    }

    /**
     * @inheritdoc
     */
    public function setOffsetStart(int $offsetStart): SearchResultsInterface
    {
        $this->offsetStart = $offsetStart;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getOffsetEnd(): int
    {
        return $this->offsetEnd;
    }

    /**
     * @inheritdoc
     */
    public function setOffsetEnd(int $offsetEnd): SearchResultsInterface
    {
        $this->offsetEnd = $offsetEnd;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getPages(): Info
    {
        return $this->pages;
    }

    /**
     * @inheritdoc
     */
    public function setPages(Info $pages): SearchResultsInterface
    {
        $this->pages = $pages;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getSearchTerm(): ?string
    {
        return $this->searchTerm;
    }

    /**
     * @inheritdoc
     */
    public function setSearchTerm(?string $searchTerm): SearchResultsInterface
    {
        $this->searchTerm = $searchTerm;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getSearchTermWrite(): ?string
    {
        return $this->searchTermWrite;
    }

    /**
     * @inheritdoc
     */
    public function setSearchTermWrite(?string $searchTerm): SearchResultsInterface
    {
        $this->searchTermWrite = $searchTerm;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getSearchUnsuccessful(): bool
    {
        return $this->searchUnsuccessful;
    }

    /**
     * @inheritdoc
     */
    public function setSearchUnsuccessful(bool $searchUnsuccessful): SearchResultsInterface
    {
        $this->searchUnsuccessful = $searchUnsuccessful;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getManufacturerFilterOptions(): array
    {
        return $this->manufacturerFilterOptions;
    }

    /**
     * @inheritdoc
     */
    public function setManufacturerFilterOptions(array $options): SearchResultsInterface
    {
        $this->manufacturerFilterOptions = $options;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getRatingFilterOptions(): array
    {
        return $this->ratingFilterOptions;
    }

    /**
     * @inheritdoc
     */
    public function setRatingFilterOptions(array $options): SearchResultsInterface
    {
        $this->ratingFilterOptions = $options;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getCharacteristicFilterOptions(): array
    {
        return $this->characteristicFilterOptions;
    }

    /**
     * @inheritdoc
     */
    public function setCharacteristicFilterOptions(array $options): SearchResultsInterface
    {
        $this->characteristicFilterOptions = $options;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getPriceRangeFilterOptions(): array
    {
        return $this->priceRangeFilterOptions;
    }

    /**
     * @inheritdoc
     */
    public function setPriceRangeFilterOptions(array $options): SearchResultsInterface
    {
        $this->priceRangeFilterOptions = $options;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getCategoryFilterOptions(): array
    {
        return $this->categoryFilterOptions;
    }

    /**
     * @inheritdoc
     */
    public function setCategoryFilterOptions(array $options): SearchResultsInterface
    {
        $this->categoryFilterOptions = $options;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getSearchFilterOptions(): array
    {
        return $this->searchFilterOptions;
    }

    /**
     * @inheritdoc
     */
    public function setSearchFilterOptions(array $options): SearchResultsInterface
    {
        $this->searchFilterOptions = $options;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getSearchSpecialFilterOptions(): array
    {
        return $this->searchSpecialFilterOptions;
    }

    /**
     * @inheritdoc
     */
    public function setSearchSpecialFilterOptions(array $options): SearchResultsInterface
    {
        $this->searchSpecialFilterOptions = $options;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getAvailabilityFilterOptions(): array
    {
        return $this->availabilityFilterOptions;
    }

    /**
     * @inheritdoc
     */
    public function setAvailabilityFilterOptions(array $options): SearchResultsInterface
    {
        $this->availabilityFilterOptions = $options;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getCustomFilterOptions(): array
    {
        return $this->customFilterOptions;
    }

    /**
     * @inheritdoc
     */
    public function setCustomFilterOptions(array $options): SearchResultsInterface
    {
        $this->customFilterOptions = $options;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getSearchFilterJSON(): ?string
    {
        return $this->searchFilterJSON;
    }

    /**
     * @inheritdoc
     */
    public function setSearchFilterJSON(?string $json): SearchResultsInterface
    {
        $this->searchFilterJSON = $json;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * @inheritdoc
     */
    public function setError(?string $error): SearchResultsInterface
    {
        $this->error = $error;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getSortingOptions(): array
    {
        return $this->sortingOptions;
    }

    /**
     * @inheritdoc
     */
    public function setSortingOptions(array $options): SearchResultsInterface
    {
        $this->sortingOptions = $options;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getLimitOptions(): array
    {
        return $this->limitOptions;
    }

    /**
     * @inheritdoc
     */
    public function setLimitOptions(array $options): SearchResultsInterface
    {
        $this->limitOptions = $options;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getAllFilterOptions(): array
    {
        return [
            'manufacturerFilterOptions'   => $this->getManufacturerFilterOptions(),
            'ratingFilterOptions'         => $this->getRatingFilterOptions(),
            'CharacteristicFilterOptions' => $this->getCharacteristicFilterOptions(),
            'priceRangeFilterOptions'     => $this->getPriceRangeFilterOptions(),
            'categoryFilterOptions'       => $this->getCategoryFilterOptions(),
            'searchFilterOptions'         => $this->getSearchFilterOptions(),
            'searchSpecialFilterOptions'  => $this->getSearchSpecialFilterOptions(),
            'customFilterOptions'         => $this->getCustomFilterOptions()
        ];
    }

    /**
     * @param FilterInterface[] $activeFilters
     * @param FilterInterface[] $availableFilters
     */
    private function autoActivateOptions(array $activeFilters, array $availableFilters): void
    {
        foreach ($activeFilters as $activeFilter) {
            $class        = $activeFilter->getClassName();
            $activeValues = $activeFilter->getActiveValues();
            foreach ($this->getActiveFiltersByClassName($availableFilters, $class, $activeValues) as $filter) {
                $currentValues = $filter->getActiveValues();
                $act           = \is_array($currentValues)
                    ? map($currentValues, fn(FilterInterface $e) => $e->getValue())
                    : [$currentValues->getValue()];
                $this->updateOptions($filter, $act);
            }
        }
    }

    /**
     * @param FilterInterface $filter
     * @param array<mixed>    $values
     */
    private function updateOptions(FilterInterface $filter, array $values): void
    {
        invoke(
            filter($filter->getOptions(), static fn(Option $e): bool => \in_array($e->getValue(), $values, true)),
            'setIsActive',
            [true]
        );
    }

    /**
     * @param FilterInterface[] $filters
     * @param string            $class
     * @param Option|Option[]   $activeValues
     * @return FilterInterface[]
     */
    private function getActiveFiltersByClassName(array $filters, string $class, array|Option $activeValues): array
    {
        return filter(
            $filters,
            fn(FilterInterface $f): bool => $f->getClassName() === $class && $f->getActiveValues() === $activeValues
        );
    }

    /**
     * @inheritdoc
     */
    public function setFilterOptions(
        ProductFilter $productFilter,
        ?Kategorie $currentCategory = null,
        bool $selectionWizard = false
    ): SearchResultsInterface {
        if ($productFilter->isExtendedJTLSearch()) {
            return $this;
        }
        // @todo: make option
        $hideActiveOnly              = true;
        $manufacturerOptions         = $productFilter->getManufacturerFilter()->getOptions();
        $ratingOptions               = $productFilter->getRatingFilter()->getOptions();
        $categoryOptions             = $productFilter->getCategoryFilter()->getOptions();
        $priceRangeOptions           = $productFilter->getPriceRangeFilter()->getOptions($this->getProductCount());
        $searchSpecialFilters        = $productFilter->getSearchSpecialFilter()->getOptions();
        $availabilityOptions         = $productFilter->getAvailabilityFilter()->getOptions();
        $characteristicFilterOptions = $productFilter->getCharacteristicFilterCollection()->getOptions([
            'oAktuelleKategorie' => $currentCategory,
            'bForce'             => $selectionWizard === true
        ]);
        $searchFilterOptions         = [];
        foreach ($productFilter->getSearchFilter() as $searchFilter) {
            $opt = $searchFilter->getOptions();
            if (\is_array($opt)) {
                foreach ($opt as $_o) {
                    $searchFilterOptions[] = $_o;
                }
            }
        }
        $this->autoActivateOptions($productFilter->getActiveFilters(), $productFilter->getAvailableFilters());

        $customFilterOptions = map(
            $productFilter->getCustomFilters(),
            static function (FilterInterface $e): FilterInterface {
                if (\count($e->getOptions()) === 0) {
                    $e->hide();
                }

                return $e;
            }
        );
        $json                = AbstractBox::getJSONString(
            \array_map(
                static function (stdClass|Option $e) {
                    $e->cURL = Text::htmlentitydecode($e->cURL);

                    return $e;
                },
                $searchFilterOptions
            )
        );

        $this->setManufacturerFilterOptions($manufacturerOptions)
            ->setSortingOptions($productFilter->getSorting()->getOptions())
            ->setLimitOptions($productFilter->getLimits()->getOptions())
            ->setRatingFilterOptions($ratingOptions)
            ->setPriceRangeFilterOptions($priceRangeOptions)
            ->setCategoryFilterOptions($categoryOptions)
            ->setSearchFilterOptions($searchFilterOptions)
            ->setSearchSpecialFilterOptions($searchSpecialFilters)
            ->setAvailabilityFilterOptions($availabilityOptions)
            ->setCharacteristicFilterOptions($characteristicFilterOptions)
            ->setCustomFilterOptions($customFilterOptions)
            ->setSearchFilterJSON($json);

        if (empty($searchSpecialFilters)) {
            // hide category filter when a category is being browsed
            $productFilter->getSearchSpecialFilter()->hide();
        }
        if (
            empty($categoryOptions)
            || \count($categoryOptions) === 0
            || ($productFilter->getCategory()->isInitialized()
                && $productFilter->getCategory()->getValue() !== null)
        ) {
            // hide category filter when a category is being browsed
            $productFilter->getCategoryFilter()->hide();
        }
        if (
            empty($priceRangeOptions)
            || \count($priceRangeOptions) === 0
            || ($productFilter->getPriceRangeFilter()->isInitialized()
                && $productFilter->getPriceRangeFilter()->getValue() !== null)
        ) {
            // hide empty price ranges
            $productFilter->getPriceRangeFilter()->hide();
        }
        if (
            empty($manufacturerOptions) || \count($manufacturerOptions) === 0
            || $productFilter->getManufacturer()->isInitialized()
            || ($productFilter->getManufacturerFilter()->isInitialized()
                && \count($manufacturerOptions) === 1
                && $hideActiveOnly)
        ) {
            // hide manufacturer filter when browsing manufacturer products
            $productFilter->getManufacturerFilter()->hide();
        }
        if (empty($ratingOptions)) {
            $productFilter->getRatingFilter()->hide();
        }
        if (\count($characteristicFilterOptions) < 1) {
            $productFilter->getCharacteristicFilterCollection()->hide();
        } elseif ($hideActiveOnly === true) {
            foreach ($characteristicFilterOptions as $af) {
                $options = $af->getOptions();
                if (
                    \is_array($options)
                    && $af->getVisibility() !== Visibility::SHOW_NEVER
                    && \array_reduce(
                        $options,
                        static fn($carry, Option $option): bool => $carry && $option->isActive(),
                        true
                    ) === true
                ) {
                    $af->hide();
                }
            }
            if (
                every($characteristicFilterOptions, static function (Option $item): bool {
                    return $item->getVisibility() === Visibility::SHOW_NEVER;
                })
            ) {
                // hide the whole attribute filter collection if every filter consists of only active options
                $productFilter->getCharacteristicFilterCollection()->hide();
            }
        }
        $productFilter->getCharacteristicFilterCollection()
            ->setFilterCollection($characteristicFilterOptions);

        return $this;
    }
}
