<?php

declare(strict_types=1);

namespace JTL\Filter\SortingOptions;

use JTL\Filter\ProductFilter;
use JTL\Shop;

/**
 * Class ProductNumber
 * @package JTL\Filter\SortingOptions
 */
class ProductNumber extends AbstractSortingOption
{
    public function __construct(ProductFilter $productFilter)
    {
        parent::__construct($productFilter);
        $this->setOrderBy('tartikel.cArtNr, tartikel.cName');
        $this->setName(Shop::Lang()->get('sortProductno'));
        $this->setPriority($this->getConfig('artikeluebersicht')['suche_sortierprio_artikelnummer']);
        $this->setValue(\SEARCH_SORT_PRODUCTNO);
    }
}
