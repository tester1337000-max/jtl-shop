<?php

declare(strict_types=1);

namespace JTL\DataObjects;

use stdClass;

/**
 * Interface DataObjectInterface
 * @package JTL\DataObjects
 */
interface DomainObjectInterface
{
    /**
     * Will return an array containing Keys and values of protected and public properties.
     * Shall use getColumnMapping() if $tableColumns = true
     *
     * @param bool $deep
     * @param bool $serialize
     * @return array<string, mixed>
     */
    public function toArray(bool $deep = false, bool $serialize = true): array;

    /**
     * @return array<string, mixed>
     */
    public function extract(): array;

    /**
     * Creates and returns object from data provided in toArray()
     * @param bool $deep
     * @return stdClass
     */
    public function toObject(bool $deep = false): stdClass;
}
