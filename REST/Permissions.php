<?php

declare(strict_types=1);

namespace JTL\REST;

/**
 * Class Permissions
 * @package JTL\REST
 */
class Permissions
{
    public const ALLOW_METHOD_NONE = 0;

    public const ALLOW_METHOD_GET = 1;

    public const ALLOW_METHOD_POST = 2;

    public const ALLOW_METHOD_PUT = 4;

    public const ALLOW_METHOD_DELETE = 8;

    public function __construct(private int $value = 0)
    {
    }

    public function getConst(string $method): int
    {
        return match (\mb_strtoupper($method)) {
            'GET'    => self::ALLOW_METHOD_GET,
            'POST'   => self::ALLOW_METHOD_POST,
            'PUT'    => self::ALLOW_METHOD_PUT,
            'DELETE' => self::ALLOW_METHOD_DELETE,
            default  => self::ALLOW_METHOD_NONE,
        };
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function get(int $n): bool
    {
        return ($this->value & $n) !== 0;
    }

    public function set(int $n): void
    {
        $this->value |= $n;
    }

    public function clear(int $n): void
    {
        $this->value ^= $n;
    }

    public function methodAllowed(string $method): bool
    {
        return $this->get($this->getConst($method));
    }
}
