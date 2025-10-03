<?php

declare(strict_types=1);

namespace JTL\DataObjects;

use ReflectionClass;
use ReflectionProperty;
use stdClass;

/**
 * Class AbstractDomainObject
 * @package JTL\DataObjects
 */
abstract class AbstractDomainObject implements DomainObjectInterface
{
    /**
     * @param array<'modifiedKeys', string[]> $modifiedKeys
     */
    public function __construct(public array $modifiedKeys = [])
    {
    }

    /**
     * @inheritdoc
     */
    public function toArray(bool $deep = false, bool $serialize = true): array
    {
        $reflect = new ReflectionClass($this);
        if ($deep === true) {
            $properties = $reflect->getProperties();
        } else {
            $properties = $reflect->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED);
        }

        return $this->getPropertyValues($properties, $serialize);
    }

    /**
     * @param bool $serialize
     * @return array<string, mixed>
     */
    public function toArrayMapped(bool $serialize = true): array
    {
        if (\method_exists($this, 'getColumnMapping')) {
            $columnMap = $this->getColumnMapping();
        } else {
            return $this->toArray(false, $serialize);
        }
        $reflect    = new ReflectionClass($this);
        $properties = $reflect->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED);

        return $this->getPropertyValues($properties, $serialize, $columnMap);
    }

    /**
     * @inheritdoc
     */
    public function toObject(bool $deep = false): stdClass
    {
        return (object)$this->toArray($deep);
    }

    public function toObjectMapped(): stdClass
    {
        return (object)$this->toArrayMapped();
    }

    /**
     * @throws \JsonException
     */
    public function toJson(bool $deep = false, ?int $flags = null): string|false
    {
        return \json_encode($this->toArray($deep), $flags ?? \JSON_THROW_ON_ERROR);
    }

    /**
     * @inheritdoc
     */
    public function extract(): array
    {
        $reflect    = new ReflectionClass($this);
        $attributes = $reflect->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED);
        $extracted  = [];
        foreach ($attributes as $attribute) {
            $method = 'get' . \ucfirst($attribute->getName());
            if ($attribute->name !== 'modifiedKeys') {
                $extracted[$attribute->name] = $this->$method();
            }
        }

        return $extracted;
    }

    /**
     * @param array<string, mixed> $newData
     * @return static
     * @description Makes a copy of a readonly domain object while changing the values of the given keys.
     * @comment This is a workaround for the fact that readonly objects cannot be modified. A modified domain object
     *  should never be trusted without further checking.
     * @since 5.3.0
     */
    public function copyWith(array $newData): static
    {
        $asArray = $this->toArray(true);

        if (isset($asArray['modifiedKeys'])) {
            unset($asArray['modifiedKeys']);
        }
        foreach ($newData as $key => $value) {
            if (\array_key_exists($key, $asArray) === false) {
                throw new \RuntimeException(
                    'Attempting to modify a nonexistent key ('
                    . $key . ') in ' . static::class
                );
            }
            $asArray[$key]             = $value;
            $asArray['modifiedKeys'][] = $key;
        }

        return new static(...$asArray);
    }

    /**
     * @param list<ReflectionProperty> $properties
     * @param bool                     $serialize
     * @param ?string[]                $columnMap
     * @return array<string, mixed>
     */
    protected function getPropertyValues(array $properties, bool $serialize, ?array $columnMap = []): array
    {
        $toArray        = [];
        $primaryKeyName = \method_exists($this, 'getPrimaryKey') ? $this->getPrimaryKey() : null;
        foreach ($properties as $property) {
            $propertyName  = $property->getName();
            $propertyValue = $property->getValue($this);
            if ($propertyName === 'modifiedKeys') {
                continue;
            }
            if (
                (
                    $propertyName === $primaryKeyName
                    || ($columnMap !== [] && $columnMap !== null && $primaryKeyName === $columnMap[$propertyName])
                )
                && (int)$propertyValue === 0
            ) {
                continue;
            }
            if ($columnMap !== [] && $columnMap !== null) {
                $propertyName = $columnMap[$propertyName];
            }
            if ($serialize && (\is_array($propertyValue || \is_object($propertyValue)))) {
                $toArray[$propertyName] = \serialize($propertyValue);
            } else {
                $toArray[$propertyName] = $propertyValue;
            }
        }

        return $toArray;
    }
}
