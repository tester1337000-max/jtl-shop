<?php

declare(strict_types=1);

namespace JTL\Mapper;

use JTL\Backend\AdminLoginStatus;
use Monolog\Level;

/**
 * Class AdminLoginStatusToLogLevel
 * @package JTL\Mapper
 */
class AdminLoginStatusToLogLevel
{
    public function map(int $code): int
    {
        return match ($code) {
            AdminLoginStatus::LOGIN_OK                      => Level::Info->value,
            AdminLoginStatus::ERROR_INVALID_PASSWORD_LOCKED => Level::Alert->value,
            default                                         => Level::Warning->value,
        };
    }

    public function mapToJTLLog(int $code): int
    {
        return match ($code) {
            AdminLoginStatus::LOGIN_OK, Level::Info->value => \JTLLOG_LEVEL_NOTICE,
            default                                        => \JTLLOG_LEVEL_ERROR,
        };
    }
}
