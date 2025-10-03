<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Step;

final class LockAquire extends AbstractStep
{
    public function getTitle(): string
    {
        return \__('Aquiring lock...');
    }

    public function run(mixed $data = null): StepConfiguration
    {
        $this->startTiming();
        if (\file_exists(StepConfiguration::LOCK_FILE)) {
            if (\time() > \filemtime(StepConfiguration::LOCK_FILE) + (60 * 60)) {
                \unlink(StepConfiguration::LOCK_FILE);
            } else {
                throw new StepFailedException(\__('Cannot acquire lock - upgrade already running?'));
            }
        }
        \touch(StepConfiguration::LOCK_FILE);
        $this->stopTiming();

        return $this->progress;
    }
}
