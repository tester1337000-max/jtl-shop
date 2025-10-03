<?php

declare(strict_types=1);

namespace JTL\Filter\SortingOptions;

use JTL\Filter\ProductFilter;
use JTL\Shop;

/**
 * Class PriceDESC
 * @package JTL\Filter\SortingOptions
 */
class PriceDESC extends PriceASC
{
    public function __construct(ProductFilter $productFilter)
    {
        parent::__construct($productFilter);
        $this->setOrderBy('tpreisdetail.fVKNetto DESC, tartikel.cName');
        $this->setName(Shop::Lang()->get('sortPriceDesc'));
        $this->setPriority($this->getConfig('artikeluebersicht')['suche_sortierprio_preis_ab']);
        $this->setValue(\SEARCH_SORT_PRICE_DESC);
    }
}
