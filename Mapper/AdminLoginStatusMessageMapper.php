<?php

declare(strict_types=1);

namespace JTL\Mapper;

use JTL\Backend\AdminLoginStatus;

/**
 * Class AdminLoginStatusMessageMapper
 * @package JTL\Mapper
 */
class AdminLoginStatusMessageMapper
{
    public function map(int $code): string
    {
        return match ($code) {
            AdminLoginStatus::LOGIN_OK             => 'user {user}@{ip} successfully logged in',
            AdminLoginStatus::ERROR_NOT_AUTHORIZED => 'user {user}@{ip} is not authorized',
            AdminLoginStatus::ERROR_INVALID_PASSWORD_LOCKED, AdminLoginStatus::ERROR_INVALID_PASSWORD
                                                   => 'invalid password for user {user}@{ip}',
            AdminLoginStatus::ERROR_USER_NOT_FOUND => 'user {user}@{ip} not found',
            AdminLoginStatus::ERROR_USER_DISABLED  => 'user {user}@{ip} disabled',
            AdminLoginStatus::ERROR_LOGIN_EXPIRED  => 'login for user {user}@{ip} expired',
            AdminLoginStatus::ERROR_TWO_FACTOR_AUTH_EXPIRED
                                                   => 'two factor authentication token for user {user}@{ip} expired',
            default                                => 'unknown error for user {user}@{ip}',
        };
    }
}
