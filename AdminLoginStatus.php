<?php

declare(strict_types=1);

namespace JTL\Backend;

/**
 * Class AdminLoginStatus
 * @package JTL\Backend
 */
abstract class AdminLoginStatus
{
    public const LOGIN_OK = 1;

    public const ERROR_NOT_AUTHORIZED = 0;

    public const ERROR_INVALID_PASSWORD = -1;

    public const ERROR_INVALID_PASSWORD_LOCKED = -2;

    public const ERROR_USER_NOT_FOUND = -3;

    public const ERROR_USER_DISABLED = -4;

    public const ERROR_LOGIN_EXPIRED = -5;

    public const ERROR_TWO_FACTOR_AUTH_EXPIRED = -6;

    public const ERROR_UNKNOWN = -7;

    public const ERROR_LOCKED = -8;

    public const ERROR_SESSION_INVALID = -9;
}
