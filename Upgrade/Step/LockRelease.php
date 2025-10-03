<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Step;

final class LockRelease extends AbstractStep
{
    public function getTitle(): string
    {
        return \__('Releasing lock...');
    }

    public function run(mixed $data = null): StepConfiguration
    {
        $this->startTiming();
        if (\file_exists(StepConfiguration::LOCK_FILE)) {
            \unlink(StepConfiguration::LOCK_FILE);
        }
        $this->stopTiming();

        return $this->progress;
    }
}
