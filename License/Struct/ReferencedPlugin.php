<?php

declare(strict_types=1);

namespace JTL\License\Struct;

use Carbon\Carbon;
use Exception;
use JTL\DB\DbInterface;
use JTL\Plugin\State;
use JTLShop\SemVer\Version;
use stdClass;

/**
 * Class ReferencedPlugin
 * @package JTL\License\Struct
 */
class ReferencedPlugin extends ReferencedItem
{
    /**
     * @inheritdoc
     */
    public function initByExsID(DbInterface $db, stdClass $license, Releases $releases): void
    {
        $installed = $db->select('tplugin', 'exsID', $license->exsid);
        if ($installed === null) {
            return;
        }
        $filesMissing     = !\is_dir(\PFAD_ROOT . \PLUGIN_DIR . $installed->cPluginID . '/')
            || !\file_exists(\PFAD_ROOT . \PLUGIN_DIR . $installed->cPluginID . '/' . \PLUGIN_INFO_FILE);
        $available        = $releases->getAvailable();
        $latest           = $releases->getLatest();
        $installedVersion = Version::parse($installed->nVersion);
        $availableVersion = $available === null ? Version::parse('0.0.0') : $available->getVersion();
        $latestVersion    = $latest === null ? $availableVersion : $latest->getVersion();
        $this->setReleaseAvailable($available !== null);
        $this->setHasUpdate(false);
        $this->setCanBeUpdated(false);
        $this->setID($installed->cPluginID);
        $this->checkIsUpdatable($installedVersion, $availableVersion, $available, $latestVersion);
        $this->setInstalled(true);
        $this->setInstalledVersion($installedVersion);
        $this->setActive((int)$installed->nStatus === State::ACTIVATED);
        $this->setInternalID((int)$installed->kPlugin);
        $this->setFilesMissing($filesMissing);
        try {
            $dateInstalled = (new Carbon($installed->dInstalliert))->toIso8601ZuluString();
        } catch (Exception) {
            $dateInstalled = null;
        }
        $this->setDateInstalled($dateInstalled);
        $this->setInitialized(true);
    }

    public function checkIsUpdatable(
        Version $installedVersion,
        Version $availableVersion,
        ?Release $available,
        Version $latestVersion
    ): void {
        $this->setMaxInstallableVersion($installedVersion);
        if ($availableVersion->greaterThan($installedVersion)) {
            $this->setMaxInstallableVersion($availableVersion);
            $this->setHasUpdate(true);
            $this->setCanBeUpdated(true);
            if (($available?->getPhpVersionOK() ?? Release::PHP_VERSION_LOW) !== Release::PHP_VERSION_OK) {
                $this->setCanBeUpdated(false);
            }
        } elseif ($latestVersion->greaterThan($availableVersion) && $latestVersion->greaterThan($installedVersion)) {
            $this->setMaxInstallableVersion($latestVersion);
            $this->setHasUpdate(true);
            $this->setShopVersionOK(false);
            $this->setCanBeUpdated(false);
        }
    }
}
