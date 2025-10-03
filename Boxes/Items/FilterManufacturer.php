<?php

declare(strict_types=1);

namespace JTL\Boxes\Items;

use JTL\Filter\Visibility;
use JTL\Shop;

/**
 * Class FilterManufacturer
 * @package JTL\Boxes\Items
 */
final class FilterManufacturer extends AbstractBox
{
    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $filter        = Shop::getProductFilter()->getManufacturerFilter();
        $searchResults = Shop::getProductFilter()->getSearchResults();
        $show          = $filter->getVisibility() !== Visibility::SHOW_NEVER
            && $filter->getVisibility() !== Visibility::SHOW_CONTENT
            && (!empty($searchResults->getManufacturerFilterOptions()) || $filter->isInitialized());
        $this->setShow($show);
        $this->setItems($filter);
    }
}
