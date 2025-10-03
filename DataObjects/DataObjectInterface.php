<?php

declare(strict_types=1);

namespace JTL\DataObjects;

use stdClass;

/**
 * Interface DataObjectInterface
 * @package JTL\DataObjects
 */
interface DataObjectInterface
{
    /**
     * @param array<string, mixed> $data
     * @return DataObjectInterface
     */
    public function hydrate(array $data): self;

    /**
     * Will return an array containing Keys and values of protected and public properties.
     * Shall use getColumnMapping() if $tableColumns = true
     *
     * @param bool $tableColumns
     * @return array<string, mixed>
     */
    public function toArray(bool $tableColumns = true): array;

    /**
     * Object should have properties matching DataObject - or DataObject mapping
     * @param object $object
     * @return $this
     */
    public function hydrateWithObject(object $object): self;

    /**
     * Creates and returns object from data provided in toArray()
     * @param bool $tableColumns
     * @return stdClass
     */
    public function toObject(bool $tableColumns = true): stdClass;

    /**
     * Shall use setter to insert property data.
     * Will use getMapping() if available
     * @param string $name
     * @param mixed  $value
     */
    public function __set(string $name, mixed $value): void;

    /**
     * @param string $name
     * @return mixed
     */
    public function __get(string $name): mixed;

    /**
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool;

    /**
     * @param string $name
     */
    public function __unset(string $name): void;

    /**
     * Keep $mapping-array private to prevent it from being returned with toArray() or extract()
     * Mapping array gives the possibility to map any input to a specific property
     * Mapping more than one value to a property might become dangerous.
     * To map column names against properties you may consider to implement DataTableObjectInterface
     * and use $columnMapping instead
     *
     * @return string[]
     */
    public function getMapping(): array;

    /**
     * @return string[]
     */
    public function getReverseMapping(): array;
}
