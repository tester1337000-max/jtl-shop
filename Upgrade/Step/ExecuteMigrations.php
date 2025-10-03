<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Step;

use JTL\Update\MigrationManager;
use JTL\Update\Updater;

final class ExecuteMigrations extends AbstractStep
{
    public function getTitle(): string
    {
        return \__('Executing migrations...');
    }

    public function run(mixed $data = null): StepConfiguration
    {
        $this->startTiming();
        $manager = new MigrationManager($this->db);
        $manager->setMigrations([]);
        $migrations = $manager->getPendingMigrations(true);
        \ksort($migrations);
        foreach ($migrations as $id) {
            $migration = $manager->getMigrationById($id);
            $manager->executeMigration($migration);
            $this->progress->addInfo(
                \sprintf(
                    \__('Migrated %s - %s'),
                    $migration->getName(),
                    $migration->getDescription()
                )
            );
        }
        if ($this->progress->targetVersion !== null && \count($manager->getPendingMigrations(true)) === 0) {
            $updater = new Updater($this->db);
            $updater->setVersion($this->progress->targetVersion);
            $this->progress->addInfo(\sprintf(\__('Set DB version to %s'), $this->progress->targetVersion));
        }
        $this->stopTiming();

        return $this->progress;
    }
}
