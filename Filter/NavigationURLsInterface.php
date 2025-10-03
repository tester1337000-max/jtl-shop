<?php

declare(strict_types=1);

namespace JTL\Filter;

/**
 * Interface NavigationURLsInterface
 * @package JTL\Filter
 */
interface NavigationURLsInterface
{
    /**
     * @return string
     */
    public function getPriceRanges(): string;

    /**
     * @param string $priceRanges
     * @return NavigationURLsInterface
     */
    public function setPriceRanges(string $priceRanges): NavigationURLsInterface;

    /**
     * @return string
     */
    public function getRatings(): string;

    /**
     * @param string $ratings
     * @return NavigationURLsInterface
     */
    public function setRatings(string $ratings): NavigationURLsInterface;

    /**
     * @return string
     */
    public function getSearchSpecials(): string;

    /**
     * @param string $searchSpecials
     * @return NavigationURLsInterface
     */
    public function setSearchSpecials(string $searchSpecials): NavigationURLsInterface;

    /**
     * @return string
     */
    public function getCategories(): string;

    /**
     * @param string $categories
     * @return NavigationURLsInterface
     */
    public function setCategories(string $categories): NavigationURLsInterface;

    /**
     * @return string
     */
    public function getManufacturers(): string;

    /**
     * @param string $manufacturers
     * @return NavigationURLsInterface
     */
    public function setManufacturers(string $manufacturers): NavigationURLsInterface;

    /**
     * @param int    $idx
     * @param string $manufacturer
     * @return NavigationURLsInterface
     */
    public function addManufacturer(int $idx, string $manufacturer): NavigationURLsInterface;

    /**
     * @return array<int, string>
     */
    public function getCharacteristics(): array;

    /**
     * @param array<int, string> $characteristics
     * @return NavigationURLsInterface
     */
    public function setCharacteristics(array $characteristics): NavigationURLsInterface;

    /**
     * @param int    $idx
     * @param string $characteristic
     * @return NavigationURLsInterface
     */
    public function addCharacteristic(int $idx, string $characteristic): NavigationURLsInterface;

    /**
     * @return array<int, string>
     */
    public function getCharacteristicValues(): array;

    /**
     * @param array<int, string> $value
     * @return NavigationURLsInterface
     */
    public function setCharacteristicValues(array $value): NavigationURLsInterface;

    /**
     * @param int|string $idx
     * @param string     $value
     * @return NavigationURLsInterface
     */
    public function addCharacteristicValue(int|string $idx, string $value): NavigationURLsInterface;

    /**
     * @return array<int, string>
     */
    public function getSearchFilters(): array;

    /**
     * @param array<int, string> $searchFilters
     * @return NavigationURLsInterface
     */
    public function setSearchFilters(array $searchFilters): NavigationURLsInterface;

    /**
     * @param int    $idx
     * @param string $searchFilter
     * @return NavigationURLsInterface
     */
    public function addSearchFilter(int $idx, string $searchFilter): NavigationURLsInterface;

    /**
     * @return string
     */
    public function getUnsetAll(): string;

    /**
     * @param string $unsetAll
     * @return NavigationURLsInterface
     */
    public function setUnsetAll(string $unsetAll): NavigationURLsInterface;
}
