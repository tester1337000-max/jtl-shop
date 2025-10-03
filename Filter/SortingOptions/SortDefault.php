<?php

declare(strict_types=1);

namespace JTL\Filter\SortingOptions;

use JTL\Filter\ProductFilter;
use JTL\Shop;

/**
 * Class SortDefault
 * @package JTL\Filter\SortingOptions
 */
class SortDefault extends AbstractSortingOption
{
    public function __construct(ProductFilter $productFilter)
    {
        parent::__construct($productFilter);
        $this->setOrderBy('tartikel.nSort, tartikel.cName');
        if ($productFilter->getCategory()->getValue() > 0) {
            $this->orderBy = 'tartikel.nSort, tartikel.cName';
        } elseif (
            isset($_SESSION['Usersortierung'])
            && $_SESSION['Usersortierung'] === \SEARCH_SORT_STANDARD
            && $productFilter->getSearch()->getSearchCacheID() > 0
        ) {
            $this->setOrderBy('jSuche.nSort, tartikel.nSort, tartikel.cName');
        }
        $this->setName(Shop::Lang()->get('standard'));
        $this->setValue(\SEARCH_SORT_STANDARD);
        $this->setPriority(10);
    }
}
