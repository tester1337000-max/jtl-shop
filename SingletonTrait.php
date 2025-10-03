<?php

declare(strict_types=1);

namespace JTL;

/**
 * Trait SingletonTrait
 * @package JTL
 */
trait SingletonTrait
{
    private static ?self $instance = null;

    final public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    final public function __construct()
    {
        $this->init();
    }

    final public function __wakeup(): void
    {
    }

    final public function __clone(): void
    {
    }

    protected function init(): void
    {
    }
}
