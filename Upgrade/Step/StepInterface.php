<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Step;

interface StepInterface
{
    /**
     * @param mixed|null $data
     * @return StepConfiguration
     * @throws StepFailedException
     */
    public function run(mixed $data = null): StepConfiguration;

    public function getTitle(): string;

    public function startTiming(): void;

    public function stopTiming(): void;

    public function getTiming(): float;
}
