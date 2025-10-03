<?php

declare(strict_types=1);

namespace JTL\Filter;

use JTL\MagicCompatibilityTrait;

/**
 * Class NavigationURLs
 * @package JTL\Filter
 */
class NavigationURLs implements NavigationURLsInterface
{
    use MagicCompatibilityTrait;

    /**
     * @var array<string, string>
     */
    public static array $mapping = [
        'cAllePreisspannen' => 'PriceRanges',
        'cAlleBewertungen'  => 'Ratings',
        'cAlleTags'         => 'Tags',
        'cAlleSuchspecials' => 'SearchSpecials',
        'cAlleKategorien'   => 'Categories',
        'cAlleHersteller'   => 'Manufacturers',
        'cAlleMerkmale'     => 'Characteristics',
        'cAlleMerkmalWerte' => 'CharacteristicValues',
        'cAlleSuchFilter'   => 'SearchFilters',
        'cNoFilter'         => 'UnsetAll'
    ];

    private string $priceRanges = '';

    private string $ratings = '';

    private string $tags = '';

    private string $searchSpecials = '';

    private string $categories = '';

    private string $manufacturers = '';

    /**
     * @var array<int, string>
     */
    private array $characteristics = [];

    /**
     * @var array<int, string>
     */
    private array $characteristicValues = [];

    /**
     * @var array<int, string>
     */
    private array $searchFilters = [];

    private string $unsetAll = '';

    /**
     * @inheritdoc
     */
    public function getPriceRanges(): string
    {
        return $this->priceRanges;
    }

    /**
     * @inheritdoc
     */
    public function setPriceRanges(string $priceRanges): NavigationURLsInterface
    {
        $this->priceRanges = $priceRanges;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getRatings(): string
    {
        return $this->ratings;
    }

    /**
     * @inheritdoc
     */
    public function setRatings(string $ratings): NavigationURLsInterface
    {
        $this->ratings = $ratings;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getSearchSpecials(): string
    {
        return $this->searchSpecials;
    }

    /**
     * @inheritdoc
     */
    public function setSearchSpecials(string $searchSpecials): NavigationURLsInterface
    {
        $this->searchSpecials = $searchSpecials;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getCategories(): string
    {
        return $this->categories;
    }

    /**
     * @inheritdoc
     */
    public function setCategories(string $categories): NavigationURLsInterface
    {
        $this->categories = $categories;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getManufacturers(): string
    {
        return $this->manufacturers;
    }

    /**
     * @inheritdoc
     */
    public function setManufacturers(string $manufacturers): NavigationURLsInterface
    {
        $this->manufacturers = $manufacturers;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function addManufacturer(int $idx, string $manufacturer): NavigationURLsInterface
    {
        $this->manufacturers[$idx] = $manufacturer;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getCharacteristics(): array
    {
        return $this->characteristics;
    }

    /**
     * @inheritdoc
     */
    public function setCharacteristics(array $characteristics): NavigationURLsInterface
    {
        $this->characteristics = $characteristics;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function addCharacteristic(int $idx, string $characteristic): NavigationURLsInterface
    {
        $this->characteristics[$idx] = $characteristic;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getCharacteristicValues(): array
    {
        return $this->characteristicValues;
    }

    /**
     * @inheritdoc
     */
    public function setCharacteristicValues(array $value): NavigationURLsInterface
    {
        $this->characteristicValues = $value;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function addCharacteristicValue(int|string $idx, string $value): NavigationURLsInterface
    {
        $this->characteristicValues[$idx] = $value;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getSearchFilters(): array
    {
        return $this->searchFilters;
    }

    /**
     * @inheritdoc
     */
    public function setSearchFilters(array $searchFilters): NavigationURLsInterface
    {
        $this->searchFilters = $searchFilters;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function addSearchFilter(int $idx, string $searchFilter): NavigationURLsInterface
    {
        $this->searchFilters[$idx] = $searchFilter;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getUnsetAll(): string
    {
        return $this->unsetAll;
    }

    /**
     * @inheritdoc
     */
    public function setUnsetAll(string $unsetAll): NavigationURLsInterface
    {
        $this->unsetAll = $unsetAll;

        return $this;
    }
}
