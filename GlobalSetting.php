<?php

/** @noinspection PhpDeprecationInspection */

declare(strict_types=1);

namespace JTL;

/**
 * Class GlobalSetting
 * @package JTL
 * @deprecated since 5.3.0
 */
final class GlobalSetting
{
    private static ?self $instance = null;

    public const CHILD_ITEM_BULK_PRICING = 'GENERAL_CHILD_ITEM_BULK_PRICING';

    private function __construct()
    {
        \trigger_error(__CLASS__ . ' is deprecated and should not be used anymore.', \E_USER_DEPRECATED);
        self::$instance = $this;
    }

    private function __clone()
    {
    }

    public static function getInstance(): self
    {
        return self::$instance ?? new self();
    }

    public function getValue(string $valueName, mixed $default = null): mixed
    {
        $value = null;

        return match (\gettype($default)) {
            'boolean' => (bool)$value,
            'integer' => (int)$value,
            'double'  => (float)$value,
            'string'  => (string)$value,
            default   => $value,
        };
    }
}
