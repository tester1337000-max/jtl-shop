<?php

declare(strict_types=1);

namespace JTL\Cron;

use MyCLabs\Enum\Enum;

/**
 * Class Type
 * @package JTL\Cron
 * @extends Enum<Type::*>
 */
class Type extends Enum
{
    public const EXPORT = 'exportformat';

    public const STATUSMAIL = 'statusemail';

    public const TS_RATING = 'tskundenbewertung';

    public const CLEAR_CACHE = 'clearcache';

    public const NEWSLETTER = 'newsletter';

    public const PLUGIN = 'plugin';

    public const DATAPROTECTION = 'dataprotection';

    public const IMAGECACHE = 'imagecache';

    public const STORE = 'store';

    public const LICENSE_CHECK = 'licensecheck';

    public const TOPSELLER = 'topsellerupdate';

    public const MAILQUEUE = 'sendMailQueue';

    public const XSELLING = 'generateXSelling';

    public const REDIRECT_CLEANUP = 'redirectCleanup';
}
