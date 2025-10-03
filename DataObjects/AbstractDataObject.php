<?php

declare(strict_types=1);

namespace JTL\DataObjects;

use ReflectionClass;
use ReflectionProperty;
use stdClass;

/**
 * Class AbstractDataObject
 * @package JTL\DataObjects
 */
abstract class AbstractDataObject implements DataObjectInterface
{
    /**
     * as long as our database provides german column identifiers it is recommended to provide
     * two mapping arrays:
     * private array $mapping: to map the identifiers used in the code to the identifiers inside the DTO
     *      $mapping = [
     *              <someIdentifier> => <DTOPropertyIdentifier>
     *                  ];
     * private array $columnMapping: to map the DTO property names  to the column identifiers in the database
     *      $columnMapping = [
     *              <columnIdentifier> => <DTOPropertyIdentifier>
     *                  ];
     *
     */

    /**
     * @inheritdoc
     */
    abstract public function getMapping(): array;

    /**
     * @inheritdoc
     */
    abstract public function getReverseMapping(): array;

    /**
     * @inheritdoc
     */
    public function __set(string $name, mixed $value): void
    {
        $map = $this->getMapping();

        if (isset($map[$name])) {
            $method = 'set' . \str_replace(' ', '', \ucwords(\str_replace('_', ' ', $map[$name])));
            $this->$method($value);
        }
    }

    /**
     * @inheritdoc
     */
    public function __get(string $name): mixed
    {
        $map = $this->getMapping();

        if (isset($map[$name])) {
            $prop = $map[$name];

            return $this->$prop;
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function __isset(string $name): bool
    {
        return isset($this->$name);
    }

    /**
     * @inheritdoc
     */
    public function __unset(string $name): void
    {
        unset($this->$name);
    }

    /**
     * @var array<string|int, bool>
     */
    private array $possibleBoolValues = [
        'true'  => true,
        'y'     => true,
        'yes'   => true,
        'ja'    => true,
        '1'     => true,
        'false' => false,
        'n'     => false,
        'no'    => false,
        'nein'  => false,
        '0'     => false,
    ];

    /**
     * @param ReflectionProperty $property
     * @param bool               $dismissNull
     * @param bool               $serialize
     * @param array<mixed>       $toArray
     * @param string             $propertyName
     * @return array<mixed>
     */
    public function fillArray(
        ReflectionProperty $property,
        bool $dismissNull,
        bool $serialize,
        array $toArray,
        string $propertyName
    ): array {
        if ($dismissNull === true && $property->getValue($this) === null) {
            return $toArray;
        }
        if (
            $serialize === true
            && (\is_array($property->getValue($this)) || \is_object($property->getValue($this)))
        ) {
            $toArray[$propertyName] = \serialize($property->getValue($this));
        } else {
            $toArray[$propertyName] = $property->getValue($this);
        }

        return $toArray;
    }

    protected function checkAndReturnBoolValue(bool|int|string $value = 0): bool
    {
        $value = \strtolower((string)$value);
        if (!\array_key_exists($value, $this->possibleBoolValues)) {
            return false;
        }

        return $this->possibleBoolValues[$value];
    }

    /**
     * @inheritdoc
     */
    public function hydrate(array $data): self
    {
        $attributeMap = $this->getMapping();
        foreach ($data as $attribute => $value) {
            if (\array_key_exists($attribute, $attributeMap)) {
                $attribute = $attributeMap[$attribute];
            }
            $method = 'set' . \str_replace(' ', '', \ucwords(\str_replace('_', ' ', $attribute)));
            if (\is_callable([$this, $method])) {
                $this->$method($value);
            }
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function hydrateWithObject(object $object): self
    {
        $attributeMap     = $this->getMapping();
        $objectAttributes = \get_object_vars($object);
        foreach ($objectAttributes as $name => $attribute) {
            $propertyName = $name;
            if (\array_key_exists($name, $attributeMap)) {
                $propertyName = $attributeMap[$name];
            }
            $setMethod = 'set' . \ucfirst($propertyName);
            if (\method_exists($this, $setMethod)) {
                $this->$setMethod($object->{$name});
            }
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function toArray(bool $tableColumns = true, bool $serialize = true, bool $dismissNull = false): array
    {
        $columnMap = [];
        if ($tableColumns === true && \method_exists($this, 'getColumnMapping')) {
            $columnMap = $this->getColumnMapping();
        }
        $reflect        = new ReflectionClass($this);
        $properties     = $reflect->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED);
        $toArray        = [];
        $primaryKeyName = \method_exists($this, 'getPrimaryKey') ? $this->getPrimaryKey() : null;
        foreach ($properties as $property) {
            $propertyName = $property->getName();
            if (
                ($propertyName === $primaryKeyName || $primaryKeyName === ($columnMap[$propertyName] ?? ''))
                && (int)$property->getValue($this) === 0
            ) {
                continue;
            }
            if ($tableColumns && \array_key_exists($propertyName, $columnMap)) {
                $propertyName = $columnMap[$propertyName];
            }
            $toArray = $this->fillArray($property, $dismissNull, $serialize, $toArray, $propertyName);
        }

        return $toArray;
    }

    /**
     * @inheritdoc
     */
    public function toObject(bool $tableColumns = true, bool $dismissNull = false): stdClass
    {
        return (object)$this->toArray($tableColumns, true, $dismissNull);
    }
}
