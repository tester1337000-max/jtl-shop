<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Step;

use InvalidArgumentException;
use JTL\Backend\Upgrade\Release\Release;
use JTL\Backend\Upgrade\Release\ReleaseCollection;
use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTLShop\SemVer\Version;
use League\Flysystem\MountManager;

final readonly class Factory
{
    public function __construct(
        private StepConfiguration $configuration,
        private DbInterface $db,
        private JTLCacheInterface $cache,
        private MountManager $manager,
        private ReleaseCollection $release
    ) {
    }

    public function initWithReleaseID(int $requestedID): void
    {
        $releaseItem = $this->validateRelease(
            $this->release->getReleaseByID($requestedID),
            $requestedID
        );
        $this->initConfig($releaseItem);
    }

    public function initWithReleaseString(string $version): void
    {
        $this->initConfig($this->validateRelease($this->release->getReleasyByVersionString($version), $version));
    }

    private function initConfig(Release $releaseItem): void
    {
        $this->configuration->targetVersion = Version::parse($releaseItem->version)->setPrefix('');
        $this->configuration->downloadURL   = $releaseItem->downloadURL;
        $this->configuration->checksum      = $releaseItem->checksum;
    }

    private function validateRelease(Release $releaseItem, int|string $id): Release
    {
        if (!$releaseItem->shopVersionOK) {
            throw new InvalidArgumentException(
                \sprintf(
                    \__('Release %s requires shop version >= %s.'),
                    $id,
                    $releaseItem->minShopVersion
                )
            );
        }
        if ($releaseItem->phpVersionOK !== true) {
            throw new InvalidArgumentException(
                \sprintf(
                    \__('Release %s requires PHP version %s - %s, not compatible with your PHP version %s.'),
                    $id,
                    $releaseItem->phpMinVersion,
                    $releaseItem->phpMaxVersion,
                    \PHP_VERSION
                )
            );
        }

        return $releaseItem;
    }

    /**
     * @return StepInterface[]
     */
    public function getSteps(bool $fsBackup = true, bool $dbBackup = true, bool $updatePlugins = false): array
    {
        $res = [
            $this->createStep(CheckDirPermissions::class),
            $this->createStep(CheckDiskSpace::class),
            $this->createStep(CheckTemplateMaxShopVersion::class),
            $this->createStep(BackupSpecialFiles::class),
            $this->createStep(LockAquire::class),
            $this->createStep(EnableMaintenanceMode::class),
            $this->createStep(DownloadRelease::class),
            $this->createStep(VerifyRelease::class),
        ];
        if ($dbBackup) {
            $res[] = $this->createStep(CreateDBBackup::class);
        }
        if ($fsBackup) {
            $res[] = $this->createStep(CreateFSBackup::class);
        }
        if ($updatePlugins) {
            $res[] = $this->createStep(UpdatePlugins::class);
        }
        $res[] = $this->createStep(CheckPluginMaxShopVersion::class);
        $res[] = $this->createStep(UnzipRelease::class);
        $res[] = $this->createStep(ValidateReleaseStructure::class);
        $res[] = $this->createStep(PreUpgradeTests::class);
        $res[] = $this->createStep(MoveFilesToRoot::class);
        $res[] = $this->createStep(ExecuteMigrations::class);
        $res[] = $this->createStep(DeleteOldFiles::class);
        $res[] = $this->createStep(Finalize::class);
        $res[] = $this->createStep(LockRelease::class);
        $res[] = $this->createStep(DisableMaintenanceMode::class);

        return $res;
    }

    /**
     * @param class-string<StepInterface> $class
     */
    private function createStep(string $class): StepInterface
    {
        return new $class($this->configuration, $this->db, $this->cache, $this->manager);
    }
}
