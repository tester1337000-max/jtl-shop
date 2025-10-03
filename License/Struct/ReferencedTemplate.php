<?php

declare(strict_types=1);

namespace JTL\License\Struct;

use JTL\DB\DbInterface;
use JTL\Template\Admin\Listing;
use JTL\Template\Admin\ListingItem;
use JTL\Template\Admin\Validation\TemplateValidator;
use JTLShop\SemVer\Version;
use stdClass;

/**
 * Class ReferencedTemplate
 * @package JTL\License\Struct
 */
class ReferencedTemplate extends ReferencedItem
{
    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function initByExsID(DbInterface $db, stdClass $license, Releases $releases): void
    {
        $exsid = $license->exsid;
        $data  = $db->select('ttemplate', 'eTyp', 'standard');
        if ($data === null || $data->exsID !== $exsid) {
            $this->initFromListing($db, $exsid, $releases);
            return;
        }
        $available        = $releases->getAvailable();
        $latest           = $releases->getLatest();
        $installedVersion = Version::parse($data->version);
        $availableVersion = $available === null ? Version::parse('0.0.0') : $available->getVersion();
        $latestVersion    = $latest === null ? $availableVersion : $latest->getVersion();
        $this->setMaxInstallableVersion($installedVersion);
        $this->setHasUpdate(false);
        $this->setCanBeUpdated(false);
        $this->checkIsUpdatable($availableVersion, $installedVersion, $available, $latestVersion);
        $this->setID($data->cTemplate);
        $this->setInstalled(true);
        $this->setInstalledVersion($installedVersion);
        $this->setActive(true);
        $this->setInitialized(true);
    }

    public function checkIsUpdatable(
        Version $availableVersion,
        Version $installedVersion,
        ?Release $available,
        Version $latestVersion
    ): void {
        if ($availableVersion->greaterThan($installedVersion)) {
            $this->setMaxInstallableVersion($availableVersion);
            $this->setHasUpdate(true);
            $this->setCanBeUpdated(true);
            if (($available?->getPhpVersionOK() ?? Release::PHP_VERSION_LOW) !== Release::PHP_VERSION_OK) {
                $this->setCanBeUpdated(false);
            }
        } elseif (
            $latestVersion->greaterThan($availableVersion)
            && $latestVersion->greaterThan($installedVersion)
        ) {
            $this->setMaxInstallableVersion($latestVersion);
            $this->setHasUpdate(true);
            $this->setCanBeUpdated(false);
        }
    }

    private function initFromListing(DbInterface $db, ?string $exsid, Releases $releases): void
    {
        $lstng    = new Listing($db, new TemplateValidator($db));
        $template = $lstng->getAll()->first(static fn(ListingItem $item): bool => $item->getExsID() === $exsid);
        if ($template === null) {
            return;
        }
        $available        = $releases->getAvailable();
        $latest           = $releases->getLatest() ?? $available;
        $installedVersion = Version::parse($template->getVersion());
        $availableVersion = $available === null ? Version::parse('0.0.0') : $available->getVersion();
        $latestVersion    = $latest === null ? $availableVersion : $latest->getVersion();
        $this->setMaxInstallableVersion($installedVersion);
        $this->setHasUpdate(false);
        $this->setCanBeUpdated(false);
        if ($availableVersion->greaterThan($installedVersion)) {
            $this->setMaxInstallableVersion($availableVersion);
            $this->setHasUpdate(true);
            $this->setCanBeUpdated(true);
        } elseif ($latestVersion->greaterThan($availableVersion)) {
            $this->setMaxInstallableVersion($latestVersion);
            $this->setHasUpdate(true);
            $this->setCanBeUpdated(false);
        }
        $this->setID($template->getPath());
        $this->setHasUpdate($installedVersion->smallerThan($availableVersion));
        $this->setInstalled(true);
        $this->setInstalledVersion($installedVersion);
        $this->setActive(false);
        $this->setInitialized(true);
    }
}
