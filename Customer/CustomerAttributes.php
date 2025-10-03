<?php

declare(strict_types=1);

namespace JTL\Customer;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JTL\Shop;
use Traversable;

/**
 * Class CustomerAttributes
 * @package JTL\Customer
 * @phpstan-implements IteratorAggregate<int, CustomerAttribute>
 * @phpstan-implements ArrayAccess<int, CustomerAttribute>
 */
class CustomerAttributes implements ArrayAccess, IteratorAggregate, Countable
{
    /**
     * @var CustomerAttribute[]
     */
    private array $attributes = [];

    private int $customerID = 0;

    public function __construct(int $customerID = 0)
    {
        if ($customerID > 0) {
            $this->load($customerID);
        } else {
            $this->create();
        }
    }

    public function load(int $customerID): self
    {
        $this->attributes = [];
        $this->customerID = $customerID;

        foreach (
            Shop::Container()->getDB()->getObjects(
                'SELECT tkundenattribut.kKundenAttribut, COALESCE(tkundenattribut.kKunde, :customerID) kKunde,
                    tkundenfeld.kKundenfeld, tkundenfeld.cName, tkundenfeld.cWawi, tkundenattribut.cWert,
                    tkundenfeld.nSort,
                    IF(tkundenattribut.kKundenAttribut IS NULL
                        OR COALESCE(tkundenattribut.cWert, \'\') = \'\', 1, tkundenfeld.nEditierbar) nEditierbar
                FROM tkundenfeld
                LEFT JOIN tkundenattribut ON tkundenattribut.kKunde = :customerID
                    AND tkundenattribut.kKundenfeld = tkundenfeld.kKundenfeld
                WHERE tkundenfeld.kSprache = :langID
                ORDER BY tkundenfeld.nSort, tkundenfeld.cName',
                [
                    'customerID' => $customerID,
                    'langID'     => Shop::getLanguageID(),
                ]
            ) as $customerAttribute
        ) {
            $this->attributes[(int)$customerAttribute->kKundenfeld] = new CustomerAttribute($customerAttribute);
        }

        return $this;
    }

    public function save(): self
    {
        $nonEditables = (new CustomerFields())->getNonEditableFields();
        $usedIDs      = [];
        /** @var CustomerAttribute $attribute */
        foreach ($this as $attribute) {
            if ($attribute->isEditable()) {
                $attribute->save();
                $usedIDs[] = $attribute->getID();
            } else {
                $this->attributes[$attribute->getCustomerFieldID()] = CustomerAttribute::load($attribute->getID());
            }
        }

        Shop::Container()->getDB()->queryPrepared(
            'DELETE FROM tkundenattribut
                WHERE kKunde = :customerID' . (\count($nonEditables) > 0
                ? ' AND kKundenfeld NOT IN (' . \implode(', ', $nonEditables) . ')' : '') . (\count($usedIDs) > 0
                ? ' AND kKundenAttribut NOT IN (' . \implode(', ', $usedIDs) . ')' : ''),
            [
                'customerID' => $this->customerID,
            ]
        );

        return $this;
    }

    public function assign(CustomerAttributes $customerAttributes): self
    {
        $this->attributes = [];
        /** @var CustomerAttribute $customerAttribute */
        foreach ($customerAttributes as $customerAttribute) {
            $record = $customerAttribute->getRecord();

            $this->attributes[$record->kKundenfeld] = new CustomerAttribute($record);
        }

        return $this->sort();
    }

    public function create(): self
    {
        $this->attributes = [];
        $customerFields   = new CustomerFields();

        /** @var CustomerField $customerField */
        foreach ($customerFields as $customerField) {
            $attribute = new CustomerAttribute();
            $attribute->setName($customerField->getName());
            $attribute->setCustomerFieldID($customerField->getID());
            $attribute->setEditable(true);
            $attribute->setLabel($customerField->getLabel());
            $attribute->setOrder($customerField->getOrder());

            $this->attributes[$customerField->getID()] = $attribute;
        }

        return $this->sort();
    }

    public function getCustomerID(): int
    {
        return $this->customerID;
    }

    public function setCustomerID(int $customerID): void
    {
        $this->customerID = $customerID;

        foreach ($this->attributes as $attribute) {
            $attribute->setCustomerID($customerID);
        }
    }

    public function sort(): self
    {
        \uasort($this->attributes, static function (CustomerAttribute $lft, CustomerAttribute $rgt): int {
            if ($lft->getOrder() === $rgt->getOrder()) {
                return \strcmp($lft->getName(), $rgt->getName());
            }

            return $lft->getOrder() < $rgt->getOrder() ? -1 : 1;
        });

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->attributes);
    }

    /**
     * @inheritdoc
     */
    public function offsetExists($offset): bool
    {
        return \array_key_exists($offset, $this->attributes);
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->attributes[$offset] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function offsetSet($offset, $value): void
    {
        if (\is_a($value, CustomerAttribute::class)) {
            $this->attributes[$offset] = $value;
        } elseif (\is_a($value, \stdClass::class)) {
            $this->attributes[$offset] = new CustomerAttribute($value);
        } else {
            throw new \InvalidArgumentException(
                self::class . '::' . __METHOD__ . ' - value must be an object, ' . \gettype($value) . ' given.'
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function offsetUnset($offset): void
    {
        unset($this->attributes[$offset]);
    }

    public function count(): int
    {
        return \count($this->attributes);
    }
}
