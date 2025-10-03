<?php

declare(strict_types=1);

namespace JTL\Services;

/**
 * Class ContainerEntry
 * @package JTL\Services
 */
class ContainerEntry
{
    public const TYPE_FACTORY = 1;

    public const TYPE_SINGLETON = 2;

    /**
     * @var callable
     */
    protected $factory;

    protected ?object $instance;

    protected int $type;

    protected bool $locked = false;

    public function __construct(callable $factory, int $type)
    {
        if ($type !== self::TYPE_FACTORY && $type !== self::TYPE_SINGLETON) {
            throw new \InvalidArgumentException('$type incorrect');
        }
        $this->factory = $factory;
        $this->type    = $type;
    }

    public function getInstance(): ?object
    {
        return $this->instance;
    }

    public function hasInstance(): bool
    {
        return $this->instance !== null;
    }

    public function setInstance(object $instance): void
    {
        $this->instance = $instance;
    }

    public function getFactory(): callable
    {
        return $this->factory;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function lock(): void
    {
        $this->locked = true;
    }

    public function unlock(): void
    {
        $this->locked = false;
    }
}
