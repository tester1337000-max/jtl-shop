<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Step;

use InvalidArgumentException;
use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTLShop\SemVer\Version;
use League\Flysystem\MountManager;
use stdClass;

final readonly class RollbackFactory
{
    public function __construct(
        private StepConfiguration $configuration,
        private DbInterface $db,
        private JTLCacheInterface $cache,
        private MountManager $manager
    ) {
    }

    public function initWithID(int $requestedID): void
    {
        /** @var object{id: string, version_from: string, backup_fs: string, backup_db: string}&stdClass $backup */
        $backup = $this->db->select('upgrade_log', 'id', $requestedID);
        if ($backup === null) {
            throw new InvalidArgumentException(\sprintf(\__('Backup with id %d not found.'), $requestedID));
        }
        if ($backup->backup_db === null) {
            throw new InvalidArgumentException(\sprintf(\__('Backup %d has no database backup.'), $requestedID));
        }
        if ($backup->backup_fs === null) {
            throw new InvalidArgumentException(\sprintf(\__('Backup %d has no file system backup.'), $requestedID));
        }
        $this->initConfig($backup);
    }

    /**
     * @param object{version_from: string, backup_fs: string, backup_db: string}&stdClass $backup
     */
    private function initConfig(stdClass $backup): void
    {
        $this->configuration->targetVersion = Version::parse($backup->version_from)->setPrefix('');
        $this->configuration->fsBackupFile  = $backup->backup_fs;
        $this->configuration->dbBackupFile  = $backup->backup_db;
    }

    /**
     * @return StepInterface[]
     */
    public function getSteps(bool $skipDBBackupRestore = false, bool $skipFSBackupRestore = false): array
    {
        $steps = [
            $this->createStep(CheckDiskSpace::class),
            $this->createStep(LockAquire::class),
            $this->createStep(EnableMaintenanceMode::class),
        ];
        if ($skipDBBackupRestore === false) {
            $steps[] = $this->createStep(RollbackCheckDBBackup::class);
        }
        if ($skipFSBackupRestore === false) {
            $steps[] = $this->createStep(RollbackCheckFSBackup::class);
            $steps[] = $this->createStep(RollbackFSBackup::class);
        }
        if ($skipDBBackupRestore === false) {
            $steps[] = $this->createStep(RollbackDBBackup::class);
        }
        $steps[] = $this->createStep(LockRelease::class);
        $steps[] = $this->createStep(DisableMaintenanceMode::class);

        return $steps;
    }

    /**
     * @param class-string<StepInterface> $class
     */
    private function createStep(string $class): StepInterface
    {
        return new $class($this->configuration, $this->db, $this->cache, $this->manager);
    }
}
