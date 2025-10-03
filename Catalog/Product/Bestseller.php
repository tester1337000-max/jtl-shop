<?php

declare(strict_types=1);

namespace JTL\Catalog\Product;

use Illuminate\Support\Collection;
use JTL\Customer\CustomerGroup;
use JTL\Session\Frontend;
use JTL\Shop;

/**
 * Class Bestseller
 * @package JTL\Catalog\Product
 */
class Bestseller
{
    /**
     * @var Collection<int, int>
     */
    protected Collection $products;

    protected int $customergrp = 0;

    protected int $limit = 3;

    protected int $minsales = 10;

    /**
     * @param array<string, mixed>|null $options
     */
    public function __construct(?array $options = null)
    {
        $this->products = new Collection();
        if (\is_array($options)) {
            $this->setOptions($options);
        }
    }

    /**
     * @param array<mixed> $options
     * @return $this
     */
    public function setOptions(array $options): self
    {
        $methods = \get_class_methods($this);
        foreach ($options as $key => $value) {
            $method = 'set' . \ucfirst($key);
            if (\in_array($method, $methods, true) && \method_exists($this, $method)) {
                $this->$method($value);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, int>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    /**
     * @param Collection<int, int> $products
     */
    public function setProducts(Collection $products): self
    {
        $this->products = $products;

        return $this;
    }

    public function getCustomergroup(): int
    {
        return $this->customergrp;
    }

    public function setCustomergroup(int $customergroup): self
    {
        $this->customergrp = $customergroup;

        return $this;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function setLimit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    public function getMinSales(): int
    {
        return $this->minsales;
    }

    public function setMinSales(int $minsales): self
    {
        $this->minsales = $minsales;

        return $this;
    }

    /**
     * @return int[]
     */
    public function fetch(): array
    {
        $productsql = $this->getProducts()->isNotEmpty()
            ? ' AND tartikel.kArtikel IN (' . \implode(',', $this->getProducts()->toArray()) . ') '
            : '';
        $storagesql = Shop::getProductFilter()->getFilterSQL()->getStockFilterSQL();

        return Shop::Container()->getDB()->getInts(
            'SELECT tartikel.kArtikel
                FROM tartikel
                JOIN tbestseller
                    ON tbestseller.kArtikel = tartikel.kArtikel AND tbestseller.isBestseller = 1
                LEFT JOIN tartikelsichtbarkeit
                    ON tartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                    AND tartikelsichtbarkeit.kKundengruppe = :cgid
                WHERE tartikelsichtbarkeit.kArtikel IS NULL
                    AND ROUND(tbestseller.fAnzahl) >= :ms ' . $storagesql . $productsql . '
                GROUP BY tartikel.kArtikel
                ORDER BY tbestseller.fAnzahl DESC
                LIMIT :lmt',
            'kArtikel',
            ['cgid' => $this->customergrp, 'ms' => $this->minsales, 'lmt' => $this->limit]
        );
    }

    /**
     * @param int[]|Collection<int, int> $products
     * @return ($onlykeys is true ? int[] : Artikel[])
     */
    public static function buildBestsellers(
        Collection|array $products,
        int $customerGroupID,
        bool $viewallowed = true,
        bool $onlykeys = true,
        int $limit = 3,
        int $minsells = 10
    ): array {
        if (!$viewallowed || \count($products) === 0) {
            return [];
        }
        if (\is_array($products)) {
            $products = \collect($products);
        }
        $options    = [
            'Products'      => $products,
            'Customergroup' => $customerGroupID,
            'Limit'         => $limit,
            'MinSales'      => $minsells
        ];
        $bestseller = new self($options);
        if ($onlykeys) {
            return $bestseller->fetch();
        }
        $db             = Shop::Container()->getDB();
        $cache          = Shop::Container()->getCache();
        $bestsellers    = [];
        $defaultOptions = Artikel::getDefaultOptions();
        $languageID     = Shop::getLanguageID();
        $customerGroup  = CustomerGroup::getByID($customerGroupID);
        $currency       = Frontend::getCurrency();
        foreach ($bestseller->fetch() as $productID) {
            $product = new Artikel($db, $customerGroup, $currency, $cache);
            $product->fuelleArtikel($productID, $defaultOptions, $customerGroupID, $languageID);
            if ($product->kArtikel > 0) {
                $bestsellers[] = $product;
            }
        }

        return $bestsellers;
    }

    /**
     * @param array<mixed> $products
     * @param array<mixed> $bestsellers
     * @return int[]
     */
    public static function ignoreProducts(array &$products, array $bestsellers): array
    {
        $ignoredkeys = [];
        if (\count($products) === 0 || \count($bestsellers) === 0) {
            return $ignoredkeys;
        }
        foreach ($products as $i => $product) {
            if (\count($products) === 1) {
                break;
            }
            foreach ($bestsellers as $bestseller) {
                if ($product->kArtikel === $bestseller->kArtikel) {
                    unset($products[$i]);
                    $ignoredkeys[] = $bestseller->kArtikel;
                    break;
                }
            }
        }

        return $ignoredkeys;
    }
}
