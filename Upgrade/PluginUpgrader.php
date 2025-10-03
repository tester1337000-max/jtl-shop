<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\License\AjaxResponse;
use JTL\License\Collection;
use JTL\License\Installer\Helper;
use JTL\License\Manager;
use JTL\License\Mapper;
use JTL\License\Struct\ExsLicense;
use JTL\Plugin\InstallCode;

final readonly class PluginUpgrader
{
    public function __construct(private DbInterface $db, private JTLCacheInterface $cache, private Manager $manager)
    {
    }

    /**
     * @return Collection<int, ExsLicense>
     */
    public function getPluginUpdates(): Collection
    {
        return (new Mapper($this->manager))->getCollection()->getUpdateableItems();
    }

    /**
     * @param array<string|null> $itemIDs
     * @return array<string, int>
     */
    public function updatePlugins(array $itemIDs): array
    {
        $helper   = new Helper($this->manager, $this->db, $this->cache);
        $response = new AjaxResponse();
        $results  = [];
        foreach (\array_filter($itemIDs) as $itemID) {
            $licenseData = $this->manager->getLicenseByItemID($itemID);
            if ($licenseData === null) {
                continue;
            }
            $installer = $helper->getInstaller($itemID);
            $download  = $helper->getDownload($itemID);
            $result    = $installer->update($licenseData->getExsID(), $download, $response);
            if ($result === InstallCode::DUPLICATE_PLUGIN_ID) {
                $download = $helper->getDownload($itemID);
                $result   = $installer->forceUpdate($download, $response);
            }
            $results[$itemID] = $result;
        }
        $this->cache->flushTags([\CACHING_GROUP_LICENSES]);

        return $results;
    }
}
