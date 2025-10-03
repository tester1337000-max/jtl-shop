<?php

declare(strict_types=1);

namespace JTL\License\Installer;

use InvalidArgumentException;
use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\License\Downloader;
use JTL\License\Exception\ApiResultCodeException;
use JTL\License\Exception\ChecksumValidationException;
use JTL\License\Exception\DownloadValidationException;
use JTL\License\Exception\FilePermissionException;
use JTL\License\Manager;
use JTL\License\Struct\ExsLicense;
use JTL\License\Struct\Release;
use JTLShop\SemVer\Version;

/**
 * Class Helper
 * @package JTL\License\Installer
 */
readonly class Helper
{
    public function __construct(
        private Manager $manager,
        private DbInterface $db,
        private JTLCacheInterface $cache
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getAvailableRelease(string $itemID): Release
    {
        $licenseData = $this->manager->getLicenseByItemID($itemID);
        if ($licenseData === null) {
            throw new InvalidArgumentException('Could not find item with ID ' . $itemID);
        }
        $available = $licenseData->getReleases()->getAvailable();
        if ($available === null) {
            throw new InvalidArgumentException('Could not find release for item with ID ' . $itemID);
        }

        return $available;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function validatePrerequisites(string $itemID): bool
    {
        $available = $this->getAvailableRelease($itemID);
        $version   = new Version();
        $version->setMajor(\PHP_MAJOR_VERSION);
        $version->setMinor(\PHP_MINOR_VERSION);
        if ($available->getPhpMaxVersion() !== null && $version->greaterThan($available->getPhpMaxVersion())) {
            throw new InvalidArgumentException('PHP version too high');
        }
        if ($available->getPhpMinVersion() !== null && $version->smallerThan($available->getPhpMinVersion())) {
            throw new InvalidArgumentException('PHP version too low');
        }

        return true;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getInstaller(string $itemID): InstallerInterface
    {
        $licenseData = $this->manager->getLicenseByItemID($itemID);
        if ($licenseData === null) {
            throw new InvalidArgumentException('Could not find item with ID ' . $itemID);
        }
        $type = $licenseData->getType();

        return match ($type) {
            ExsLicense::TYPE_PLUGIN,
            ExsLicense::TYPE_PORTLET  => new PluginInstaller($this->db, $this->cache),
            ExsLicense::TYPE_TEMPLATE => new TemplateInstaller($this->db, $this->cache),
            default                   => throw new InvalidArgumentException('Cannot update type ' . $type),
        };
    }

    /**
     * @throws DownloadValidationException
     * @throws InvalidArgumentException
     * @throws ApiResultCodeException
     * @throws FilePermissionException
     * @throws ChecksumValidationException
     */
    public function getDownload(string $itemID): string
    {
        return (new Downloader())->downloadRelease($this->getAvailableRelease($itemID));
    }
}
