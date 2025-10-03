<?php

declare(strict_types=1);

namespace JTL\Filter;

use MyCLabs\Enum\Enum;

/**
 * Class Visibility
 *
 * @package JTL\Filter
 * @method static Visibility SHOW_NEVER()
 * @method static Visibility SHOW_BOX()
 * @method static Visibility SHOW_CONTENT()
 * @method static Visibility SHOW_ALWAYS()
 * @extends Enum<Visibility::*>
 */
class Visibility extends Enum
{
    /**
     * never show filter
     */
    public const SHOW_NEVER = 0;

    /**
     * show filter in box
     */
    public const SHOW_BOX = 1;

    /**
     * show filter in content area
     */
    public const SHOW_CONTENT = 2;

    /**
     * always show filter
     */
    public const SHOW_ALWAYS = 3;
}
