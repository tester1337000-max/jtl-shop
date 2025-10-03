<?php

declare(strict_types=1);

namespace JTL;

use InvalidArgumentException;

/**
 * Class Path
 * @package JTL
 */
class Path
{
    /**
     * @throws InvalidArgumentException
     */
    public static function combine(): string
    {
        $paths = \func_get_args();
        if (\count($paths) === 0) {
            throw new InvalidArgumentException('Empty or invalid paths');
        }

        return static::clean(\implode(\DIRECTORY_SEPARATOR, \array_map(self::clean(...), $paths)));
    }

    public static function getDirectoryName(string $path, bool $real = true): false|string
    {
        return ($real && \is_dir($path)) ? \realpath(\dirname($path)) : \dirname($path);
    }

    public static function getFileName(string $path): string
    {
        return self::hasExtension($path)
            ? self::getFileNameWithoutExtension($path) . '.' . self::getExtension($path)
            : self::getFileNameWithoutExtension($path);
    }

    public static function getFileNameWithoutExtension(string $path): string
    {
        return \pathinfo($path, \PATHINFO_FILENAME);
    }

    public static function getExtension(string $path): string
    {
        return \pathinfo($path, \PATHINFO_EXTENSION);
    }

    public static function hasExtension(string $path): bool
    {
        return \mb_strlen(self::getExtension($path)) > 0;
    }

    public static function addTrailingSlash(string $path): string
    {
        return static::removeTrailingSlash($path) . \DIRECTORY_SEPARATOR;
    }

    public static function removeTrailingSlash(string $path): string
    {
        return \rtrim($path, '/\\');
    }

    /**
     * Normalize path [/var/www/../test => /var/test].
     */
    public static function clean(string $path, bool $trailingSlash = false): string
    {
        $parts    = [];
        $path     = \str_replace('\\', '/', $path);
        $prefix   = '';
        $absolute = false;

        if (\preg_match('{^([\da-z]+:(?://(?:[a-z]:)?)?)}i', $path, $match)) {
            $prefix = $match[1];
            $path   = \substr($path, \strlen($prefix));
        }

        if (\str_starts_with($path, '/')) {
            $absolute = true;
            $path     = \substr($path, 1);
        }

        $up = false;
        foreach (\explode('/', $path) as $chunk) {
            if ($chunk === '..' && ($absolute || $up)) {
                \array_pop($parts);
                $up = !(empty($parts) || \end($parts) === '..');
            } elseif ($chunk !== '.' && $chunk !== '') {
                $parts[] = $chunk;
                $up      = $chunk !== '..';
            }
        }

        $path = $prefix . ($absolute ? '/' : '') . \implode('/', $parts);

        if ($trailingSlash) {
            $path = static::addTrailingSlash($path);
        }

        return $path;
    }
}
