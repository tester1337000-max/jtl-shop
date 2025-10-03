<?php

declare(strict_types=1);

namespace JTL\Pagination;

use MyCLabs\Enum\Enum;

/**
 * Class Operation
 * @package JTL\Pagination
 * @extends Enum<Operation::*>
 */
class Operation extends Enum
{
    public const CUSTOM = 0;

    public const CONTAINS = 1;

    public const BEGINS_WITH = 2;

    public const ENDS_WITH = 3;

    public const EQUALS = 4;

    public const LOWER_THAN = 5;

    public const GREATER_THAN = 6;

    public const LOWER_THAN_EQUAL = 7;

    public const GREATER_THAN_EQUAL = 8;

    public const NOT_EQUAL = 9;
}
