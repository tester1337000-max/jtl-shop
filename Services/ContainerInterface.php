<?php

declare(strict_types=1);

namespace JTL\Services;

/**
 * Interface ContainerInterface
 * @package JTL\Services
 */
interface ContainerInterface extends \Psr\Container\ContainerInterface
{
    /**
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function setSingleton(string $id, callable $factory): void;

    /**
     * @throws \InvalidArgumentException
     */
    public function setFactory(string $id, callable $factory): void;

    /**
     * @template T
     * @param class-string<T> $id
     * @return T
     */
    public function getFactoryMethod(string $id);
}
