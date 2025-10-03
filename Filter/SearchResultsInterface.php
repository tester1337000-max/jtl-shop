<?php

declare(strict_types=1);

namespace JTL\Filter;

use Illuminate\Support\Collection;
use JTL\Catalog\Category\Kategorie;
use JTL\Catalog\Product\Artikel;
use JTL\Filter\Pagination\Info;
use stdClass;

/**
 * Interface SearchResultsInterface
 * @package JTL\Filter
 */
interface SearchResultsInterface
{
    /**
     * @param SearchResultsInterface|stdClass $legacy
     * @return SearchResultsInterface
     */
    public function convert(SearchResultsInterface|stdClass $legacy): SearchResultsInterface;

    /**
     * @return stdClass
     */
    public function getProductsCompat(): stdClass;

    /**
     * @return SearchResultsInterface
     */
    public function setProductsCompat(): SearchResultsInterface;

    /**
     * @return Collection<int, int>
     */
    public function getProductKeys(): Collection;

    /**
     * @param Collection<int, int> $keys
     * @return SearchResultsInterface
     */
    public function setProductKeys(Collection $keys): SearchResultsInterface;

    /**
     * @return Collection<int, Artikel>
     */
    public function getProducts(): Collection;

    /**
     * @param Collection<int, Artikel> $products
     * @return SearchResultsInterface
     */
    public function setProducts(Collection $products): SearchResultsInterface;

    /**
     * @return int
     */
    public function getProductCount(): int;

    /**
     * @param int $productCount
     * @return SearchResultsInterface
     */
    public function setProductCount(int $productCount): SearchResultsInterface;

    /**
     * @return int
     */
    public function getVisibleProductCount(): int;

    /**
     * @param int $count
     * @return SearchResultsInterface
     */
    public function setVisibleProductCount(int $count): SearchResultsInterface;

    /**
     * @return int
     */
    public function getOffsetStart(): int;

    /**
     * @param int $offsetStart
     * @return $this
     */
    public function setOffsetStart(int $offsetStart): SearchResultsInterface;

    /**
     * @return int
     */
    public function getOffsetEnd(): int;

    /**
     * @param int $offsetEnd
     * @return $this
     */
    public function setOffsetEnd(int $offsetEnd): SearchResultsInterface;

    /**
     * @return Info
     */
    public function getPages(): Info;

    /**
     * @param Info $pages
     * @return $this
     */
    public function setPages(Info $pages): SearchResultsInterface;

    /**
     * @return string|null
     */
    public function getSearchTerm(): ?string;

    /**
     * @param string|null $searchTerm
     * @return $this
     */
    public function setSearchTerm(?string $searchTerm): SearchResultsInterface;

    /**
     * @return string|null
     */
    public function getSearchTermWrite(): ?string;

    /**
     * @param string|null $searchTerm
     * @return $this
     */
    public function setSearchTermWrite(?string $searchTerm): SearchResultsInterface;

    /**
     * @return bool
     */
    public function getSearchUnsuccessful(): bool;

    /**
     * @param bool $searchUnsuccessful
     * @return $this
     */
    public function setSearchUnsuccessful(bool $searchUnsuccessful): SearchResultsInterface;

    /**
     * @return Option[]
     */
    public function getManufacturerFilterOptions(): array;

    /**
     * @param Option[] $options
     * @return $this
     */
    public function setManufacturerFilterOptions(array $options): SearchResultsInterface;

    /**
     * @return Option[]
     */
    public function getRatingFilterOptions(): array;

    /**
     * @param Option[] $options
     * @return $this
     */
    public function setRatingFilterOptions(array $options): SearchResultsInterface;

    /**
     * @return Option[]
     */
    public function getCharacteristicFilterOptions(): array;

    /**
     * @param Option[] $options
     * @return $this
     */
    public function setCharacteristicFilterOptions(array $options): SearchResultsInterface;

    /**
     * @return Option[]
     */
    public function getPriceRangeFilterOptions(): array;

    /**
     * @param Option[] $options
     * @return $this
     */
    public function setPriceRangeFilterOptions(array $options): SearchResultsInterface;

    /**
     * @return Option[]
     */
    public function getCategoryFilterOptions(): array;

    /**
     * @param Option[] $options
     * @return $this
     */
    public function setCategoryFilterOptions(array $options): SearchResultsInterface;

    /**
     * @return Option[]
     */
    public function getSearchFilterOptions(): array;

    /**
     * @param Option[] $options
     * @return $this
     */
    public function setSearchFilterOptions(array $options): SearchResultsInterface;

    /**
     * @return Option[]
     */
    public function getSearchSpecialFilterOptions(): array;

    /**
     * @param Option[] $options
     * @return $this
     */
    public function setSearchSpecialFilterOptions(array $options): SearchResultsInterface;

    /**
     * @return Option[]
     */
    public function getAvailabilityFilterOptions(): array;

    /**
     * @param Option[] $options
     * @return $this
     */
    public function setAvailabilityFilterOptions(array $options): SearchResultsInterface;

    /**
     * @return Option[]
     */
    public function getCustomFilterOptions(): array;

    /**
     * @param Option[] $options
     * @return $this
     */
    public function setCustomFilterOptions(array $options): SearchResultsInterface;

    /**
     * @return string|null
     */
    public function getSearchFilterJSON(): ?string;

    /**
     * @param string|null $json
     * @return $this
     */
    public function setSearchFilterJSON(?string $json): SearchResultsInterface;

    /**
     * @return string|null
     */
    public function getError(): ?string;

    /**
     * @param string|null $error
     * @return $this
     */
    public function setError(?string $error): SearchResultsInterface;

    /**
     * @return Option[]
     */
    public function getSortingOptions(): array;

    /**
     * @param Option[] $options
     * @return $this
     */
    public function setSortingOptions(array $options): SearchResultsInterface;

    /**
     * @return Option[]
     */
    public function getLimitOptions(): array;

    /**
     * @param Option[] $options
     * @return $this
     */
    public function setLimitOptions(array $options): SearchResultsInterface;

    /**
     * @return array<string, Option[]>
     */
    public function getAllFilterOptions(): array;

    /**
     * @param ProductFilter  $productFilter
     * @param Kategorie|null $currentCategory
     * @param bool           $selectionWizard
     * @return $this
     */
    public function setFilterOptions(
        ProductFilter $productFilter,
        ?Kategorie $currentCategory = null,
        bool $selectionWizard = false
    ): SearchResultsInterface;
}
