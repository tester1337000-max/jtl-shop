<?php

declare(strict_types=1);

namespace JTL\Filter\SortingOptions;

use JTL\Filter\ProductFilter;

/**
 * Class None
 * @package JTL\Filter\SortingOptions
 */
class None extends AbstractSortingOption
{
    public function __construct(ProductFilter $productFilter)
    {
        parent::__construct($productFilter);
        $this->setOrderBy('');
        $this->setName('');
        $this->setPriority(-1);
        $this->setValue(\SEARCH_SORT_NONE);
    }
}
