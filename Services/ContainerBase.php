<?php

declare(strict_types=1);

namespace JTL\Services;

use Illuminate\Container\Container as IlluminateContainer;

/**
 * Class ContainerBase
 * @package JTL\Services
 */
class ContainerBase extends IlluminateContainer implements ContainerInterface
{
    /**
     * @inheritdoc
     */
    public function setSingleton(string $id, callable $factory): void
    {
        $this->singleton($id, $factory);
    }

    /**
     * @inheritdoc
     */
    public function setFactory(string $id, callable $factory): void
    {
        $this->bind($id, $factory);
    }

    public function getFactoryMethod(string $id): mixed
    {
        return $this->get($id);
    }
}
