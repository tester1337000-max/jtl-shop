<?php

declare(strict_types=1);

namespace JTL\Filter\SortingOptions;

use JTL\Filter\ProductFilter;
use JTL\Shop;

/**
 * Class Bestseller
 * @package JTL\Filter\SortingOptions
 */
class Bestseller extends AbstractSortingOption
{
    public function __construct(ProductFilter $productFilter)
    {
        parent::__construct($productFilter);
        $this->setOrderBy('tbestseller.isBestseller DESC, tbestseller.fAnzahl DESC, tartikel.cName');
        $this->join->setType('LEFT JOIN')
            ->setTable('tbestseller')
            ->setOn('tartikel.kArtikel = tbestseller.kArtikel');
        $this->setName(Shop::Lang()->get('bestseller'));
        $this->setPriority($this->getConfig('artikeluebersicht')['suche_sortierprio_bestseller']);
        $this->setValue(\SEARCH_SORT_BESTSELLER);
    }
}
