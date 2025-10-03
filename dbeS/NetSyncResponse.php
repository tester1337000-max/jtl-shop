<?php

declare(strict_types=1);

namespace JTL\dbeS;

/**
 * Class NetSyncResponse
 * @package JTL\dbeS
 */
class NetSyncResponse
{
    public const UNKNOWN = -1;

    public const OK = 0;

    public const ERRORLOGIN = 1;

    public const ERRORDESERIALIZE = 2;

    public const RECEIVINGDATA = 3;

    public const FOLDERNOTEXISTS = 4;

    public const ERRORINTERNAL = 5;

    public const ERRORNOLICENSE = 6;
}
