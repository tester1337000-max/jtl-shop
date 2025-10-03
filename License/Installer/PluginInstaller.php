<?php

declare(strict_types=1);

namespace JTL\License\Installer;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\License\AjaxResponse;
use JTL\Plugin\Admin\Installation\Extractor;
use JTL\Plugin\Admin\Installation\InstallationResponse;
use JTL\Plugin\Admin\Installation\Installer;
use JTL\Plugin\Admin\Installation\Uninstaller;
use JTL\Plugin\Admin\Updater;
use JTL\Plugin\Admin\Validation\LegacyPluginValidator;
use JTL\Plugin\Admin\Validation\PluginValidator;
use JTL\Plugin\Helper;
use JTL\Plugin\InstallCode;
use JTL\XMLParser;

/**
 * Class PluginInstaller
 * @package JTL\License\Installer
 */
class PluginInstaller implements InstallerInterface
{
    public function __construct(protected DbInterface $db, protected JTLCacheInterface $cache)
    {
    }

    /**
     * @inheritdoc
     */
    public function update(string $exsID, string $zip, AjaxResponse $response): int
    {
        $parser           = new XMLParser();
        $uninstaller      = new Uninstaller($this->db, $this->cache);
        $legacyValidator  = new LegacyPluginValidator($this->db, $parser);
        $pluginValidator  = new PluginValidator($this->db, $parser);
        $installer        = new Installer($this->db, $uninstaller, $legacyValidator, $pluginValidator, $this->cache);
        $updater          = new Updater($this->db, $installer);
        $extractor        = new Extractor($parser);
        $installResponse  = $extractor->extractPlugin($zip);
        $response->status = $installResponse->getStatus();
        if ($response->status === InstallationResponse::STATUS_FAILED) {
            $response->error      = $installResponse->getError() ?? \implode(', ', $installResponse->getMessages());
            $response->additional = $installResponse;

            return 0;
        }

        return $updater->update(Helper::getIDByExsID($exsID));
    }

    /**
     * @inheritdoc
     */
    public function install(string $itemID, string $zip, AjaxResponse $response): int
    {
        $parser          = new XMLParser();
        $uninstaller     = new Uninstaller($this->db, $this->cache);
        $legacyValidator = new LegacyPluginValidator($this->db, $parser);
        $pluginValidator = new PluginValidator($this->db, $parser);
        $installer       = new Installer($this->db, $uninstaller, $legacyValidator, $pluginValidator, $this->cache);
        $installer->setDir($itemID);
        $extractor        = new Extractor($parser);
        $installResponse  = $extractor->extractPlugin($zip);
        $response->status = $installResponse->getStatus();
        if ($response->status === InstallationResponse::STATUS_FAILED) {
            $response->error      = $installResponse->getError() ?? \implode(', ', $installResponse->getMessages());
            $response->additional = $installResponse;

            return 0;
        }

        return $installer->prepare(\rtrim($installResponse->getDirName() ?? '', '/'));
    }

    /**
     * @inheritdoc
     */
    public function forceUpdate(string $zip, AjaxResponse $response): int
    {
        $parser           = new XMLParser();
        $uninstaller      = new Uninstaller($this->db, $this->cache);
        $legacyValidator  = new LegacyPluginValidator($this->db, $parser);
        $pluginValidator  = new PluginValidator($this->db, $parser);
        $installer        = new Installer($this->db, $uninstaller, $legacyValidator, $pluginValidator, $this->cache);
        $updater          = new Updater($this->db, $installer);
        $extractor        = new Extractor($parser);
        $installResponse  = $extractor->extractPlugin($zip);
        $response->status = $installResponse->getStatus();
        if ($response->status === InstallationResponse::STATUS_FAILED) {
            $response->error      = $installResponse->getError() ?? \implode(', ', $installResponse->getMessages());
            $response->additional = $installResponse;

            return 0;
        }
        $pluginID = Helper::getIDByPluginID(\rtrim($installResponse->getDirName() ?? '', '/'));
        $check    = $this->db->select('tplugin', 'kPlugin', $pluginID);
        if ($check === null || !empty($check->exsID)) {
            // this method only updates old plugins without an exsID!
            return InstallCode::DUPLICATE_PLUGIN_ID;
        }

        return $updater->update($pluginID);
    }
}
