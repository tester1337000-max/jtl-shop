<?php

declare(strict_types=1);

namespace JTL\License\Struct;

use JTLShop\SemVer\Version;

/**
 * Class ReferencedItem
 * @package JTL\License\Struct
 */
abstract class ReferencedItem implements ReferencedItemInterface
{
    private string $id;

    private bool $installed = false;

    private ?Version $installedVersion = null;

    private ?Version $maxInstallableVersion = null;

    private bool $hasUpdate = false;

    private bool $canBeUpdated = true;

    private bool $shopVersionOK = true;

    private bool $active = false;

    private int $internalID = 0;

    private bool $initialized = false;

    private ?string $dateInstalled = null;

    private bool $filesMissing = false;

    private bool $releaseAvailable = false;

    /**
     * @inheritdoc
     */
    public function getID(): string
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     */
    public function setID(string $id): void
    {
        $this->id = $id;
    }

    /**
     * @inheritdoc
     */
    public function isInstalled(): bool
    {
        return $this->installed;
    }

    /**
     * @inheritdoc
     */
    public function setInstalled(bool $installed): void
    {
        $this->installed = $installed;
    }

    /**
     * @inheritdoc
     */
    public function getInstalledVersion(): ?Version
    {
        return $this->installedVersion;
    }

    /**
     * @inheritdoc
     */
    public function setInstalledVersion(?Version $installedVersion): void
    {
        $this->installedVersion = $installedVersion;
    }

    /**
     * @inheritdoc
     */
    public function getMaxInstallableVersion(): ?Version
    {
        return $this->maxInstallableVersion;
    }

    /**
     * @inheritdoc
     */
    public function setMaxInstallableVersion(?Version $maxInstallableVersion): void
    {
        $this->maxInstallableVersion = $maxInstallableVersion;
    }

    /**
     * @inheritdoc
     */
    public function hasUpdate(): bool
    {
        return $this->hasUpdate;
    }

    /**
     * @inheritdoc
     */
    public function setHasUpdate(bool $hasUpdate): void
    {
        $this->hasUpdate = $hasUpdate;
    }

    public function canBeUpdated(): bool
    {
        return $this->canBeUpdated;
    }

    public function setCanBeUpdated(bool $canBeUpdated): void
    {
        $this->canBeUpdated = $canBeUpdated;
    }

    /**
     * @inheritdoc
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * @inheritdoc
     */
    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    /**
     * @inheritdoc
     */
    public function getInternalID(): int
    {
        return $this->internalID;
    }

    /**
     * @inheritdoc
     */
    public function setInternalID(int $internalID): void
    {
        $this->internalID = $internalID;
    }

    /**
     * @inheritdoc
     */
    public function getDateInstalled(): ?string
    {
        return $this->dateInstalled;
    }

    /**
     * @inheritdoc
     */
    public function setDateInstalled(?string $dateInstalled): void
    {
        $this->dateInstalled = $dateInstalled;
    }

    /**
     * @inheritdoc
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * @inheritdoc
     */
    public function setInitialized(bool $initialized): void
    {
        $this->initialized = $initialized;
    }

    public function isFilesMissing(): bool
    {
        return $this->filesMissing;
    }

    public function setFilesMissing(bool $filesMissing): void
    {
        $this->filesMissing = $filesMissing;
    }

    public function isShopVersionOK(): bool
    {
        return $this->shopVersionOK;
    }

    public function setShopVersionOK(bool $shopVersionOK): void
    {
        $this->shopVersionOK = $shopVersionOK;
    }

    public function isReleaseAvailable(): bool
    {
        return $this->releaseAvailable;
    }

    public function setReleaseAvailable(bool $releaseAvailable): void
    {
        $this->releaseAvailable = $releaseAvailable;
    }
}
