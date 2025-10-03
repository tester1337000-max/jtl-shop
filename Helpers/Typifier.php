<?php

declare(strict_types=1);

namespace JTL\Helpers;

use JTL\Shop;

/**
 * Class Typifier
 * @since 5.3.0
 * @package JTL\Helpers
 */
class Typifier
{
    private const POSSIBLEBOOLVALUES = [
        'true'  => true,
        'y'     => true,
        'yes'   => true,
        'ja'    => true,
        '1'     => true,
        'false' => false,
        'n'     => false,
        'no'    => false,
        'nein'  => false,
        '0'     => false,
    ];

    private const TYPEMAPPING = [
        'string'  => 'stringify',
        'integer' => 'intify',
        'double'  => 'floatify',
        'boolean' => 'boolify',
        'array'   => 'arrify',
        'object'  => 'objectify',
    ];

    private static function logError(string $msg): void
    {
        try {
            Shop::Container()->getLogService()->error(__CLASS__ . $msg);
        } catch (\Exception) {
            // do nothing
        }
        if (\APPLICATION_BUILD_SHA === '#DEV#') {
            throw new \InvalidArgumentException($msg, 501);
        }
    }

    public static function typeify(mixed $value, string $to): mixed
    {
        $method = self::TYPEMAPPING[$to] ?? '';
        if ($method === '') {
            return $value;
        }

        return self::$method($value);
    }

    /**
     * @param mixed       $value
     * @param string|null $default
     * @return ($value is string ? string : string|null)
     */
    public static function stringify(mixed $value, ?string $default = ''): ?string
    {
        if ($default !== null && (\is_array($value) || \is_object($value))) {
            self::logError('Value could not be typified into a string. ' . \serialize($value));
        }

        return $value === null ? $default : (string)$value;
    }

    public static function intify(mixed $value, int $default = 0): int
    {
        if (\is_numeric($value)) {
            return (int)$value;
        }

        return $default;
    }

    public static function intifyOrNull(mixed $value): ?int
    {
        return \is_numeric($value) ? (int)$value : null;
    }

    public static function floatify(mixed $value, float $default = 0.00): float
    {
        if (\is_numeric($value)) {
            return (float)$value;
        }

        return $default;
    }

    public static function boolify(mixed $value, ?bool $default = false): bool
    {
        if (\array_key_exists(\strtolower((string)$value), self::POSSIBLEBOOLVALUES)) {
            return \is_bool($value)
                ? $value
                : self::POSSIBLEBOOLVALUES[\strtolower((string)$value)];
        }

        return $default ?? false;
    }

    /**
     * @param mixed        $value
     * @param array<mixed> $default
     * @return array<mixed>
     */
    public static function arrify(mixed $value, array $default = []): array
    {
        if ($value !== null) {
            return (array)$value;
        }

        return $default;
    }

    public static function objectify(mixed $value, object $default = new \stdClass()): object
    {
        if (\is_object($value) || \is_array($value)) {
            return (object)$value;
        }

        return $default;
    }
}
