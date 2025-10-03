<?php

declare(strict_types=1);

namespace JTL\Filter\SortingOptions;

use JTL\Filter\Join;
use JTL\Filter\MultiJoin;
use JTL\Filter\ProductFilter;
use JTL\Shop;

/**
 * Class PriceASC
 * @package JTL\Filter\SortingOptions
 */
class PriceASC extends AbstractSortingOption
{
    public function __construct(ProductFilter $productFilter)
    {
        parent::__construct($productFilter);
        $this->setOrderBy('tpreisdetail.fVKNetto, tartikel.cName');
        $this->join = (new MultiJoin())->addJoin(
            (new Join())
                ->setType('JOIN')
                ->setTable('tpreisdetail')
                ->setOn('tpreisdetail.kPreis = tpreis.kPreis AND tpreisdetail.nAnzahlAb = 0')
        )
            ->setType('JOIN')
            ->setTable('tpreis')
            ->setOn(
                'tartikel.kArtikel = tpreis.kArtikel
                    AND tpreis.kKundengruppe = ' . $productFilter->getFilterConfig()->getCustomerGroupID()
            );
        $this->setName(Shop::Lang()->get('sortPriceAsc'));
        $this->setPriority($this->getConfig('artikeluebersicht')['suche_sortierprio_preis']);
        $this->setValue(\SEARCH_SORT_PRICE_ASC);
    }
}
