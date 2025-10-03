<?php

declare(strict_types=1);

namespace JTL\News;

use MyCLabs\Enum\Enum;

/**
 * Class ViewType
 * @package JTL\News
 * @extends Enum<ViewType::*>
 */
class ViewType extends Enum
{
    public const NEWS_DISABLED = -1;

    public const NEWS_UNKNOWN = 0;

    public const NEWS_DETAIL = 1;

    public const NEWS_CATEGORY = 2;

    public const NEWS_MONTH_OVERVIEW = 3;

    public const NEWS_OVERVIEW = 4;
}
