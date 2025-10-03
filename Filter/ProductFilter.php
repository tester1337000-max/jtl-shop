<?php

declare(strict_types=1);

namespace JTL\Filter;

use Illuminate\Support\Collection;
use JTL\Cache\JTLCacheInterface;
use JTL\Catalog\Category\Kategorie;
use JTL\Catalog\Product\Artikel;
use JTL\DB\DbInterface;
use JTL\Filter\Items\Availability;
use JTL\Filter\Items\Category;
use JTL\Filter\Items\Characteristic;
use JTL\Filter\Items\Limit;
use JTL\Filter\Items\Manufacturer;
use JTL\Filter\Items\PriceRange;
use JTL\Filter\Items\Rating;
use JTL\Filter\Items\Search;
use JTL\Filter\Items\SearchSpecial;
use JTL\Filter\Items\Sort;
use JTL\Filter\Pagination\Info;
use JTL\Filter\SortingOptions\Factory;
use JTL\Filter\States\BaseCategory;
use JTL\Filter\States\BaseCharacteristic;
use JTL\Filter\States\BaseManufacturer;
use JTL\Filter\States\BaseSearchQuery;
use JTL\Filter\States\BaseSearchSpecial;
use JTL\Filter\States\DummyState;
use JTL\Helpers\Request;
use JTL\Helpers\Text;
use JTL\MagicCompatibilityTrait;
use JTL\Mapper\SortingType;
use JTL\Session\Frontend;
use JTL\Shop;
use stdClass;

use function Functional\first;
use function Functional\flatten;
use function Functional\group;
use function Functional\map;
use function Functional\select;

/**
 * Class ProductFilter
 * @package JTL\Filter
 */
class ProductFilter
{
    use MagicCompatibilityTrait;

    /**
     * @var BaseCategory
     */
    private FilterInterface $category;

    /**
     * @var Category
     */
    private FilterInterface $categoryFilter;

    /**
     * @var BaseManufacturer
     */
    private FilterInterface $manufacturer;

    /**
     * @var Manufacturer
     */
    private FilterInterface $manufacturerFilter;

    /**
     * @var BaseCharacteristic
     */
    private FilterInterface $characteristicValue;

    /**
     * @var BaseSearchQuery
     */
    private FilterInterface $searchQuery;

    /**
     * @var Search[]
     */
    private array $searchFilter = [];

    /**
     * @var Characteristic[]
     */
    private array $characteristicFilter = [];

    /**
     * @var SearchSpecial
     */
    private FilterInterface $searchSpecialFilter;

    /**
     * @var Availability
     */
    private FilterInterface $availabilityFilter;

    /**
     * @var Rating
     */
    private FilterInterface $ratingFilter;

    /**
     * @var PriceRange
     */
    private FilterInterface $priceRangeFilter;

    /**
     * @var BaseSearchSpecial
     */
    private FilterInterface $searchSpecial;

    /**
     * @var Search
     */
    private FilterInterface $search;

    private ?stdClass $EchteSuche = null;

    private int $productLimit = 0;

    private int $nSeite = 1;

    private int $nSortierung = 0;

    /**
     * @var array<mixed>
     */
    private array $params = [];

    /**
     * @var FilterInterface[]
     */
    private array $filters = [];

    /**
     * @var FilterInterface[]
     */
    private array $activeFilters = [];

    private FilterInterface $baseState;

    private NavigationURLsInterface $url;

    /**
     * @var Characteristic
     */
    private FilterInterface $characteristicFilterCollection;

    /**
     * @var Search
     */
    public FilterInterface $searchFilterCompat;

    /**
     * @var SearchResultsInterface|null
     */
    private ?SearchResultsInterface $searchResults = null;

    private MetadataInterface $metaData;

    private ProductFilterSQLInterface $filterSQL;

    private ProductFilterURL $filterURL;

    private bool $bExtendedJTLSearch = false;

    private ?stdClass $oExtendedJTLSearchResponse = null;

    private int $showChildProducts;

    /**
     * @var Sort
     */
    private FilterInterface $sorting;

    /**
     * @var Limit
     */
    private FilterInterface $limits;

    /**
     * @var array<string, string>
     */
    public static array $mapping = [
        'nAnzahlFilter'      => 'FilterCount',
        'nAnzahlProSeite'    => 'ProductLimit',
        'Kategorie'          => 'Category',
        'KategorieFilter'    => 'CategoryFilter',
        'Hersteller'         => 'Manufacturer',
        'HerstellerFilter'   => 'ManufacturerFilter',
        'Suchanfrage'        => 'SearchQuery',
        'MerkmalWert'        => 'CharacteristicValue',
        'Suchspecial'        => 'SearchSpecial',
        'MerkmalFilter'      => 'CharacteristicFilter',
        'SuchFilter'         => 'SearchFilter',
        'SuchspecialFilter'  => 'SearchSpecialFilter',
        'BewertungFilter'    => 'RatingFilter',
        'PreisspannenFilter' => 'PriceRangeFilter',
        'Suche'              => 'Search',
        'EchteSuche'         => 'RealSearch',
        'oSprache_arr'       => 'Languages',
        'URL'                => 'URL'
    ];

    public function __construct(
        private ConfigInterface $filterConfig,
        private DbInterface $db,
        private JTLCacheInterface $cache
    ) {
        $this->showChildProducts = \defined('SHOW_CHILD_PRODUCTS')
            ? \SHOW_CHILD_PRODUCTS
            : 0;

        $this->url       = new NavigationURLs();
        $this->metaData  = new Metadata($this);
        $this->filterSQL = new ProductFilterSQL($this);
        $this->filterURL = new ProductFilterURL($this);

        $this->initBaseStates();
        \executeHook(\HOOK_PRODUCTFILTER_CREATE, ['productFilter' => $this]);
    }

    public function showChildProducts(): int
    {
        return $this->showChildProducts;
    }

    public function setShowChildProducts(int $showChildProducts): self
    {
        $this->showChildProducts = $showChildProducts;

        return $this;
    }

    public function getSort(): int
    {
        return $this->nSortierung;
    }

    public function setSort(int $sort): self
    {
        $this->nSortierung = $sort;

        return $this;
    }

    public function getURL(): NavigationURLsInterface
    {
        return $this->url;
    }

    public function setURL(NavigationURLsInterface $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getFilterSQL(): ProductFilterSQLInterface
    {
        return $this->filterSQL;
    }

    public function setFilterSQL(ProductFilterSQLInterface $filterSQL): self
    {
        $this->filterSQL = $filterSQL;

        return $this;
    }

    public function getFilterURL(): ProductFilterURL
    {
        return $this->filterURL;
    }

    public function setFilterURL(ProductFilterURL $filterURL): self
    {
        $this->filterURL = $filterURL;

        return $this;
    }

    /**
     * @return Collection<int, Artikel>
     */
    public function getSearchResultProducts(): Collection
    {
        if ($this->searchResults === null) {
            $this->searchResults = new SearchResults();
        }

        return $this->searchResults->getProducts();
    }

    public function getSearchResults(): SearchResultsInterface
    {
        if ($this->searchResults === null) {
            $this->searchResults = new SearchResults();
        }

        return $this->searchResults;
    }

    public function setSearchResults(SearchResultsInterface $results): self
    {
        $this->searchResults = $results;

        return $this;
    }

    public function getMetaData(): MetadataInterface
    {
        return $this->metaData;
    }

    public function getPage(): int
    {
        return $this->nSeite;
    }

    public function getBaseState(): FilterInterface
    {
        return $this->baseState;
    }

    public function setBaseState(FilterInterface $filter): self
    {
        $this->baseState = $filter;

        return $this;
    }

    public function getProductLimit(): int
    {
        return $this->productLimit;
    }

    public function setProductLimit(int $limit): self
    {
        $this->productLimit = $limit;

        return $this;
    }

    public function getSorting(): Sort
    {
        return $this->sorting;
    }

    public function setSorting(Sort $sorting): self
    {
        $this->sorting = $sorting;

        return $this;
    }

    public function getLimits(): Limit
    {
        return $this->limits;
    }

    public function setLimits(Limit $limits): self
    {
        $this->limits = $limits;

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function getParams(): array
    {
        return \array_merge($this->getParamsPrototype(), $this->params);
    }

    /**
     * @return array<string, mixed> - default array keys
     */
    private function getParamsPrototype(): array
    {
        return [
            'kKategorie'             => 0,
            'kKonfigPos'             => 0,
            'kHersteller'            => 0,
            'kArtikel'               => 0,
            'kVariKindArtikel'       => 0,
            'kSeite'                 => 0,
            'kLink'                  => 0,
            'kSuchanfrage'           => 0,
            'kMerkmalWert'           => 0,
            'kSuchspecial'           => 0,
            'kKategorieFilter'       => 0,
            'kHerstellerFilter'      => 0,
            'nBewertungSterneFilter' => 0,
            'cPreisspannenFilter'    => '',
            'kSuchspecialFilter'     => 0,
            'nSortierung'            => 0,
            'nSort'                  => 0,
            'MerkmalFilter_arr'      => [],
            'SuchFilter_arr'         => [],
            'nArtikelProSeite'       => null,
            'cSuche'                 => null,
            'seite'                  => null,
            'show'                   => true,
            'kSuchFilter'            => 0,
            'kWunschliste'           => 0,
            'MerkmalFilter'          => null,
            'SuchFilter'             => null,
            'vergleichsliste'        => null,
            'nDarstellung'           => 0,
            'isSeoMainword'          => false,
            'cDatum'                 => '',
            'nAnzahl'                => 0,
            'nSterne'                => 0,
            'customFilters'          => [],
            'searchSpecialFilters'   => [],
            'manufacturerFilters'    => [],
            'categoryFilters'        => []
        ];
    }

    public function initBaseStates(): self
    {
        $this->category       = new BaseCategory($this);
        $this->categoryFilter = new Category($this);

        $this->manufacturer       = new BaseManufacturer($this);
        $this->manufacturerFilter = new Manufacturer($this);

        $this->searchQuery = new BaseSearchQuery($this);

        $this->characteristicValue = new BaseCharacteristic($this);

        $this->searchSpecial = new BaseSearchSpecial($this);

        $this->filters              = [];
        $this->activeFilters        = [];
        $this->characteristicFilter = [];
        $this->searchFilter         = [];

        $this->searchSpecialFilter = new SearchSpecial($this);

        $this->availabilityFilter = new Availability($this);

        $this->ratingFilter = new Rating($this);

        $this->priceRangeFilter = new PriceRange($this);

        $this->characteristicFilterCollection = new Characteristic($this);
        $this->searchFilterCompat             = new Search($this);

        $this->search = new Search($this);

        $this->baseState = new DummyState($this);

        \executeHook(\HOOK_PRODUCTFILTER_INIT, ['productFilter' => $this]);

        $this->filters[] = $this->categoryFilter;
        $this->filters[] = $this->manufacturerFilter;
        $this->filters[] = $this->characteristicFilterCollection;
        $this->filters[] = $this->searchSpecialFilter;
        $this->filters[] = $this->priceRangeFilter;
        $this->filters[] = $this->ratingFilter;
        $this->filters[] = $this->search;
        $this->filters[] = $this->availabilityFilter;

        $this->sorting = new Sort($this);
        $this->limits  = new Limit($this);

        $this->sorting->setFactory(new Factory($this));
        $this->sorting->registerSortingOptions();

        return $this;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function initStates(array $params, bool $validate = true): self
    {
        $this->activeFilters = [];

        $params = \array_merge($this->getParamsPrototype(), $params);
        if ($params['kKategorie'] > 0) {
            $this->baseState = $this->category->init($params['kKategorie']);
        } elseif ($params['kHersteller'] > 0) {
            $this->manufacturer->init($params['kHersteller']);
            $this->baseState = $this->manufacturer;
        } elseif ($params['kMerkmalWert'] > 0) {
            $this->characteristicValue = (new BaseCharacteristic($this))->init($params['kMerkmalWert']);
            $this->baseState           = $this->characteristicValue;
        } elseif ($params['kSuchspecial'] > 0) {
            $this->searchSpecial->init($params['kSuchspecial']);
            $this->baseState = $this->searchSpecial;
        }
        if ($params['kKategorieFilter'] > 0 && \count($params['categoryFilters']) === 0) {
            // backwards compatibility
            if (\is_array($params['kKategorieFilter'])) {
                foreach ($params['kKategorieFilter'] as $param) {
                    $params['categoryFilters'][] = $param;
                }
            } else {
                $params['categoryFilters'][] = $params['kKategorieFilter'];
            }
        }
        if (\count($params['categoryFilters']) > 0) {
            $this->addActiveFilter($this->categoryFilter, $params['categoryFilters']);
        }
        if ($params['kHerstellerFilter'] > 0 || \count($params['manufacturerFilters']) > 0) {
            $this->addActiveFilter(
                $this->manufacturerFilter,
                \count($params['manufacturerFilters']) > 0
                    ? $params['manufacturerFilters']
                    : $params['kHerstellerFilter']
            );
        }
        if ($params['nBewertungSterneFilter'] > 0) {
            $this->addActiveFilter($this->ratingFilter, $params['nBewertungSterneFilter']);
        }
        if (\mb_strlen($params['cPreisspannenFilter']) > 0) {
            $this->addActiveFilter($this->priceRangeFilter, $params['cPreisspannenFilter']);
        }
        $this->initCharacteristicFilters($params['MerkmalFilter_arr']);
        if ($params['kSuchspecialFilter'] > 0 && \count($params['searchSpecialFilters']) === 0) {
            // backwards compatibility
            $params['searchSpecialFilters'][] = $params['kSuchspecialFilter'];
        }
        if (\count($params['searchSpecialFilters']) > 0) {
            $this->addActiveFilter($this->searchSpecialFilter, $params['searchSpecialFilters']);
        }

        foreach ($params['SuchFilter_arr'] as $sf) {
            /** @var Search $activeSearchFilter */
            $activeSearchFilter   = $this->addActiveFilter(new Search($this), $sf);
            $this->searchFilter[] = $activeSearchFilter;
        }
        if ($params['nSortierung'] > 0) {
            $this->nSortierung = (int)$params['nSortierung'];
        }
        if ($params['nArtikelProSeite'] !== 0) {
            $this->productLimit = (int)$params['nArtikelProSeite'];
        }
        if ($params['kSuchanfrage'] > 0) {
            $queryData = $this->db->select(
                'tsuchanfrage',
                'kSuchanfrage',
                $params['kSuchanfrage']
            );
            if ($queryData !== null && isset($queryData->cSuche) && \mb_strlen($queryData->cSuche) > 0) {
                $this->search->setName($queryData->cSuche);
            }
            // Suchcache beachten / erstellen
            $searchName = $this->search->getName();
            if (!empty($searchName)) {
                $this->searchQuery->setName($searchName);
                $this->search->setSearchCacheID($this->searchQuery->editSearchCache());
                $this->searchQuery->setID((int)$queryData->kSuchanfrage);
                $this->searchQuery->init($queryData->cSuche);
                $this->searchQuery->setSearchCacheID($this->search->getSearchCacheID())
                    ->setName($this->search->getName());
                if (!$this->baseState->isInitialized()) {
                    $this->baseState = $this->searchQuery;
                }
            }
        } elseif ($params['cSuche'] !== null && \mb_strlen($params['cSuche']) > 0) {
            $params['cSuche'] = Text::filterXSS($params['cSuche']);
            $this->search->setName($params['cSuche']);
            $this->searchQuery->setName($params['cSuche']);
            $queryData = $this->db->select(
                'tsuchanfrage',
                'cSuche',
                $params['cSuche'],
                'kSprache',
                $this->getFilterConfig()->getLanguageID(),
                'nAktiv',
                1,
                false,
                'kSuchanfrage'
            );
            $cacheID   = $this->searchQuery->editSearchCache();
            $queryID   = isset($queryData->kSuchanfrage)
                ? (int)$queryData->kSuchanfrage
                : $params['kSuchanfrage'];
            $this->search->setSearchCacheID($cacheID);
            $this->searchQuery->setSearchCacheID($cacheID)
                ->init($queryID)
                ->setName($params['cSuche']);
            $this->EchteSuche         = new stdClass();
            $this->EchteSuche->cSuche = $params['cSuche'];
            if (!$this->baseState->isInitialized()) {
                $this->baseState = $this->searchQuery;
            }
            $limit                            = $this->limits->getProductsPerPageLimit();
            $this->oExtendedJTLSearchResponse = null;
            $this->bExtendedJTLSearch         = false;

            \executeHook(\HOOK_NAVI_PRESUCHE, [
                'cValue'             => &$this->EchteSuche->cSuche,
                'bExtendedJTLSearch' => &$this->bExtendedJTLSearch
            ]);
            if (empty($params['cSuche'])) {
                $this->bExtendedJTLSearch = false;
            }
            $this->nSeite = \max(1, Request::verifyGPCDataInt('seite'));
            \executeHook(\HOOK_NAVI_SUCHE, [
                'bExtendedJTLSearch'         => &$this->bExtendedJTLSearch,
                'oExtendedJTLSearchResponse' => &$this->oExtendedJTLSearchResponse,
                'cValue'                     => &$this->EchteSuche->cSuche,
                'nArtikelProSeite'           => &$limit,
                'nSeite'                     => &$this->nSeite,
                'nSortierung'                => $_SESSION['Usersortierung'] ?? null,
                'bLagerbeachten'             => (int)$this->getFilterConfig()->getConfig('global')
                    ['artikel_artikelanzeigefilter'] === \EINSTELLUNGEN_ARTIKELANZEIGEFILTER_LAGERNULL
            ]);
            $this->search->bExtendedJTLSearch = $this->bExtendedJTLSearch;
        }
        $this->nSeite = \max(1, Request::verifyGPCDataInt('seite'));
        foreach ($this->getCustomFilters() as $filter) {
            $filterParam = $filter->getUrlParam();
            $filterClass = $filter->getClassName();
            if (isset($_GET[$filterParam])) {
                // OR filters should always get an array as input - even if there is just one value active
                if (!\is_array($_GET[$filterParam]) && $filter->getType() === Type::OR) {
                    $_GET[$filterParam] = [$_GET[$filterParam]];
                }
                // escape all input values
                if (
                    ($filter->getType() === Type::OR && \is_array($_GET[$filterParam]))
                    || ($filter->getType() === Type::AND
                        && (Request::verifyGPCDataInt($filterParam) > 0
                            || Request::verifyGPDataString($filterParam) !== ''))
                ) {
                    $filterValue = \is_array($_GET[$filterParam])
                        ? \array_map([$this->db, 'escape'], $_GET[$filterParam])
                        : $this->db->escape($_GET[$filterParam]);
                    $this->addActiveFilter($filter, $filterValue);
                    $params[$filterParam] = $filterValue;
                }
            } elseif (\count($params['customFilters']) > 0) {
                foreach ($params['customFilters'] as $className => $filterValue) {
                    if ($filterClass === $className) {
                        $this->addActiveFilter($filter, $filterValue);
                        $params[$filterParam] = $filterValue;
                    }
                }
            }
        }
        \executeHook(\HOOK_PRODUCTFILTER_INIT_STATES, [
            'productFilter' => $this,
            'params'        => $params
        ]);
        $this->params = $params;

        return $validate === true ? $this->validate() : $this;
    }

    /**
     * @param array<numeric-string|int> $values
     */
    private function initCharacteristicFilters(array $values): self
    {
        if (\count($values) === 0) {
            return $this;
        }
        $characteristics = $this->db->getObjects(
            'SELECT tmerkmalwert.kMerkmal, tmerkmalwert.kMerkmalWert, tmerkmal.nMehrfachauswahl
                FROM tmerkmalwert
                JOIN tmerkmal 
                    ON tmerkmal.kMerkmal = tmerkmalwert.kMerkmal
                WHERE kMerkmalWert IN (' . \implode(',', \array_map('\intval', $values)) . ')'
        );
        foreach ($characteristics as $characteristic) {
            $characteristic->kMerkmal         = (int)$characteristic->kMerkmal;
            $characteristic->kMerkmalWert     = (int)$characteristic->kMerkmalWert;
            $characteristic->nMehrfachauswahl = (int)$characteristic->nMehrfachauswahl;
            $this->characteristicFilter[]     = $this->addActiveFilter(new Characteristic($this), $characteristic);
        }

        return $this;
    }

    public function registerFilter(FilterInterface $filter): self
    {
        $this->filters[] = $filter->setBaseData($this);

        return $this;
    }

    /**
     * @param class-string<FilterInterface> $filterName
     * @return FilterInterface
     * @throws \InvalidArgumentException
     */
    public function registerFilterByClassName(string $filterName): FilterInterface
    {
        if (\class_exists($filterName)) {
            $filter          = new $filterName($this);
            $this->filters[] = $filter->setClassName($filterName);
        } else {
            throw new \InvalidArgumentException('Cannot register filter class ' . $filterName);
        }

        return $filter;
    }

    public function addActiveFilter(FilterInterface $filter, mixed $filterValue): FilterInterface
    {
        $this->activeFilters[] = $filter->setBaseData($this)->init($filterValue)->generateActiveFilterData();

        return $filter;
    }

    public function enableFilter(FilterInterface $filter): self
    {
        foreach ($this->filters as $idx => $registeredFilter) {
            if ($filter->getName() === $registeredFilter->getName()) {
                $this->filters[$idx] = $filter;
            }
        }
        $this->activeFilters[] = $filter;

        return $this;
    }

    /**
     * @param class-string<FilterInterface> $filterClassName
     * @return int|array<mixed>|null
     */
    public function getFilterValue(string $filterClassName): mixed
    {
        return \array_reduce(
            $this->activeFilters,
            static function ($carry, $item) use ($filterClassName) {
                /** @var FilterInterface $item */
                return $carry ?? ($item->getClassName() === $filterClassName
                    ? $item->getValue()
                    : null);
            }
        );
    }

    /**
     * @param class-string<FilterInterface> $filterClassName
     */
    public function hasFilter(string $filterClassName): bool
    {
        return $this->getActiveFilterByClassName($filterClassName) !== null;
    }

    /**
     * @param class-string<FilterInterface> $filterClassName
     */
    public function getFilterByClassName(string $filterClassName): ?FilterInterface
    {
        return first($this->filters, fn(FilterInterface $filter): bool => $filter->getClassName() === $filterClassName);
    }

    /**
     * @param class-string<FilterInterface> $filterClassName
     */
    public function getActiveFilterByClassName(string $filterClassName): ?FilterInterface
    {
        return first(
            $this->activeFilters,
            fn(FilterInterface $filter): bool => $filter->getClassName() === $filterClassName
        );
    }

    /**
     * @return FilterInterface[]
     */
    public function getCustomFilters(): array
    {
        return \array_filter($this->filters, static fn(FilterInterface $e): bool => $e->isCustom());
    }

    /**
     * @return FilterInterface[]
     */
    public function getAvailableFilters(): array
    {
        return $this->filters;
    }

    /**
     * @param FilterInterface[] $filters
     * @return $this
     */
    public function setAvailableFilters(array $filters): self
    {
        $this->filters = $filters;

        return $this;
    }

    /**
     * get filters that can be displayed at content level
     *
     * @return FilterInterface[]
     */
    public function getAvailableContentFilters(): array
    {
        if ($this->bExtendedJTLSearch === true) {
            return [];
        }
        $templateSettings = $this->filterConfig->getConfig('template');

        return \array_filter(
            $this->filters,
            static function (FilterInterface $f) use ($templateSettings): bool {
                return $f->getVisibility() === Visibility::SHOW_ALWAYS
                    || $f->getVisibility() === Visibility::SHOW_CONTENT
                    || ($f->getClassName() === PriceRange::class
                        && ($templateSettings['productlist']['always_show_price_range'] ?? 'N') === 'Y'
                    );
            }
        );
    }

    public function getFilterCount(): int
    {
        return \count($this->activeFilters);
    }

    /**
     * @param class-string<FilterInterface> $className
     */
    public function override(string $className, FilterInterface $filter): bool
    {
        foreach ($this->filters as $i => $registerdFilter) {
            if ($registerdFilter->getClassName() === $className) {
                $this->filters[$i] = $filter;

                return true;
            }
        }

        return false;
    }

    /**
     * @return Manufacturer
     */
    public function getManufacturerFilter(): FilterInterface
    {
        return $this->manufacturerFilter;
    }

    public function setManufacturerFilter(FilterInterface|stdClass $filter): self
    {
        if (\is_a($filter, stdClass::class) && !isset($filter->kHersteller)) {
            // disallow setting manufacturer filter to empty stdClass
            return $this;
        }
        $this->manufacturerFilter = $filter;

        return $this;
    }

    public function hasManufacturerFilter(): bool
    {
        return $this->manufacturerFilter->isInitialized();
    }

    /**
     * @return BaseManufacturer
     */
    public function getManufacturer(): FilterInterface
    {
        return $this->manufacturer;
    }

    public function setManufacturer(FilterInterface|stdClass $filter): self
    {
        if (\is_a($filter, stdClass::class) && !isset($filter->kHersteller)) {
            // disallow setting manufacturer base to empty stdClass
            return $this;
        }
        $this->manufacturer = $filter;

        return $this;
    }

    public function hasManufacturer(): bool
    {
        return $this->manufacturer->isInitialized();
    }

    /**
     * returns ALL registered characteristic filters
     *
     * @return Characteristic[]
     */
    public function getCharacteristicFilters(): array
    {
        return $this->characteristicFilter;
    }

    /**
     * this method works like pre Shop 4.06 - only returns ACTIVE attribute filters
     *
     * @param int|null $idx
     * @return Characteristic|Characteristic[]
     */
    public function getCharacteristicFilter(?int $idx = null): array|Characteristic
    {
        return $idx === null ? $this->characteristicFilter : $this->characteristicFilter[$idx];
    }

    /**
     * @param Characteristic[]|stdClass|Characteristic $filter
     * @return $this
     */
    public function setCharacteristicFilter(array|Characteristic|stdClass $filter): self
    {
        if (\is_a($filter, stdClass::class) && !isset($filter->kMerkmal)) {
            // disallow setting attribute filter to empty stdClass
            return $this;
        }
        $this->characteristicFilter = $filter;

        return $this;
    }

    public function addCharacteristicFilter(FilterInterface $filter): self
    {
        $this->characteristicFilter[] = $filter;

        return $this;
    }

    public function hasCharacteristicFilter(): bool
    {
        return \count($this->characteristicFilter) > 0;
    }

    /**
     * @return BaseCharacteristic
     */
    public function getCharacteristicValue(): FilterInterface
    {
        return $this->characteristicValue;
    }

    /**
     * @param Characteristic[]|stdClass $filter
     */
    public function setCharacteristicValue(array|stdClass $filter): self
    {
        if (\is_a($filter, stdClass::class) && !isset($filter->kMerkmalWert)) {
            // disallow setting attribute value to empty stdClass
            return $this;
        }
        $this->characteristicFilter = $filter;

        return $this;
    }

    public function hasCharacteristicValue(): bool
    {
        return $this->characteristicValue->isInitialized();
    }

    /**
     * @return Characteristic
     */
    public function getCharacteristicFilterCollection(): FilterInterface
    {
        return $this->characteristicFilterCollection;
    }

    /**
     * @return BaseCategory
     */
    public function getCategory(): FilterInterface
    {
        return $this->category;
    }

    public function setCategory(FilterInterface|stdClass $filter): self
    {
        if (\is_a($filter, stdClass::class) && !isset($filter->kKategorie)) {
            // disallow setting category base to empty stdClass
            return $this;
        }
        $this->category = $filter;

        return $this;
    }

    public function hasCategory(): bool
    {
        return $this->category->isInitialized();
    }

    /**
     * @return Category
     */
    public function getCategoryFilter(): FilterInterface
    {
        return $this->categoryFilter;
    }

    public function setCategoryFilter(FilterInterface|stdClass $filter): self
    {
        if (\is_a($filter, stdClass::class) && !isset($filter->kKategorie)) {
            // disallow setting category filter to empty stdClass
            return $this;
        }
        $this->categoryFilter = $filter;

        return $this;
    }

    public function hasCategoryFilter(): bool
    {
        return $this->categoryFilter->isInitialized();
    }

    /**
     * @return Search
     */
    public function getSearch(): FilterInterface
    {
        return $this->search;
    }

    public function hasSearch(): bool
    {
        return $this->search->getName() !== null;
    }

    /**
     * @return BaseSearchQuery
     */
    public function getSearchQuery(): FilterInterface
    {
        return $this->searchQuery;
    }

    public function hasSearchQuery(): bool
    {
        return $this->searchQuery->isInitialized();
    }

    public function setSearchQuery(FilterInterface $filter): self
    {
        $this->searchQuery = $filter;

        return $this;
    }

    /**
     * @param null|int $idx
     * @return Search|Search[]
     */
    public function getSearchFilter(?int $idx = null): array|Search
    {
        return $idx === null ? $this->searchFilter : $this->searchFilter[$idx];
    }

    public function hasSearchFilter(): bool
    {
        return \count($this->searchFilter) > 0;
    }

    /**
     * @return BaseSearchSpecial
     */
    public function getSearchSpecial(): FilterInterface
    {
        return $this->searchSpecial;
    }

    public function setSearchSpecial(FilterInterface $filter): self
    {
        $this->searchSpecial = $filter;

        return $this;
    }

    public function hasSearchSpecial(): bool
    {
        return $this->searchSpecial->isInitialized();
    }

    /**
     * @return SearchSpecial
     */
    public function getSearchSpecialFilter(): FilterInterface
    {
        return $this->searchSpecialFilter;
    }

    public function setSearchSpecialFilter(FilterInterface $filter): self
    {
        $this->searchSpecialFilter = $filter;

        return $this;
    }

    /**
     * @return SearchSpecial
     */
    public function getAvailabilityFilter(): FilterInterface
    {
        return $this->availabilityFilter;
    }

    public function setAvailabilityFilter(FilterInterface $filter): self
    {
        $this->availabilityFilter = $filter;

        return $this;
    }

    public function hasSearchSpecialFilter(): bool
    {
        return $this->searchSpecialFilter->isInitialized();
    }

    public function getRealSearch(): stdClass|null
    {
        return empty($this->EchteSuche->cSuche)
            ? null
            : $this->EchteSuche;
    }

    /**
     * @return Rating
     */
    public function getRatingFilter(): FilterInterface
    {
        return $this->ratingFilter;
    }

    public function setRatingFilter(FilterInterface $filter): self
    {
        $this->ratingFilter = $filter;

        return $this;
    }

    public function hasRatingFilter(): bool
    {
        return $this->ratingFilter->isInitialized();
    }

    /**
     * @return PriceRange
     */
    public function getPriceRangeFilter(): FilterInterface
    {
        return $this->priceRangeFilter;
    }

    public function setPriceRangeFilter(FilterInterface $filter): self
    {
        $this->priceRangeFilter = $filter;

        return $this;
    }

    public function hasPriceRangeFilter(): bool
    {
        return $this->priceRangeFilter->isInitialized();
    }

    public function validate(): self
    {
        if ($this->getFilterCount() === 0) {
            return $this;
        }
        $languageID = $this->getFilterConfig()->getLanguageID();
        $location   = 'Location: ' . $this->getFilterConfig()->getBaseURL();
        if (
            empty($this->search->getName())
            && !$this->hasManufacturer()
            && !$this->hasCategory()
            && !$this->hasSearchQuery()
            && !$this->hasCharacteristicValue()
            && !$this->hasSearchSpecial()
            && !(\get_class($this->getBaseState()) === DummyState::class && $this->getBaseState()->isInitialized())
        ) {
            // we have a manufacturer filter that doesn't filter anything
            if ($this->manufacturerFilter->getSeo($languageID) !== null) {
                \http_response_code(301);
                \header($location . $this->manufacturerFilter->getSeo($languageID));
                exit;
            }
            // we have a category filter that doesn't filter anything
            if ($this->categoryFilter->getSeo($languageID) !== null) {
                \http_response_code(301);
                \header($location . $this->categoryFilter->getSeo($languageID));
                exit;
            }
        } elseif (
            $this->hasManufacturer()
            && $this->hasManufacturerFilter()
            && \is_string($seo = $this->manufacturer->getSeo($languageID))
        ) {
            // we have a manufacturer page with some manufacturer filter
            \http_response_code(301);
            \header($location . $seo);
            exit;
        } elseif (
            $this->hasCategory()
            && $this->hasCategoryFilter()
            && \is_string($seo = $this->category->getSeo($languageID))
        ) {
            // we have a category page with some category filter
            \http_response_code(301);
            \header($location . $seo);
            exit;
        }

        return $this;
    }

    public function setUserSort(?Kategorie $category = null): self
    {
        $gpcSort = Request::verifyGPCDataInt('Sortierung');
        // user wants to reset default sorting
        if ($gpcSort === \SEARCH_SORT_STANDARD) {
            unset($_SESSION['Usersortierung'], $_SESSION['nUsersortierungWahl'], $_SESSION['UsersortierungVorSuche']);
        }
        // no sorting configured - use default from config
        if (!isset($_SESSION['Usersortierung'])) {
            unset($_SESSION['nUsersortierungWahl']);

            $_SESSION['Usersortierung'] = (int)$this->getFilterConfig()->getConfig('artikeluebersicht')
            ['artikeluebersicht_artikelsortierung'];
        }
        if (!isset($_SESSION['nUsersortierungWahl'])) {
            $_SESSION['Usersortierung'] = (int)$this->getFilterConfig()->getConfig('artikeluebersicht')
            ['artikeluebersicht_artikelsortierung'];
        }
        if (!isset($_SESSION['nUsersortierungWahl']) && $this->getSearch()->getSearchCacheID() > 0) {
            // nur bei initialsuche Sortierung zurücksetzen
            $_SESSION['UsersortierungVorSuche'] = $_SESSION['Usersortierung'];
            $_SESSION['Usersortierung']         = \SEARCH_SORT_STANDARD;
        }
        // custom category attribute
        if ($category !== null && !empty($category->getCategoryFunctionAttribute(\KAT_ATTRIBUT_ARTIKELSORTIERUNG))) {
            $mapper                     = new SortingType();
            $_SESSION['Usersortierung'] = $mapper->mapUserSorting(
                $category->getCategoryFunctionAttribute(\KAT_ATTRIBUT_ARTIKELSORTIERUNG)
            );
        }
        if (isset($_SESSION['UsersortierungVorSuche']) && (int)$_SESSION['UsersortierungVorSuche'] > 0) {
            $_SESSION['Usersortierung'] = (int)$_SESSION['UsersortierungVorSuche'];
        }
        // search special sorting
        if ($gpcSort === 0 && !isset($_SESSION['nUsersortierungWahl']) && $this->hasSearchSpecial()) {
            $mapping = $this->getSearchSpecialConfigMapping();
            /** @var int $idx */
            $idx    = $this->getSearchSpecial()->getValue();
            $ssConf = $mapping[$idx] ?? -1;
            if ($ssConf !== -1) {
                $_SESSION['Usersortierung'] = $ssConf;
            }
        }
        // explicitly set by user
        if ($gpcSort > 0 && $gpcSort !== \SEARCH_SORT_STANDARD) {
            $_SESSION['Usersortierung']         = $gpcSort;
            $_SESSION['UsersortierungVorSuche'] = $_SESSION['Usersortierung'];
            $_SESSION['nUsersortierungWahl']    = 1;
        }
        $this->sorting->setActiveSortingType($_SESSION['Usersortierung']);

        return $this;
    }

    /**
     * @return array<int, int>
     */
    public function getSearchSpecialConfigMapping(): array
    {
        $config = $this->getFilterConfig()->getConfig('suchspecials');

        return [
            \SEARCHSPECIALS_BESTSELLER       => (int)$config['suchspecials_sortierung_bestseller'],
            \SEARCHSPECIALS_SPECIALOFFERS    => (int)$config['suchspecials_sortierung_sonderangebote'],
            \SEARCHSPECIALS_NEWPRODUCTS      => (int)$config['suchspecials_sortierung_neuimsortiment'],
            \SEARCHSPECIALS_TOPOFFERS        => (int)$config['suchspecials_sortierung_topangebote'],
            \SEARCHSPECIALS_UPCOMINGPRODUCTS => (int)$config['suchspecials_sortierung_inkuerzeverfuegbar'],
            \SEARCHSPECIALS_TOPREVIEWS       => (int)$config['suchspecials_sortierung_topbewertet'],
        ];
    }

    /**
     * get list of product IDs matching the current filter
     *
     * @return Collection<int, int>
     */
    public function getProductKeys(): Collection
    {
        $sorting = $this->getSorting()->getActiveSorting();
        $sql     = (new StateSQL())->from($this->getCurrentStateData());
        $sql->addJoin($sorting->getJoin());
        $sql->setSelect(['tartikel.kArtikel']);
        $sql->setOrderBy($sorting->getOrderBy());
        $sql->setLimit('');
        $sql->setGroupBy(['tartikel.kArtikel']);
        $productKeys       = $this->db->getCollection($this->getFilterSQL()->getBaseQuery($sql, 'listing'))
            ->map(fn(stdClass $e): int => (int)$e->kArtikel);
        $orderData         = new stdClass();
        $orderData->cJoin  = $sorting->getJoin()->getSQL();
        $orderData->cOrder = $sorting->getOrderBy();

        \executeHook(\HOOK_FILTER_INC_GIBARTIKELKEYS, [
            'oArtikelKey_arr'            => &$productKeys,
            'FilterSQL'                  => new stdClass(),
            'NaviFilter'                 => $this,
            'SortierungsSQL'             => &$orderData,
            'bExtendedJTLSearch'         => $this->bExtendedJTLSearch,
            'oExtendedJTLSearchResponse' => $this->oExtendedJTLSearchResponse
        ]);

        return $productKeys;
    }

    /**
     * checks if a given combination of filter class and filter value is currently active
     * @param class-string $class
     */
    public function filterOptionIsActive(string $class, mixed $value): bool
    {
        foreach ($this->getActiveFilters() as $filter) {
            if ($filter->getClassName() !== $class) {
                continue;
            }
            $filterValue = $filter->getValue();
            if ($value === $filterValue) {
                return true;
            }
            if (\is_array($filterValue) && \in_array($value, $filterValue, true)) {
                return true;
            }
        }

        return false;
    }

    public function generateSearchResults(
        ?Kategorie $category = null,
        bool $fill = true,
        ?int $limit = null
    ): SearchResultsInterface {
        if (
            (int)$this->filterConfig->getConfig('global')['global_sichtbarkeit'] === 3
            && Frontend::getCustomer()->getID() === 0
        ) {
            $this->searchResults = new SearchResults();

            return $this->searchResults;
        }
        $error = false;
        if ($this->searchResults === null) {
            $this->searchResults = new SearchResults();
            $productList         = new Collection();
            $productKeys         = $this->getProductKeys();
            if (!empty($this->search->getName())) {
                if ($this->searchQuery->getError() === null) {
                    $this->search->saveQuery(
                        $productKeys->count(),
                        $this->search->getName(),
                        !$this->bExtendedJTLSearch
                    );
                    $this->search->setQueryID(
                        $this->search->getName(),
                        $this->getFilterConfig()->getLanguageID()
                    );
                    $this->searchQuery->setValue($this->search->getValue())
                        ->setSeo($this->getFilterConfig()->getLanguages());
                } else {
                    $error = $this->searchQuery->getError();
                }
            }
            $this->searchResults->setFilterOptions($this, $category)
                ->setSearchTerm($this->search->getName())
                ->setSearchTermWrite($this->metaData->getHeader());
        } else {
            $productList = $this->searchResults->getProducts();
            $productKeys = $this->searchResults->getProductKeys();
        }
        if (\is_string($error)) {
            return $this->showErrorPage($productList, $error);
        }
        if ($fill === true) { // @todo: slice list of IDs when not filling?
            $this->fillProducts($limit, $productKeys, $productList);
        }
        $this->url                             = $this->filterURL->createUnsetFilterURLs($this->url);
        $_SESSION['oArtikelUebersichtKey_arr'] = $productKeys;
        $this->searchResults->setProducts($productList);
        $this->updateSearchResultsMeta($limit, $productKeys);

        return $this->searchResults;
    }

    /**
     * @param int|null             $limit
     * @param Collection<int, int> $productKeys
     */
    protected function updateSearchResultsMeta(?int $limit, Collection $productKeys): void
    {
        if ($this->searchResults === null) {
            return;
        }
        $maxPaginationPageCount = (int)$this->getFilterConfig()->getConfig('artikeluebersicht')
        ['artikeluebersicht_max_seitenzahl'];
        $productCount           = $productKeys->count();
        $productsPerPage        = $limit ?? $this->limits->getProductsPerPageLimit();
        $nLimitN                = $productsPerPage * ($this->nSeite - 1);
        $end                    = \min($nLimitN + $productsPerPage, $productCount);
        $this->searchResults->setOffsetStart($nLimitN + 1)
            ->setOffsetEnd($end > 0 ? $end : $productCount);
        $total   = $productsPerPage > 0 ? (int)\ceil($productCount / $productsPerPage) : \min($productCount, 1);
        $minPage = (int)\max($this->nSeite - \floor($maxPaginationPageCount / 2), 1);
        $maxPage = $minPage + $maxPaginationPageCount - 1;
        if ($maxPage > $total) {
            $diff    = $total - $maxPage;
            $maxPage = $total;
            $minPage += $diff;
            $minPage = (int)\max($minPage, 1);
        }
        $pages = new Info();
        $pages->setMinPage($minPage);
        $pages->setMaxPage($maxPage);
        $pages->setTotalPages($total);
        $pages->setCurrentPage($this->nSeite);

        $this->searchResults->setProductCount($productCount)
            ->setProductKeys($productKeys)
            ->setPages($pages);
    }

    /**
     * @param int|null                 $limit
     * @param Collection<int, int>     $productKeys
     * @param Collection<int, Artikel> $productList
     */
    protected function fillProducts(?int $limit, Collection $productKeys, Collection $productList): void
    {
        $opt                        = Artikel::getDefaultOptions();
        $opt->nKategorie            = 1;
        $opt->nVariationen          = 1;
        $opt->nWarenlager           = 1;
        $opt->nRatings              = \PRODUCT_LIST_SHOW_RATINGS === true ? 1 : 0;
        $opt->nVariationDetailPreis = (int)$this->getFilterConfig()->getConfig('artikeldetails')
        ['artikel_variationspreisanzeige'] !== 0
            ? 1
            : 0;
        $productsPerPage            = $limit ?? $this->limits->getProductsPerPageLimit();
        if ($productsPerPage < 0) {
            $productsPerPage = null;
        }
        $currency      = Frontend::getCurrency();
        $customerGroup = Frontend::getCustomerGroup();
        $languageID    = Shop::getLanguageID();
        foreach ($productKeys->forPage($this->nSeite, $productsPerPage) as $idx => $id) {
            $product = (new Artikel($this->db, $customerGroup, $currency, $this->cache))
                ->fuelleArtikel($id, $opt, $customerGroup->getID(), $languageID);
            if ($product !== null && $product->getID() > 0) {
                $productList->push($product);
            } else {
                $productKeys->forget($idx);
            }
        }
        $productList = $productList->filter();
        $this->searchResults->setVisibleProductCount($productList->count());
    }

    /**
     * @param Collection<int, Artikel> $productList
     */
    protected function showErrorPage(Collection $productList, string $error): SearchResultsInterface
    {
        $pages = new Info();
        $pages->setMinPage(0);
        $pages->setMaxPage(0);
        $pages->setTotalPages(0);
        $pages->setCurrentPage(0);

        return $this->searchResults
            ->setPages($pages)
            ->setProductCount(0)
            ->setVisibleProductCount(0)
            ->setProducts($productList)
            ->setSearchUnsuccessful(true)
            ->setSearchTerm(\strip_tags(\trim($this->params['cSuche'])))
            ->setError($error);
    }

    /**
     * @param bool        $byType
     * @param string|null $ignore
     * @return array|FilterInterface[]
     */
    public function getActiveFilters(bool $byType = false, ?string $ignore = null): array
    {
        $activeFilters = select($this->activeFilters, static function (FilterInterface $f) use ($ignore): bool {
            return $ignore === null || $f->getClassName() !== $ignore;
        });
        if ($byType === false) {
            return $activeFilters;
        }
        $grouped = group($activeFilters, static function (FilterInterface $f) {
            if ($f->isCustom()) {
                return 'custom';
            }

            return $f->isInitialized() && ($param = $f->getUrlParam()) !== ''
                ? $param
                : 'misc';
        });

        return \array_merge(
            [
                'kf'     => [],
                'hf'     => [],
                'mm'     => [],
                'ssf'    => [],
                'tf'     => [],
                'sf'     => [],
                'bf'     => [],
                'custom' => [],
                'misc'   => []
            ],
            map($grouped, fn($e) => \array_values($e))
        );
    }

    public function getCurrentStateData(?string $ignore = null): StateSQLInterface
    {
        $state          = $this->getBaseState();
        $stateCondition = $state->getSQLCondition();
        $stateJoin      = $state->getSQLJoin();
        $data           = new StateSQL();
        $data->setGroupBy([]);
        $data->setOrderBy('');
        $data->setLimit('');
        $data->setSelect([]);
        $having     = [];
        $conditions = [];
        $joins      = \is_array($stateJoin) ? $stateJoin : [$stateJoin];
        if (!empty($stateCondition)) {
            $conditions[] = $stateCondition;
        }
        foreach ($this->getActiveFilters(true, $ignore) as $type => $active) {
            /** @var FilterInterface[] $active */
            if ($type !== 'misc' && $type !== 'custom' && \count($active) > 1) {
                $orFilters = select($active, fn(FilterInterface $f): bool => $f->getType() === Type::OR);
                foreach ($active as $filter) {
                    /** @var AbstractFilter $filter */
                    // the built-in filter behave quite strangely and have to be combined this way
                    $joins[] = $filter->getSQLJoin();
                    if (!\in_array($filter, $orFilters, true)) {
                        $conditions[] = $filter->getSQLCondition();
                    }
                }
                $conditions = $this->extractConditionsFromORFilters($orFilters, $conditions);
            } else {
                // this is the most clean and usual behaviour.
                // 'misc' and custom contain clean new filters that can be calculated by just iterating over the array
                foreach ($active as $filter) {
                    $joins[]   = $filter->getSQLJoin();
                    $condition = $filter->getSQLCondition();
                    if (!empty($condition)) {
                        $conditions[] = "\n" . $condition;
                    }
                }
            }
        }
        $data->setConditions($conditions);
        $data->setHaving($having);
        $data->setJoins(flatten($joins));

        return $data;
    }

    /**
     * @param FilterInterface[] $filters
     * @param string[]          $conditions
     * @return string[]
     */
    private function extractConditionsFromORFilters(array $filters, array $conditions): array
    {
        $groupedOrFilters = group($filters, static function (FilterInterface $f) {
            return $f->getClassName() === Characteristic::class
                ? $f->getID()
                : $f->getPrimaryKeyRow();
        });
        foreach ($groupedOrFilters as $orFilters) {
            /** @var FilterInterface[] $orFilters */
            $values        = \implode(
                ',',
                \array_map(static function ($f) {
                    $val = $f->getValue();

                    return \is_array($val) ? \implode(',', $val) : $val;
                }, $orFilters)
            );
            $first         = first($orFilters);
            $primaryKeyRow = $first->getPrimaryKeyRow();
            $table         = $first->getTableAlias();
            if (empty($table)) {
                $table = first($orFilters)->getTableName();
            }
            $conditions[] = "\n" . $table . '.kArtikel IN ' .
                '(SELECT kArtikel 
                    FROM ' . $first->getTableName() . ' 
                    WHERE ' . $primaryKeyRow . ' IN (' . $values . '))';
        }

        return $conditions;
    }

    /**
     * @param int[]|numeric-string[] $filters
     * @return int[]
     */
    public static function initCharacteristicFilter(array $filters = []): array
    {
        $filter = [];
        if (\count($filters) > 1) {
            foreach ($filters as $nFilter) {
                if ((int)$nFilter > 0) {
                    $filter[] = (int)$nFilter;
                }
            }
        } elseif (isset($_GET[\QUERY_PARAM_CHARACTERISTIC_FILTER])) {
            if (\is_string($_GET[\QUERY_PARAM_CHARACTERISTIC_FILTER])) {
                $filter[] = (int)$_GET[\QUERY_PARAM_CHARACTERISTIC_FILTER];
            } else {
                foreach ($_GET[\QUERY_PARAM_CHARACTERISTIC_FILTER] as $value) {
                    $filter[] = (int)$value;
                }
            }
        } elseif (isset($_POST[\QUERY_PARAM_CHARACTERISTIC_FILTER])) {
            if (\is_string($_POST[\QUERY_PARAM_CHARACTERISTIC_FILTER])) {
                $filter[] = (int)$_POST[\QUERY_PARAM_CHARACTERISTIC_FILTER];
            } else {
                foreach ($_POST[\QUERY_PARAM_CHARACTERISTIC_FILTER] as $value) {
                    $filter[] = (int)$value;
                }
            }
        } elseif (isset($_SERVER['REQUEST_METHOD'])) {
            if ($_SERVER['REQUEST_METHOD'] === 'GET' && \count($_GET) > 0) {
                foreach ($_GET as $key => $value) {
                    if (\preg_match('/mf\d+/i', (string)$key)) {
                        $filter[] = (int)$value;
                    }
                }
            } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && \count($_POST) > 0) {
                foreach ($_POST as $key => $value) {
                    if (\preg_match('/mf\d+/i', (string)$key)) {
                        $filter[] = (int)$value;
                    }
                }
            }
        }

        return $filter;
    }

    /**
     * @param string[] $filters
     * @return int[]
     */
    public static function initSearchFilter(array $filters = []): array
    {
        $filter    = [];
        $requested = $_GET[\QUERY_PARAM_SEARCH_FILTER] ?? $_POST[\QUERY_PARAM_SEARCH_FILTER] ?? null;
        if (\count($filters) > 1) {
            foreach ($filters as $nFilter) {
                if ((int)$nFilter > 0) {
                    $filter[] = (int)$nFilter;
                }
            }
        } elseif ($requested !== null) {
            if (\is_string($requested)) {
                $filter[] = (int)$requested;
            } else {
                foreach ($requested as $value) {
                    $filter[] = (int)$value;
                }
            }
        } else {
            $i = 1;
            while ($i < 20) {
                if (Request::verifyGPCDataInt(\QUERY_PARAM_SEARCH_FILTER . $i) > 0) {
                    $filter[] = Request::verifyGPCDataInt(\QUERY_PARAM_SEARCH_FILTER . $i);
                }
                ++$i;
            }
        }

        return $filter;
    }

    /**
     * @param int[]|numeric-string[] $filters
     * @return int[]
     */
    public static function initCategoryFilter(array $filters = []): array
    {
        $filter    = [];
        $requested = $_GET[\QUERY_PARAM_CATEGORY_FILTER] ?? $_POST[\QUERY_PARAM_CATEGORY_FILTER] ?? null;
        if (\count($filters) > 1) {
            foreach ($filters as $value) {
                if ((int)$value > 0) {
                    $filter[] = (int)$value;
                }
            }
        } elseif ($requested !== null) {
            if (\is_string($requested)) {
                $filter[] = (int)$requested;
            } else {
                foreach ($requested as $value) {
                    $filter[] = (int)$value;
                }
            }
        } else {
            $i = 1;
            while ($i < 20) {
                if (Request::verifyGPCDataInt(\QUERY_PARAM_CATEGORY_FILTER . $i) > 0) {
                    $filter[] = Request::verifyGPCDataInt(\QUERY_PARAM_CATEGORY_FILTER . $i);
                }
                ++$i;
            }
        }

        return $filter;
    }

    public function getDB(): DbInterface
    {
        return $this->db;
    }

    public function setDB(DbInterface $db): void
    {
        $this->db = $db;
    }

    public function getCache(): JTLCacheInterface
    {
        return $this->cache;
    }

    public function setCache(JTLCacheInterface $cache): void
    {
        $this->cache = $cache;
    }

    public function getFilterConfig(): ConfigInterface
    {
        return $this->filterConfig;
    }

    public function setFilterConfig(ConfigInterface $filterConfig): void
    {
        $this->filterConfig = $filterConfig;
    }

    public function isExtendedJTLSearch(): bool
    {
        return $this->bExtendedJTLSearch;
    }

    public function setExtendedJTLSearch(bool $isSearch): void
    {
        $this->bExtendedJTLSearch = $isSearch;
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
        if (\is_a($result, FilterInterface::class)) {
            /** @var FilterInterface $result */
            return $result->isInitialized();
        }

        return \is_array($result) && \count($result) > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $res         = \get_object_vars($this);
        $res['conf'] = '*truncated*';

        return $res;
    }
}
