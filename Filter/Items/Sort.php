<?php

declare(strict_types=1);

namespace JTL\Filter\Items;

use Illuminate\Support\Collection;
use JTL\Filter\AbstractFilter;
use JTL\Filter\FilterInterface;
use JTL\Filter\Option;
use JTL\Filter\ProductFilter;
use JTL\Filter\SortingOptions\Factory;
use JTL\Filter\SortingOptions\SortingOptionInterface;
use JTL\Mapper\SortingType;
use JTL\Shop;

/**
 * Class Sort
 * @package JTL\Filter\Items
 */
class Sort extends AbstractFilter
{
    private ?Factory $factory = null;

    /**
     * @var Collection<int, SortingOptionInterface>
     */
    private Collection $sortingOptions;

    protected int $activeSortingType;

    public function __construct(ProductFilter $productFilter)
    {
        $this->sortingOptions = new Collection();
        parent::__construct($productFilter);
        $this->setIsCustom(false)
            ->setUrlParam('Sortierung')
            ->setFrontendName(Shop::Lang()->get('sorting', 'productOverview'))
            ->setFilterName($this->getFrontendName());
        $this->activeSortingType = (int)$this->getConfig('artikeluebersicht')['artikeluebersicht_artikelsortierung'];
        if (isset($_SESSION['Usersortierung'])) {
            $mapper                  = new SortingType();
            $this->activeSortingType = $mapper->mapUserSorting($_SESSION['Usersortierung']);
        }
        $_SESSION['Usersortierung'] = $this->activeSortingType;
        if ($_SESSION['Usersortierung'] === \SEARCH_SORT_STANDARD && $productFilter->getSort() > 0) {
            $this->activeSortingType = $productFilter->getSort();
        }
    }

    public function getActiveSorting(): SortingOptionInterface
    {
        if ($this->factory === null) {
            throw new \LogicException('Factory has to be set first.');
        }

        return $this->factory->getSortingOption($this->activeSortingType);
    }

    public function getFactory(): Factory
    {
        return $this->factory ?? throw new \LogicException('Factory has to be set first.');
    }

    public function setFactory(Factory $factory): void
    {
        $this->factory = $factory;
    }

    /**
     * @return Collection<int, SortingOptionInterface>
     */
    public function getSortingOptions(): Collection
    {
        return $this->sortingOptions;
    }

    /**
     * @param Collection<int, SortingOptionInterface> $sortingOptions
     */
    public function setSortingOptions(Collection $sortingOptions): void
    {
        $this->sortingOptions = $sortingOptions;
    }

    public function getActiveSortingType(): int
    {
        return $this->activeSortingType;
    }

    public function setActiveSortingType(int $activeSortingType): void
    {
        $this->activeSortingType = $activeSortingType;
    }

    /**
     * @throws \LogicException
     */
    public function registerSortingOptions(): void
    {
        if ($this->factory === null) {
            throw new \LogicException('Factory has to be set first.');
        }
        $this->sortingOptions = $this->factory->getAll()
            ->sortByDesc(fn(SortingOptionInterface $i): int => $i->getPriority());
    }

    /**
     * @inheritdoc
     */
    public function setSeo(array $languages): FilterInterface
    {
        return $this;
    }

    /**
     * @inheritdoc
     * @phpstan-return array{}
     */
    public function getSQLJoin(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getOptions($mixed = null): array
    {
        if ($this->options !== null) {
            return $this->options;
        }
        $options          = [];
        $additionalFilter = new self($this->getProductFilter());
        $activeSortType   = (int)($_SESSION['Usersortierung'] ?? -1);
        foreach ($this->sortingOptions as $i => $sortingOption) {
            $value = $sortingOption->getValue();
            $opt   = new Option();
            $opt->setIsActive($activeSortType === $value);
            $opt->setURL($this->getProductFilter()->getFilterURL()->getURL($additionalFilter->init($value)));
            $opt->setType($this->getType());
            $opt->setClassName($this->getClassName());
            $opt->setParam($this->getUrlParam());
            $opt->setName($sortingOption->getName());
            $opt->setValue($value);
            $opt->setSort($i);
            $options[] = $opt;
        }
        $this->options = $options;

        return $options;
    }
}
