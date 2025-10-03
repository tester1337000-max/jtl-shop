<?php

declare(strict_types=1);

namespace JTL\Filter;

use MyCLabs\Enum\Enum;

/**
 * Class Type
 *
 * @package JTL\Filter
 * @method static Type OR()
 * @method static Type AND()
 * @extends Enum<Type::*>
 */
class Type extends Enum
{
    /**
     * filter can increase product amount
     */
    public const OR = 0;

    /**
     * filter will decrease product amount
     */
    public const AND = 1;
}
