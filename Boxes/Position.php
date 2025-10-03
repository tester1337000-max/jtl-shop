<?php

declare(strict_types=1);

namespace JTL\Boxes;

use MyCLabs\Enum\Enum;

/**
 * Class Position
 *
 * @package JTL\Boxes
 * @method Position LEFT()
 * @method Position RIGHT()
 * @method Position BOTTOM()
 * @method Position TOP()
 * @extends Enum<Position::*>
 */
class Position extends Enum
{
    public const LEFT = 'left';

    public const RIGHT = 'right';

    public const BOTTOM = 'bottom';

    public const TOP = 'top';
}
