<?php

declare(strict_types=1);

namespace JTL\Session;

use MyCLabs\Enum\Enum;

/**
 * Class Behaviour
 * @package JTL\Session
 * @extends Enum<Behaviour::*>
 */
class Behaviour extends Enum
{
    /**
     * handle bot like normal visitor
     */
    public const DEFAULT = 0;

    /**
     * use single session ID for all bot visits
     */
    public const COMBINE = 1;

    /**
     * save combined bot session to cache
     */
    public const CACHE = 2;

    /**
     * never save bot sessions
     */
    public const NO_SAVE = 3;
}
