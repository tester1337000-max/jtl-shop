<?php

declare(strict_types=1);

namespace JTL\Filter\SortingOptions;

use JTL\Filter\ProductFilter;
use JTL\Shop;

/**
 * Class DateCreated
 * @package JTL\Filter\SortingOptions
 */
class DateCreated extends AbstractSortingOption
{
    public function __construct(ProductFilter $productFilter)
    {
        parent::__construct($productFilter);
        $this->setOrderBy('tartikel.dErstellt DESC, tartikel.cName');
        $this->setName(Shop::Lang()->get('sortNewestFirst'));
        $this->setPriority($this->getConfig('artikeluebersicht')['suche_sortierprio_erstelldatum']);
        $this->setValue(\SEARCH_SORT_NEWEST_FIRST);
    }
}
