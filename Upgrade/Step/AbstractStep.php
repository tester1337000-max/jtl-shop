<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Step;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use League\Flysystem\MountManager;

abstract class AbstractStep implements StepInterface
{
    protected float $start = 0.0;

    protected float $end = 0.0;

    public function __construct(
        protected StepConfiguration $progress,
        protected readonly DbInterface $db,
        protected readonly JTLCacheInterface $cache,
        protected readonly MountManager $manager
    ) {
    }

    public function getConfiguration(): StepConfiguration
    {
        return $this->progress;
    }

    abstract public function run(mixed $data = null): StepConfiguration;

    abstract public function getTitle(): string;

    public function startTiming(): void
    {
        $this->start = \microtime(true);
    }

    public function stopTiming(): void
    {
        $this->end = \microtime(true);
    }

    public function getTiming(): float
    {
        return (float)\number_format($this->end - $this->start, 4);
    }
}
