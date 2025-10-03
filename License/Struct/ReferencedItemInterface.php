<?php

declare(strict_types=1);

namespace JTL\License\Struct;

use JTL\DB\DbInterface;
use JTLShop\SemVer\Version;
use stdClass;

/**
 * Interface ReferencedItemInterface
 * @package JTL\License\Struct
 */
interface ReferencedItemInterface
{
    /**
     * @param DbInterface $db
     * @param stdClass    $license
     * @param Releases    $releases
     */
    public function initByExsID(DbInterface $db, stdClass $license, Releases $releases): void;

    /**
     * @return string
     */
    public function getID(): string;

    /**
     * @param string $id
     */
    public function setID(string $id): void;

    /**
     * @return bool
     */
    public function isInstalled(): bool;

    /**
     * @param bool $installed
     */
    public function setInstalled(bool $installed): void;

    /**
     * @return Version|null
     */
    public function getInstalledVersion(): ?Version;

    /**
     * @param Version|null $installedVersion
     */
    public function setInstalledVersion(?Version $installedVersion): void;

    /**
     * @return Version|null
     */
    public function getMaxInstallableVersion(): ?Version;

    /**
     * @param Version|null $maxInstallableVersion
     */
    public function setMaxInstallableVersion(?Version $maxInstallableVersion): void;

    /**
     * @return bool
     */
    public function hasUpdate(): bool;

    /**
     * @param bool $hasUpdate
     */
    public function setHasUpdate(bool $hasUpdate): void;

    /**
     * @return bool
     */
    public function isActive(): bool;

    /**
     * @param bool $active
     */
    public function setActive(bool $active): void;

    /**
     * @return int
     */
    public function getInternalID(): int;

    /**
     * @param int $internalID
     */
    public function setInternalID(int $internalID): void;

    /**
     * @return string|null
     */
    public function getDateInstalled(): ?string;

    /**
     * @param string|null $dateInstalled
     */
    public function setDateInstalled(?string $dateInstalled): void;

    /**
     * @return bool
     */
    public function isInitialized(): bool;

    /**
     * @param bool $initialized
     */
    public function setInitialized(bool $initialized): void;
}
