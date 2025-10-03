<?php

declare(strict_types=1);

namespace JTL\Filter\Pagination;

/**
 * Class ItemFactory
 * @package JTL\Filter\Pagination
 */
class ItemFactory
{
    /**
     * @return Item
     */
    public function create(): Item
    {
        return new Item();
    }
}
