<?php

declare(strict_types=1);

namespace JTL\Boxes;

use MyCLabs\Enum\Enum;

/**
 * Class Type
 *
 * @package JTL\Boxes
 * @method Type DEFAULT()
 * @method Type PLUGIN()
 * @method Type TEXT()
 * @method Type LINK()
 * @method Type CATBOX()
 * @extends Enum<Type::*>
 */
class Type extends Enum
{
    public const DEFAULT = 'default';

    public const PLUGIN = 'plugin';

    public const TEXT = 'text';

    public const LINK = 'link';

    public const CATBOX = 'catbox';

    public const TPL = 'tpl';

    public const CONTAINER = 'container';

    public const EXTENSION = 'extension';
}
