<?php

declare(strict_types=1);

namespace JTL\Session\Handler;

/**
 * Interface JTLHandlerInterface
 * @package JTL\Session\Handler
 */
interface JTLHandlerInterface extends \SessionHandlerInterface
{
    /**
     * @param array<mixed> $data
     */
    public function setSessionData(array &$data): void;

    /**
     * @return array<mixed>|null
     */
    public function getSessionData(): ?array;

    /**
     * @return array<mixed>|null
     */
    public function getAll(): ?array;

    /**
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * @param string $name
     * @param mixed  $value
     * @return array<mixed>
     */
    public function set(string $name, mixed $value): array;

    /**
     * @param array<mixed> $array
     * @param string       $key
     * @param mixed        $value
     * @return array<mixed>
     */
    public static function arraySet(array &$array, string $key, mixed $value): array;

    /**
     * put a key/value pair or array of key/value pairs in the session.
     *
     * @param string|array<mixed> $key
     * @param mixed|null          $value
     */
    public function put(string|array $key, mixed $value = null): void;

    /**
     * push a value onto a session array.
     *
     * @param string $key
     * @param mixed  $value
     * @return void
     */
    public function push(string $key, mixed $value): void;
}
