<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Step;

use JTL\Update\Updater;

final class CreateDBBackup extends AbstractStep
{
    public function getTitle(): string
    {
        return \__('Creating database backup...');
    }

    public function run(mixed $data = null): StepConfiguration
    {
        $this->startTiming();
        $updater = new Updater($this->db);
        $file    = $updater->createSqlDumpFile();
        $updater->createSqlDump($file, true, ['add-drop-table' => true]);
        $this->progress->dbBackupFile = $file;
        $this->progress->addInfo(\sprintf(\__('Created db backup %s'), $this->progress->dbBackupFile));
        $this->stopTiming();

        return $this->progress;
    }
}
