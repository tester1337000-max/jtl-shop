<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Step;

final class EnableMaintenanceMode extends AbstractStep
{
    public function getTitle(): string
    {
        return \__('Enabling maintenance mode...');
    }

    public function run(mixed $data = null): StepConfiguration
    {
        $this->startTiming();
        $this->db->update('teinstellungen', 'cName', 'wartungsmodus_aktiviert', (object)['cWert' => 'Y']);
        $this->cache->flushTags([\CACHING_GROUP_OPTION]);
        $this->stopTiming();

        return $this->progress;
    }
}
