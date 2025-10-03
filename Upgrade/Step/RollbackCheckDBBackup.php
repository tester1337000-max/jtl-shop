<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Step;

final class RollbackCheckDBBackup extends AbstractStep
{
    public function getTitle(): string
    {
        return \__('Checking database backup file...');
    }

    public function run(mixed $data = null): StepConfiguration
    {
        $this->startTiming();
        $file = $this->progress->dbBackupFile;
        if (!\file_exists($file)) {
            throw new StepFailedException(\sprintf('Cannot roll back - backup file %s does not exist.', $file));
        }
        $this->stopTiming();

        return $this->progress;
    }
}
