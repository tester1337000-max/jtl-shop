<?php

declare(strict_types=1);

namespace JTL\Filter\SortingOptions;

use JTL\Filter\Join;
use JTL\Filter\ProductFilter;
use JTL\Language\LanguageHelper;
use JTL\Shop;

/**
 * Class NameDESC
 * @package JTL\Filter\SortingOptions
 */
class NameDESC extends AbstractSortingOption
{
    public function __construct(ProductFilter $productFilter)
    {
        parent::__construct($productFilter);
        $this->setName(Shop::Lang()->get('sortNameDesc'));
        $this->setPriority($this->getConfig('artikeluebersicht')['suche_sortierprio_name_ab']);
        $this->setValue(\SEARCH_SORT_NAME_DESC);
        if (LanguageHelper::isDefaultLanguageActive(languageID: $this->getLanguageID())) {
            $this->setOrderBy('tartikel.cName DESC');
        } else {
            $join = new Join();
            $join->setType('LEFT JOIN');
            $join->setTable('tartikelsprache tass');
            $join->setOn('tass.kArtikel = tartikel.kArtikel');
            $this->setJoin($join);
            $this->setOrderBy('COALESCE(tass.cName, tartikel.cName) DESC');
        }
    }
}
