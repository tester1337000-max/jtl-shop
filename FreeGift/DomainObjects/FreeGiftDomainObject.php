<?php

declare(strict_types=1);

namespace JTL\FreeGift\DomainObjects;

use JTL\DataObjects\AbstractDomainObject;

/**
 * Class FreeGiftDomainObject
 * @package JTL\FreeGift
 * @description Data container for already bought gifts
 * @comment The public properties represent the database table columns
 */
class FreeGiftDomainObject extends AbstractDomainObject
{
    /**
     * @param array<'modifiedKeys', string[]> $modifiedKeys
     */
    public function __construct(
        public readonly int $id = 0,
        public readonly int $productID = 0,
        public readonly int $basketID = 0,
        public readonly int $quantity = 0,
        array $modifiedKeys = []
    ) {
        parent::__construct($modifiedKeys);
    }
}
