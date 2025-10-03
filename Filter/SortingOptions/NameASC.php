<?php

declare(strict_types=1);

namespace JTL\Filter\SortingOptions;

use JTL\Filter\Join;
use JTL\Filter\ProductFilter;
use JTL\Language\LanguageHelper;
use JTL\Shop;

/**
 * Class NameASC
 * @package JTL\Filter\SortingOptions
 */
class NameASC extends AbstractSortingOption
{
    public function __construct(ProductFilter $productFilter)
    {
        parent::__construct($productFilter);
        $this->setName(Shop::Lang()->get('sortNameAsc'));
        $this->setPriority($this->getConfig('artikeluebersicht')['suche_sortierprio_name']);
        $this->setValue(\SEARCH_SORT_NAME_ASC);
        if (LanguageHelper::isDefaultLanguageActive(languageID: $this->getLanguageID())) {
            $this->setOrderBy('tartikel.cName');
        } else {
            $join = new Join();
            $join->setType('LEFT JOIN');
            $join->setTable('tartikelsprache tass');
            $join->setOn('tass.kArtikel = tartikel.kArtikel');
            $this->setJoin($join);
            $this->setOrderBy('COALESCE(tass.cName, tartikel.cName)');
        }
    }
}
