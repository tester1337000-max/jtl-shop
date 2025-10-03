<?php

declare(strict_types=1);

namespace JTL\FreeGift\DomainObjects;

use JTL\Catalog\Product\Artikel;
use JTL\DataObjects\AbstractDomainObject;

/**
 * Class FreeGiftProductDomainObject
 * @package JTL\FreeGift
 * @description Data container for products of type C_WARENKORBPOS_TYP_GRATISGESCHENK
 * @comment The public properties represent the database table columns
 */
class FreeGiftProductDomainObject extends AbstractDomainObject
{
    /**
     * @param array<'modifiedKeys', string[]> $modifiedKeys
     */
    public function __construct(
        public readonly int $productID = 0,
        public readonly float $availableFrom = 0.00,
        private readonly float $stillMissingAmount = 0.00,
        private readonly ?Artikel $product = null,
        array $modifiedKeys = []
    ) {
        parent::__construct($modifiedKeys);
    }

    public function getStillMissingAmount(): float
    {
        return $this->stillMissingAmount;
    }

    public function getProduct(): ?Artikel
    {
        return $this->product ?? null;
    }
}
