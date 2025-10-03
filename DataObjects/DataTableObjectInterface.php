<?php

declare(strict_types=1);

namespace JTL\DataObjects;

/**
 * Interface DataObjectInterface
 * To create a DataTableObject for use in a repository:
 * - Extend AbstractDataObject and implement DataObjectInterface
 * - Make sure to provide a proper column mapping so that toObject(true)
 *   will provide an object with the column names needed
 *
 * @package JTL\DataObjects
 */
interface DataTableObjectInterface
{
    /**
     * Has to provide an array like ['tableColumnName' => 'propertyName']
     * Keep recommended property-name $columnMapping private to prevent inadvertent shipping
     *
     * @return array<string, string>
     */
    public function getColumnMapping(): array;

    /**
     * @return mixed
     */
    public function getID(): mixed;
}
