<?php

declare(strict_types=1);

namespace JTL\Filter;

use JTL\Filter\Items\Category;
use JTL\Filter\Items\Characteristic;
use JTL\Filter\Items\Manufacturer;
use JTL\Filter\Items\PriceRange;
use JTL\Filter\Items\Rating;
use JTL\Filter\Items\Search;
use JTL\Filter\Items\SearchSpecial;
use JTL\Filter\States\BaseSearchQuery;
use JTL\Helpers\URL;
use JTL\Language\LanguageModel;
use JTL\Session\Frontend;
use JTL\Shop;
use stdClass;

use function Functional\first;

/**
 * Class ProductFilterURL
 * @package JTL\Filter
 */
readonly class ProductFilterURL
{
    public function __construct(private ProductFilter $productFilter)
    {
    }

    /**
     * @param stdClass|FilterInterface|null $extraFilter
     * @param bool                          $canonical
     * @param array<mixed>                  $additional
     * @return string
     */
    public function getURL(
        FilterInterface|stdClass|null $extraFilter = null,
        bool $canonical = false,
        array $additional = []
    ): string {
        $isSearchQuery      = false;
        $productFilter      = $this->productFilter;
        $filterConfig       = $productFilter->getFilterConfig();
        $languageID         = $filterConfig->getLanguageID();
        $extraFilter        = $this->convertExtraFilter($extraFilter);
        $base               = $productFilter->getBaseState();
        $nonSeoFilterParams = [];
        $seoFilterParams    = [];

        $urlParams = [
            'kf'     => [],
            'hf'     => [],
            'mm'     => [],
            'ssf'    => [],
            'tf'     => [],
            'sf'     => [],
            'bf'     => [],
            'custom' => [],
            'misc'   => []
        ];

        $baseUrl = new URL($filterConfig->getBaseURL());
        $baseUrl->setPath('');
        $baseUrl = $baseUrl->normalize() . '/';
        if ($base->isInitialized()) {
            $filterSeoUrl = \count($additional) > 0
                ? $base->getRoute($additional)
                : null;
            $filterSeoUrl = $filterSeoUrl ?? $base->getSeo($languageID);
            if (!empty($filterSeoUrl)) {
                $seoParam          = new stdClass();
                $seoParam->value   = $base->getValue();
                $seoParam->sep     = '';
                $seoParam->param   = '';
                $seoParam->seo     = $filterSeoUrl;
                $seoFilterParams[] = $seoParam;
            } else {
                $isSearchQuery                            = \get_class($base) === BaseSearchQuery::class;
                $filterValue                              = $isSearchQuery
                    ? $base->getName()
                    : $base->getValue();
                $nonSeoFilterParams[$base->getUrlParam()] = $filterValue;
                if (($path = \parse_url(\URL_SHOP, \PHP_URL_PATH)) !== null && $path !== false && $path !== '/') {
                    $seoFilterParams[] = (object)[
                        'value' => 0,
                        'sep'   => '',
                        'param' => '',
                        'seo'   => \ltrim($path, '/')
                    ];
                }
            }
        }
        if ($canonical === true) {
            return $baseUrl . $this->buildURLString($seoFilterParams, $nonSeoFilterParams);
        }
        $active = $productFilter->getActiveFilters();
        // we need the base state + all active filters + optionally the additional filter to generate the correct url
        if ($extraFilter !== null && !$extraFilter->getDoUnset()) {
            $active[] = $extraFilter;
        }
        $ignore      = null;
        $ignoreValue = null;
        // remove extra filters from url array if getDoUnset equals true
        if ($extraFilter !== null && $extraFilter->getDoUnset() === true) {
            $ignore      = $extraFilter->getUrlParam();
            $ignoreValue = $extraFilter->getValue();
        }
        // split arrays of filters into single instances
        foreach ($active as $i => $filter) {
            $filterValue = $filter->getValue();
            if (\is_array($filterValue)) {
                unset($active[$i]);
                foreach ($filterValue as $singleValue) {
                    $class    = $filter->getClassName();
                    $instance = new $class($productFilter);
                    $instance->init($singleValue);
                    $active[] = $instance;
                }
            }
        }
        // add all filter urls to an array indexed by the filter's url param
        /** @var FilterInterface $filter */
        foreach ($active as $filter) {
            $urlParam    = $filter->getUrlParam();
            $filterValue = $filter->getValue();
            if (\is_array($filterValue)) {
                $filterValue = first($filterValue);
            }
            if ($ignore !== null && $urlParam === $ignore) {
                if ($ignoreValue === null || $ignoreValue === $filterValue) {
                    // unset filter was given for this whole filter or this current value
                    continue;
                }
                if (\is_array($filterValue) && \in_array($ignoreValue, $filterValue, true)) {
                    // ignored value was found in array of values
                    $idx = \array_search($ignoreValue, $filterValue, true);
                    unset($filterValue[$idx]);
                }
            }
            if (!isset($urlParams[$urlParam])) {
                $urlParams[$urlParam] = [];
            }

            if (isset($urlParams[$urlParam][0]->value) && \is_array($urlParams[$urlParam][0]->value)) {
                $added      = true;
                $valueToAdd = \is_array($filterValue) ? $filterValue : [$filterValue];
                foreach ($valueToAdd as $v) {
                    if (\in_array($v, $urlParams[$urlParam][0]->value, true)) {
                        $added = false;
                    } else {
                        $urlParams[$urlParam][0]->value[] = $v;
                    }
                }
                if ($added === true) {
                    if (!\is_array($urlParams[$urlParam][0]->seo)) {
                        $urlParams[$urlParam][0]->seo = [];
                    }
                    $urlParams[$urlParam][0]->seo[] = $filter->getSeo($languageID);
                }
            } else {
                $createEntry = true;
                foreach ($urlParams[$urlParam] as $i => $filterSeoData) {
                    // when adding a value that already is active, we acutally create an unset url
                    if ($filterSeoData->value === $filterValue) {
                        $createEntry = false;
                        unset($urlParams[$urlParam][$i]);
                    }
                }
                if ($createEntry === true) {
                    $filterSeoData        = new stdClass();
                    $filterSeoData->value = $filterValue;
                    $filterSeoData->sep   = $filter->getUrlParamSEO();
                    $filterSeoData->seo   = $filter->getSeo($languageID);
                    $filterSeoData->param = $urlParam;

                    $urlParams[$urlParam][] = $filterSeoData;

                    $activeValues = $filter->getActiveValues();
                    if (\is_array($activeValues) && \count($activeValues) > 0) {
                        $filterSeoData->value = [];
                        $filterSeoData->seo   = [];
                        foreach ($activeValues as $activeValue) {
                            $val = $activeValue->getValue();
                            if (
                                $ignore === null
                                || $ignore !== $urlParam
                                || $ignoreValue === 0
                                || $ignoreValue !== $val
                            ) {
                                $filterSeoData->value[] = $activeValue->getValue();
                                $filterSeoData->seo[]   = $activeValue->getURL();
                            }
                        }
                    }
                }
            }
        }
        if ($extraFilter !== null && $extraFilter->isParamExclusive() === true) {
            // some filters (like rating filter) must only have one exclusive param (?bf=1 etc.) - no array of params
            foreach ($urlParams as $param => $value) {
                if (\is_array($value) && $param === $extraFilter->getUrlParam()) {
                    foreach ($value as $index => $val) {
                        if (isset($val->value) && $val->value !== $extraFilter->getValue()) {
                            unset($urlParams[$param][$index]);
                        }
                    }
                }
            }
        }
        foreach ($urlParams as $filterID => $filters) {
            foreach ($filters as $f) {
                if (!$isSearchQuery && !empty($f->seo) && !empty($f->sep)) {
                    $seoFilterParams[] = $f;
                } elseif (!isset($nonSeoFilterParams[$filterID])) {
                    $nonSeoFilterParams[$filterID] = $f->value;
                } elseif (!\is_array($nonSeoFilterParams[$filterID])) {
                    $nonSeoFilterParams[$filterID]   = [$nonSeoFilterParams[$filterID]];
                    $nonSeoFilterParams[$filterID][] = $f->value;
                } else {
                    $nonSeoFilterParams[$filterID][] = $f->value;
                }
            }
        }
        if (empty($seoFilterParams) && $languageID !== Shop::getLanguageID()) {
            $language = first(Frontend::getLanguages(), fn(LanguageModel $l): bool => $l->getId() === $languageID);
            if ($language !== null) {
                $nonSeoFilterParams['lang'] = $language->getCode();
            }
        }

        return $baseUrl . $this->buildURLString($seoFilterParams, $this->collapse($nonSeoFilterParams));
    }

    /**
     * @param array<mixed> $params
     * @return array<mixed>
     */
    private function collapse(array $params): array
    {
        foreach ($params as $param => $value) {
            if (\is_array($value) && \count($value) === 1) {
                $params[$param] = first($value);
            }
        }

        return $params;
    }

    /**
     * @param stdClass[]   $seoParts
     * @param array<mixed> $nonSeoParts
     * @return string
     */
    private function buildURLString(array $seoParts, array $nonSeoParts): string
    {
        $url = '';
        foreach ($seoParts as $seoData) {
            $url .= $seoData->sep . (\is_array($seoData->seo)
                    ? \implode($seoData->sep, $seoData->seo)
                    : $seoData->seo);
        }
        $nonSeoPart = \http_build_query($nonSeoParts);
        if ($nonSeoPart !== '') {
            $url .= '?' . $nonSeoPart;
        }

        // remove numeric indices from array representation
        return \preg_replace('/%5B\d+%5D/imU', '%5B%5D', $url) ?? $url;
    }

    /**
     * URLs generieren, die Filter lösen
     *
     * @param NavigationURLsInterface     $url
     * @param SearchResultsInterface|null $searchResults
     * @return NavigationURLsInterface
     */
    public function createUnsetFilterURLs(
        NavigationURLsInterface $url,
        ?SearchResultsInterface $searchResults = null
    ): NavigationURLsInterface {
        if ($searchResults === null) {
            $searchResults = $this->productFilter->getSearchResults();
        }
        $extraFilter   = (new Category($this->productFilter))->init(null)->setDoUnset(true);
        $categoriesURL = $this->getURL($extraFilter);
        $url->setCategories($categoriesURL);
        $categoryFilter       = $this->productFilter->getCategoryFilter();
        $categoryFilterValues = $categoryFilter->getValue();
        if (\is_array($categoryFilterValues)) {
            $urls             = [];
            $additionalFilter = (new Category($this->productFilter))->setDoUnset(true);
            foreach ($categoryFilterValues as $value) {
                $additionalFilter->init($value)->setValue($value);
                $urls[$value] = $this->getURL($additionalFilter);
            }
            $categoryFilter->setUnsetFilterURL($urls);
        } else {
            $categoryFilter->setUnsetFilterURL($categoriesURL);
        }

        $extraFilter      = (new Manufacturer($this->productFilter))->init(null)->setDoUnset(true);
        $manufacturersURL = $this->getURL($extraFilter);
        $url->setManufacturers($manufacturersURL);
        $manufacturerFilter       = $this->productFilter->getManufacturerFilter();
        $manufacturerFilterValues = $manufacturerFilter->getValue();
        if (\is_array($manufacturerFilterValues)) {
            $urls             = [];
            $additionalFilter = (new Manufacturer($this->productFilter))->setDoUnset(true);
            foreach ($manufacturerFilterValues as $value) {
                $additionalFilter->init($value)->setValue($value);
                $urls[$value] = $this->getURL($additionalFilter);
            }
            $manufacturerFilter->setUnsetFilterURL($urls);
        } else {
            $manufacturerFilter->setUnsetFilterURL($manufacturersURL);
        }

        $additionalFilter = (new Characteristic($this->productFilter))->setDoUnset(true);
        foreach ((array)$this->productFilter->getCharacteristicFilter() as $filter) {
            if ($filter->getID() > 0) {
                $url->addCharacteristic(
                    $filter->getID(),
                    $this->getURL(
                        $additionalFilter->init($filter->getID())
                            ->setSeo($this->productFilter->getFilterConfig()->getLanguages())
                    )
                );
                $filter->setUnsetFilterURL($url->getCharacteristics());
            }
            if (\is_array($filter->getValue())) {
                $urls = [];
                foreach ($filter->getValue() as $charVal) {
                    $additionalFilter->init($charVal)->setValue($charVal);
                    $charURL = $this->getURL($additionalFilter);
                    $url->addCharacteristicValue($charVal, $charURL);
                    $urls[$charVal] = $charURL;
                }
                $filter->setUnsetFilterURL($urls);
            } else {
                $url->addCharacteristicValue(
                    $filter->getValue(),
                    $this->getURL($additionalFilter->init($filter->getValue()))
                );
                $filter->setUnsetFilterURL($url->getCharacteristicValues());
            }
        }
        // kinda hacky: try to build url that removes a merkmalwert url from merkmalfilter url
        if (
            $this->productFilter->getCharacteristicValue()->isInitialized()
            && !isset($url->getCharacteristicValues()[$this->productFilter->getCharacteristicValue()->getValue()])
        ) {
            // the url should be <shop>/<merkmalwert-url>__<merkmalfilter>[__<merkmalfilter>]
            $charValSeo = \str_replace(
                $this->productFilter->getCharacteristicValue()
                    ->getSeo($this->productFilter->getFilterConfig()->getLanguageID()) . \SEP_MERKMAL,
                '',
                $url->getCategories()
            );
            if ($charValSeo !== $url->getCategories()) {
                $url->addCharacteristicValue($this->productFilter->getCharacteristicValue()->getValue(), $charValSeo);
                $this->productFilter->getCharacteristicValue()->setUnsetFilterURL($charValSeo);
            }
        }
        $extraFilter   = (new PriceRange($this->productFilter))->setDoUnset(true);
        $priceRangeURL = $this->getURL($extraFilter);
        $url->setPriceRanges($priceRangeURL);
        $this->productFilter->getPriceRangeFilter()->setUnsetFilterURL($priceRangeURL);

        $extraFilter = (new Rating($this->productFilter))->init(null)->setDoUnset(true);
        $ratingURL   = $this->getURL($extraFilter);
        $url->setRatings($ratingURL);
        $this->productFilter->getRatingFilter()->setUnsetFilterURL($ratingURL);

        $extraFilter       = (new SearchSpecial($this->productFilter))->init(null)->setDoUnset(true);
        $searchSpecialsURL = $this->getURL($extraFilter);
        $url->setSearchSpecials($searchSpecialsURL);
        $searchSpecialFilter       = $this->productFilter->getSearchSpecialFilter();
        $searchSpecialFilterValues = $searchSpecialFilter->getValue();
        if (\is_array($searchSpecialFilterValues)) {
            $urls             = [];
            $additionalFilter = (new SearchSpecial($this->productFilter))->setDoUnset(true);
            foreach ($searchSpecialFilterValues as $value) {
                $additionalFilter->init($value);
                $urls[$value] = $this->getURL($additionalFilter);
            }
            $searchSpecialFilter->setUnsetFilterURL($urls);
        } else {
            $searchSpecialFilter->setUnsetFilterURL($searchSpecialsURL);
        }

        $extraFilter = (new Search($this->productFilter))->init(null)->setDoUnset(true);
        foreach ((array)$this->productFilter->getSearchFilter() as $searchFilter) {
            if (($value = $searchFilter->getValue()) > 0) {
                $_url = $this->getURL($extraFilter);
                $url->addSearchFilter($value, $_url);
                $searchFilter->setUnsetFilterURL($_url);
            }
        }

        foreach (
            \array_filter(
                $this->productFilter->getAvailableFilters(),
                static fn(FilterInterface $f): bool => $f->isInitialized() && $f->isCustom()
            ) as $filter
        ) {
            /** @var FilterInterface $filter */
            $extraFilter = clone $filter;
            $urls        = [];
            $extraFilter->setDoUnset(true);
            if ($filter->getType() === Type::OR) {
                foreach ((array)$filter->getValue() as $filterValue) {
                    $extraFilter->setValue($filterValue);
                    $urls[$filterValue] = $this->getURL($extraFilter);
                }
            } else {
                $extraFilter->setValue($filter->getValue());
                $urls = $this->getURL($extraFilter);
            }
            $filter->setUnsetFilterURL($urls);
        }
        // Filter reset
        $pages  = $searchResults->getPages();
        $cSeite = $pages->getCurrentPage() > 1
            ? \SEP_SEITE . $pages->getCurrentPage()
            : '';

        $url->setUnsetAll($this->getURL(null, true) . $cSeite);

        return $url;
    }

    /**
     * converts legacy stdClass filters to real filter instances
     */
    private function convertExtraFilter(FilterInterface|stdClass|null $extraFilter = null): ?FilterInterface
    {
        if ($extraFilter === null || !\is_a($extraFilter, stdClass::class)) {
            return $extraFilter;
        }
        if (
            isset($extraFilter->KategorieFilter->kKategorie)
            || (isset($extraFilter->FilterLoesen->Kategorie) && $extraFilter->FilterLoesen->Kategorie === true)
        ) {
            $filter = (new Category($this->productFilter))
                ->init($extraFilter->KategorieFilter->kKategorie ?? null);
        } elseif (
            isset($extraFilter->HerstellerFilter->kHersteller)
            || (isset($extraFilter->FilterLoesen->Hersteller) && $extraFilter->FilterLoesen->Hersteller === true)
        ) {
            $filter = (new Manufacturer($this->productFilter))
                ->init($extraFilter->HerstellerFilter->kHersteller ?? null);
        } elseif (
            isset($extraFilter->MerkmalFilter->kMerkmalWert)
            || isset($extraFilter->FilterLoesen->MerkmalWert)
        ) {
            $filter = (new Characteristic($this->productFilter))
                ->init($extraFilter->MerkmalFilter->kMerkmalWert ?? $extraFilter->FilterLoesen->MerkmalWert);
        } elseif (isset($extraFilter->FilterLoesen->Merkmale)) {
            $filter = (new Characteristic($this->productFilter))->init($extraFilter->FilterLoesen->Merkmale);
        } elseif (
            isset($extraFilter->PreisspannenFilter->fVon)
            || (isset($extraFilter->FilterLoesen->Preisspannen) && $extraFilter->FilterLoesen->Preisspannen === true)
        ) {
            $filter = (new PriceRange($this->productFilter))->init(
                isset($extraFilter->PreisspannenFilter->fVon, $extraFilter->PreisspannenFilter->fBis)
                    ? ($extraFilter->PreisspannenFilter->fVon . '_' . $extraFilter->PreisspannenFilter->fBis)
                    : null
            );
        } elseif (
            isset($extraFilter->BewertungFilter->nSterne)
            || (isset($extraFilter->FilterLoesen->Bewertungen) && $extraFilter->FilterLoesen->Bewertungen === true)
        ) {
            $filter = (new Rating($this->productFilter))
                ->init($extraFilter->BewertungFilter->nSterne ?? null);
        } elseif (
            isset($extraFilter->SuchspecialFilter->kKey)
            || (isset($extraFilter->FilterLoesen->Suchspecials) && $extraFilter->FilterLoesen->Suchspecials === true)
        ) {
            $filter = (new SearchSpecial($this->productFilter))
                ->init($extraFilter->SuchspecialFilter->kKey ?? null);
        } elseif (
            isset($extraFilter->searchFilter->kSuchanfrage)
            || !empty($extraFilter->FilterLoesen->searchFilter)
        ) {
            $filter = (new BaseSearchQuery($this->productFilter))
                ->init($extraFilter->searchFilter->kSuchanfrage ?? null);
        } elseif (isset($extraFilter->FilterLoesen->searchFilter)) {
            $filter = (new BaseSearchQuery($this->productFilter))->init($extraFilter->FilterLoesen->searchFilter);
        } else {
            throw new \InvalidArgumentException(
                'Unrecognized additional unset filter: '
                . \json_encode($extraFilter, \JSON_THROW_ON_ERROR)
            );
        }

        return $filter->setDoUnset(isset($extraFilter->FilterLoesen));
    }
}
