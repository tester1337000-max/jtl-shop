<?php

declare(strict_types=1);

namespace JTL\Backend\Wizard\Steps;

use MyCLabs\Enum\Enum;

/**
 * Class ErrorCode
 * @package JTL\Backend\Wizard\Steps
 * @extends Enum<ErrorCode::*>
 */
class ErrorCode extends Enum
{
    public const OK = 1;

    public const ERROR_REQUIRED = 2;

    public const INVALID_EMAIL = 3;

    public const ERROR_SSL = 4;

    public const ERROR_SSL_PLUGIN = 5;

    public const ERROR_PLUGIN = 6;

    public const ERROR_VAT = 7;
}
