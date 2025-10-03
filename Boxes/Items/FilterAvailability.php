<?php

declare(strict_types=1);

namespace JTL\Boxes\Items;

use JTL\Filter\Visibility;
use JTL\Shop;

/**
 * Class FilterAvailability
 * @package JTL\Boxes\Items
 */
final class FilterAvailability extends AbstractBox
{
    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $filter        = Shop::getProductFilter()->getAvailabilityFilter();
        $searchResults = Shop::getProductFilter()->getSearchResults();
        $show          = $filter->getVisibility() !== Visibility::SHOW_NEVER
            && $filter->getVisibility() !== Visibility::SHOW_CONTENT
            && (!empty($searchResults->getAvailabilityFilterOptions()) || $filter->isInitialized());
        $this->setShow($show);
        $this->setTitle($filter->getFrontendName());
        $this->setItems($filter);
    }
}
