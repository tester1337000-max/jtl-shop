<?php

declare(strict_types=1);

namespace JTL\Filter\SortingOptions;

use JTL\Filter\ProductFilter;
use JTL\Shop;

/**
 * Class EAN
 * @package JTL\Filter\SortingOptions
 */
class EAN extends AbstractSortingOption
{
    public function __construct(ProductFilter $productFilter)
    {
        parent::__construct($productFilter);
        $this->setOrderBy('tartikel.cBarcode, tartikel.cName');
        $this->setName(Shop::Lang()->get('sortEan'));
        $this->setPriority($this->getConfig('artikeluebersicht')['suche_sortierprio_ean']);
        $this->setValue(\SEARCH_SORT_EAN);
    }
}
