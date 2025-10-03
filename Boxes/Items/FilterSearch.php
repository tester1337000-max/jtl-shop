<?php

declare(strict_types=1);

namespace JTL\Boxes\Items;

use JTL\Filter\Visibility;
use JTL\Shop;

/**
 * Class FilterSearch
 * @package JTL\Boxes\Items
 */
final class FilterSearch extends AbstractBox
{
    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $filter = Shop::getProductFilter()->searchFilterCompat;
        $show   = $filter->getVisibility() !== Visibility::SHOW_NEVER
            && $filter->getVisibility() !== Visibility::SHOW_CONTENT
            && \count($filter->getOptions()) > 0
            && empty(Shop::getProductFilter()->getSearch()->getValue());
        $this->setShow($show);
        $this->setTitle($filter->getFrontendName());
    }
}
