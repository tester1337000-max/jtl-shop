<?php

declare(strict_types=1);

namespace JTL\Model;

use JTL\Backend\AdminLoginStatus;

/**
 * Class AuthLogEntry
 * @package JTL\Model
 */
class AuthLogEntry
{
    private string $ip = '0.0.0.0';

    private string $user = 'Unknown user';

    public int $code = AdminLoginStatus::ERROR_UNKNOWN;

    /**
     * @return array{ip: string, code: int, user: string}
     */
    public function asArray(): array
    {
        return [
            'ip'   => $this->getIP(),
            'code' => $this->getCode(),
            'user' => $this->getUser(),
        ];
    }

    public function getIP(): string
    {
        return $this->ip;
    }

    public function setIP(string $ip): void
    {
        $this->ip = $ip;
    }

    public function getUser(): string
    {
        return $this->user;
    }

    public function setUser(string $user): void
    {
        $this->user = $user;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function setCode(int $code): void
    {
        $this->code = $code;
    }
}
