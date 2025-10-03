<?php

declare(strict_types=1);

namespace JTL\Boxes\Items;

use JTL\Filter\Visibility;
use JTL\Shop;

/**
 * Class FilterPricerange
 * @package JTL\Boxes\Items
 */
final class FilterPricerange extends AbstractBox
{
    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $pf            = Shop::getProductFilter();
        $filter        = $pf->getPriceRangeFilter();
        $searchResults = $pf->getSearchResults();
        $tplConfig     = (($config['template']['productlist']['always_show_price_range'] ?? 'N') === 'Y');
        $show          = ($tplConfig && $pf->isExtendedJTLSearch() === false)
            || ($filter->getVisibility() !== Visibility::SHOW_NEVER
                && $filter->getVisibility() !== Visibility::SHOW_CONTENT
                && (!empty($searchResults->getPriceRangeFilterOptions()) || $filter->isInitialized()));
        $this->setShow($show);
        $this->setTitle($filter->getFrontendName());
        $this->setItems($filter);
    }
}
