<?php

declare(strict_types=1);

namespace JTL\dbeS;

/**
 * Class NetSyncRequest
 * @package JTL\dbeS
 */
class NetSyncRequest
{
    public const UNKNOWN = 0;

    public const UPLOADFILES = 1;

    public const UPLOADFILEDATA = 2;

    public const DOWNLOADFOLDERS = 3;

    public const DOWNLOADFILESINFOLDER = 4;

    public const CRONJOBTRIGGER = 5;

    public const CRONJOBSTATUS = 6;

    public const CRONJOBHISTORY = 7;
}
