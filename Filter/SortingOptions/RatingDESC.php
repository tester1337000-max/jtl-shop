<?php

declare(strict_types=1);

namespace JTL\Filter\SortingOptions;

use JTL\Filter\ProductFilter;
use JTL\Shop;

/**
 * Class RatingDESC
 * @package JTL\Filter\SortingOptions
 */
class RatingDESC extends AbstractSortingOption
{
    public function __construct(ProductFilter $productFilter)
    {
        parent::__construct($productFilter);
        $this->setOrderBy('tartikelext.fDurchschnittsBewertung DESC, tartikel.cName');
        $this->join->setType('LEFT JOIN')
            ->setTable('tartikelext')
            ->setOn('tartikelext.kArtikel = tartikel.kArtikel');
        $this->setName(Shop::Lang()->get('rating'));
        $this->setPriority($this->getConfig('artikeluebersicht')['suche_sortierprio_bewertung']);
        $this->setValue(\SEARCH_SORT_RATING);
    }
}
