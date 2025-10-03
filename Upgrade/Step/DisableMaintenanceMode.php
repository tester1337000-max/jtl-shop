<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Step;

final class DisableMaintenanceMode extends AbstractStep
{
    public function getTitle(): string
    {
        return \__('Disabling maintenance mode...');
    }

    public function run(mixed $data = null): StepConfiguration
    {
        $this->startTiming();
        $this->db->update('teinstellungen', 'cName', 'wartungsmodus_aktiviert', (object)['cWert' => 'N']);
        $this->cache->flushTags([\CACHING_GROUP_OPTION]);
        $this->progress->finished = true;
        $this->stopTiming();

        return $this->progress;
    }
}
