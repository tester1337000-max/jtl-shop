<?php

declare(strict_types=1);

namespace JTL\Helpers;

use JTL\Catalog\Product\Merkmal;
use JTL\Shop;

/**
 * Class Attribute
 * @package JTL\Helpers
 * @since 5.0.0
 */
class Attribute
{
    /**
     * @param string|string[]     $attribute
     * @param string|int|string[] $value
     * @param callable|null       $callback
     * @return ($callback is null ? \stdClass|null : mixed)
     * @since 5.0.0
     */
    public static function getDataByAttribute(string|array $attribute, mixed $value, ?callable $callback = null): mixed
    {
        $res = Shop::Container()->getDB()->select('tmerkmal', $attribute, $value);

        return \is_callable($callback)
            ? $callback($res)
            : $res;
    }

    /**
     * @param string|string[]     $attribute
     * @param string|int|string[] $value
     * @param callable|null       $callback
     * @return ($callback is null ? Merkmal|null : mixed)
     * @since 5.0.0
     */
    public static function getAtrributeByAttribute(
        string|array $attribute,
        mixed $value,
        ?callable $callback = null
    ): mixed {
        $att = ($res = self::getDataByAttribute($attribute, $value)) !== null
            ? new Merkmal($res->kMerkmal)
            : null;

        return \is_callable($callback)
            ? $callback($att)
            : $att;
    }
}
