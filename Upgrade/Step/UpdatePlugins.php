<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Step;

use JTL\Backend\Upgrade\PluginUpgrader;
use JTL\License\Manager;
use JTL\License\Struct\ExsLicense;

final class UpdatePlugins extends AbstractStep
{
    public function getTitle(): string
    {
        return \__('Updating plugins...');
    }

    public function run(mixed $data = null): StepConfiguration
    {
        $this->startTiming();
        $upgrader = new PluginUpgrader($this->db, $this->cache, new Manager($this->db, $this->cache));
        $updates  = $upgrader->getPluginUpdates();
        if ($updates->count() > 0) {
            $this->progress->updatedPlugins = $upgrader->updatePlugins(
                $updates->map(fn(ExsLicense $license): ?string => $license->getReferencedItem()?->getID())->all()
            );
        }
        $this->stopTiming();

        return $this->progress;
    }
}
