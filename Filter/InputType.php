<?php

declare(strict_types=1);

namespace JTL\Filter;

use MyCLabs\Enum\Enum;

/**
 * Class InputType
 *
 * @package JTL\Filter
 * @method static InputType SELECT()
 * @method static InputType CHECKBOX()
 * @method static InputType BUTTON()
 * @extends Enum<InputType::*>
 */
class InputType extends Enum
{
    /**
     * filter type selectbox
     */
    public const SELECT = 1;

    /**
     * filter type checkbox
     */
    public const CHECKBOX = 2;

    /**
     * filter type button
     */
    public const BUTTON = 3;
}
