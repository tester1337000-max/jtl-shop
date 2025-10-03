<?php

declare(strict_types=1);

namespace JTL\Backend\ShippingClassWizard;

/**
 * Class CombineTypes
 * @package JTL\Backend\ShippingClassWizard
 */
class CombineTypes
{
    public const ALL            = 'all';
    public const COMBINE_ALL    = 'any';
    public const COMBINE_SINGLE = 'exclusive';
    public const EXCLUSIVE      = 'full_exclusive';

    public const ALL_TYPES = [
        self::ALL,
        self::COMBINE_ALL,
        self::COMBINE_SINGLE,
        self::EXCLUSIVE
    ];

    public const LOGIC_OR  = 'or';
    public const LOGIC_AND = 'and';

    public const ALL_LOGIC = [
        self::LOGIC_OR,
        self::LOGIC_AND
    ];
}
