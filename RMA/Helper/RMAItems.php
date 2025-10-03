<?php

declare(strict_types=1);

namespace JTL\RMA\Helper;

use Exception;
use JTL\RMA\DomainObjects\RMAItemDomainObject;
use ReturnTypeWillChange;

/**
 * @description Use a typed array to store RMAItemDomainObjects and provide some array helper methods
 * @comment This class is useful for providing code linting and auto-completion in IDEs
 */
class RMAItems extends \ArrayObject
{
    /**
     * @param RMAItemDomainObject[] $items
     * @throws Exception
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }

    /**
     * @return RMAItemDomainObject[]
     */
    public function getArray(): array
    {
        return $this->getArrayCopy();
    }

    /**
     * @return RMAItemDomainObject[]
     */
    public function uniqueArrayKeys(): array
    {
        $result = [];
        foreach ($this->getArray() as $item) {
            if (($item->shippingNotePosID ?? 0) > 0 && ($item->productID ?? 0) > 0) {
                $result[$item->shippingNotePosID . '_' . $item->productID] = $item;
            }
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    public function getItem(int $shippingNotePosID): ?RMAItemDomainObject
    {
        $result = null;
        if ($shippingNotePosID > 0) {
            foreach ($this->getArray() as $item) {
                if ($item->shippingNotePosID === $shippingNotePosID) {
                    $result = $item;
                }
            }
        }

        return $result;
    }

    /**
     * Appends the value
     *
     * @link https://php.net/manual/en/arrayobject.append.php
     * @param RMAItemDomainObject $value <p>
     * The value being appended.
     * </p>
     * @return RMAItemDomainObject
     * @comment Check if the value is an instance of RMAItemDomainObject before appending it.
     */
    #[ReturnTypeWillChange] public function append(mixed $value): RMAItemDomainObject
    {
        if ($value instanceof RMAItemDomainObject) {
            parent::append($value);
        }

        return $value;
    }
}
