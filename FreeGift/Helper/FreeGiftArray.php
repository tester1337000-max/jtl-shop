<?php

declare(strict_types=1);

namespace JTL\FreeGift\Helper;

use Exception;
use JTL\FreeGift\DomainObjects\FreeGiftDomainObject;
use ReturnTypeWillChange;

/**
 * @description Use a typed array to store FreeGiftsDomainObjects and provide some array helper methods
 * @comment This class is useful for providing code linting and auto-completion in IDEs
 * @extends \ArrayObject<int, FreeGiftDomainObject>
 */
class FreeGiftArray extends \ArrayObject
{
    /**
     * @param FreeGiftDomainObject[] $items
     * @throws Exception
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }

    /**
     * @return FreeGiftDomainObject[]
     */
    public function getArray(): array
    {
        return $this->getArrayCopy();
    }

    /**
     * Appends the value
     *
     * @link https://php.net/manual/en/arrayobject.append.php
     * @param FreeGiftDomainObject $value <p>
     * The value being appended.
     * </p>
     * @return FreeGiftDomainObject
     * @comment Check if the value is an instance of FreeGiftDomainObject before appending it.
     */
    #[ReturnTypeWillChange] public function append(mixed $value): FreeGiftDomainObject
    {
        if ($value instanceof FreeGiftDomainObject) {
            parent::append($value);
        }

        return $value;
    }
}
