<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Plugin\Helper;
use JTL\Router\Route;

class PluginUpdates extends AbstractStatusCheck
{
    protected bool $includeInServiceReport = true;

    public function isOK(): bool
    {
        if (\SAFE_MODE === true) {
            return true;
        }
        $data = $this->db->getObjects(
            'SELECT `kPlugin`, `nVersion`, `bExtension`
                FROM `tplugin`'
        );
        if (\count($data) === 0) {
            return true; // there are no plugins installed
        }
        foreach ($data as $item) {
            try {
                $plugin = Helper::getLoader((int)$item->bExtension === 1)->init((int)$item->kPlugin);
            } catch (\Exception) {
                continue;
            }
            if ($plugin->getCurrentVersion()->greaterThan($item->nVersion)) {
                return false;
            }
        }

        return true;
    }

    public function getURL(): ?string
    {
        return $this->adminURL . Route::PLUGIN_MANAGER;
    }

    public function getTitle(): string
    {
        return \__('hasNewPluginVersionsTitle');
    }

    public function generateMessage(): void
    {
        $this->addNotification(\__('hasNewPluginVersionsMessage'));
    }
}
