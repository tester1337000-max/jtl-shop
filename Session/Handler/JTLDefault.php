<?php

declare(strict_types=1);

namespace JTL\Session\Handler;

/**
 * Class JTLDefault
 * @package JTL\Session\Handler
 */
class JTLDefault extends \SessionHandler implements JTLHandlerInterface
{
    /**
     * @var array<mixed>|null
     */
    protected ?array $sessionData = null;

    /**
     * @inheritdoc
     */
    public function setSessionData(array &$data): void
    {
        $this->sessionData = &$data;
    }

    /**
     * @inheritdoc
     */
    public function getSessionData(): ?array
    {
        return $this->sessionData;
    }

    /**
     * @inheritdoc
     */
    public function getAll(): ?array
    {
        return $this->getSessionData();
    }

    /**
     * @inheritdoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $array = $this->sessionData;
        if (isset($array[$key])) {
            return $array[$key];
        }
        foreach (\explode('.', $key) as $segment) {
            if (!\is_array($array) || !\array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }

    /**
     * @inheritdoc
     */
    public function set(string $name, mixed $value): array
    {
        return $this->sessionData === null
            ? []
            : self::arraySet($this->sessionData, $name, $value);
    }

    /**
     * @inheritdoc
     */
    public static function arraySet(array &$array, string $key, mixed $value): array
    {
        $keys = \explode('.', $key);
        while (\count($keys) > 1) {
            $idx = \array_shift($keys);
            if (!isset($array[$idx]) || !\is_array($array[$idx])) {
                $array[$idx] = [];
            }
            $array = &$array[$idx];
        }
        $array[\array_shift($keys)] = $value;

        return $array;
    }

    /**
     * @inheritdoc
     */
    public function put(string|array $key, mixed $value = null): void
    {
        if (!\is_array($key)) {
            $key = [$key => $value];
        }
        foreach ($key as $arrayKey => $arrayValue) {
            $this->set($arrayKey, $arrayValue);
        }
    }

    /**
     * @inheritdoc
     */
    public function push(string $key, mixed $value): void
    {
        /** @var array<mixed> $array */
        $array   = $this->get($key, []);
        $array[] = $value;
        $this->put($key, $array);
    }
}
