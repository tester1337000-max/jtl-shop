<?php

declare(strict_types=1);

namespace JTL;

/**
 * Trait MagicCompatibilityTrait
 *
 * allows backwards compatible access to class properties
 * that are now hidden behind getters and setters via a simple list of mappings
 */
trait MagicCompatibilityTrait
{
    /**
     * @var array<mixed>
     */
    private array $data = [];

    /**
     * @return string|string[]|null
     */
    private static function getMapping(string $value): array|string|null
    {
        return self::$mapping[$value] ?? null;
    }

    public function __get(string $name): mixed
    {
        \trigger_error(__CLASS__ . ': getter should be used to get ' . $name, \E_USER_DEPRECATED);
        if (\COMPATIBILITY_TRACE_DEPTH > 0 && \error_reporting() >= \E_USER_DEPRECATED) {
            Shop::dbg($name, false, 'Backtrace for', \COMPATIBILITY_TRACE_DEPTH + 1);
        }
        $mapped = self::getMapping($name);
        if ($mapped !== null) {
            if (\is_array($mapped) && \count($mapped) === 2) {
                $method1 = $mapped[0];
                $method2 = 'get' . $mapped[1];

                return $this->$method1()->$method2();
            }
            if (!\is_string($mapped)) {
                return null;
            }
            $method = 'get' . $mapped;

            return $this->$method();
        }
        if (\property_exists($this, $name)) {
            return $this->$name;
        }

        return $this->data[$name] ?? null;
    }

    public function __set(string $name, mixed $value)
    {
        \trigger_error(__CLASS__ . ': setter should be used to set ' . $name, \E_USER_DEPRECATED);
        if (\COMPATIBILITY_TRACE_DEPTH > 0 && \error_reporting() >= \E_USER_DEPRECATED) {
            Shop::dbg($name, false, 'Backtrace for', \COMPATIBILITY_TRACE_DEPTH + 1);
        }
        if (($mapped = self::getMapping($name)) !== null) {
            if (\is_array($mapped) && \count($mapped) === 2) {
                $method1 = $mapped[0];
                $method2 = 'set' . $mapped[1];
                $this->$method1()->$method2($value);

                return;
            }
            if (!\is_string($mapped)) {
                return;
            }
            $method = 'set' . $mapped;

            $this->$method($value);
            return;
        }
        if (\property_exists($this, $name)) {
            $this->$name = $value;
            return;
        }
        \trigger_error(__CLASS__ . ': setter could not find property ' . $name, \E_USER_DEPRECATED);
        $this->data[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return \property_exists($this, $name) || self::getMapping($name) !== null;
    }
}
