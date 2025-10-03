<?php

declare(strict_types=1);

namespace JTL\Pagination;

use MyCLabs\Enum\Enum;

/**
 * Class DataType
 * @package JTL\Pagination
 * @extends Enum<DataType::*>
 */
class DataType extends Enum
{
    public const TEXT = 0;

    public const NUMBER = 1;
}
