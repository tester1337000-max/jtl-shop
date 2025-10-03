<?php

declare(strict_types=1);

namespace JTL\Filter\SortingOptions;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use JTL\Filter\ProductFilter;
use JTL\Mapper\SortingType;
use JTL\Plugin\PluginInterface;

/**
 * Class Factory
 * @package JTL\Filter\SortingOptions
 */
class Factory
{
    /**
     * @var int[]
     */
    private static array $defaultSortingOptions = [
        \SEARCH_SORT_STANDARD,
        \SEARCH_SORT_NAME_ASC,
        \SEARCH_SORT_NAME_DESC,
        \SEARCH_SORT_PRICE_ASC,
        \SEARCH_SORT_PRICE_DESC,
        \SEARCH_SORT_EAN,
        \SEARCH_SORT_NEWEST_FIRST,
        \SEARCH_SORT_PRODUCTNO,
        \SEARCH_SORT_WEIGHT,
        \SEARCH_SORT_DATEOFISSUE,
        \SEARCH_SORT_BESTSELLER,
        \SEARCH_SORT_RATING,
    ];

    /**
     * @var array<string, class-string>
     */
    private array $mapping = [];

    /**
     * @var PluginInterface[]
     */
    private array $plugins = [];

    public function __construct(private readonly ProductFilter $productFilter)
    {
        \executeHook(\HOOK_PRODUCTFILTER_REGISTER_SEARCH_OPTION, [
            'factory'       => $this,
            'productFilter' => $this->productFilter
        ]);
    }

    /**
     * @param class-string<SortingOptionInterface> $className
     */
    public function registerSortingOption(int $value, string $className, ?PluginInterface $plugin = null): void
    {
        $this->mapping[$value] = $className;
        $this->plugins[$value] = $plugin;
    }

    /**
     * @return Collection<int, SortingOptionInterface>
     */
    public function getAll(): Collection
    {
        $all = new Collection();
        foreach (self::$defaultSortingOptions as $defaultSortingOption) {
            $option = $this->getSortingOption($defaultSortingOption);
            if ($option !== null) {
                $all->push($option);
            }
        }
        foreach ($this->mapping as $id => $class) {
            $all->push(new $class($this->productFilter, $this->plugins[$id]));
        }

        return $all->filter(fn(SortingOptionInterface $option): bool => $option->getPriority() !== 0);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getSortingOption(int $type): ?SortingOptionInterface
    {
        $mapper  = new SortingType();
        $mapping = $mapper->mapSortTypeToClassName($type);
        if ($mapping === null) {
            $mapping = $this->mapping[$type] ?? null;
        }
        if ($mapping === null) {
            if ($type !== \SEARCH_SORT_STANDARD) {
                return $this->getSortingOption(\SEARCH_SORT_STANDARD);
            }
            throw new InvalidArgumentException('Cannot map type ' . $type);
        }

        return new $mapping($this->productFilter, $this->plugins[$type] ?? null);
    }
}
