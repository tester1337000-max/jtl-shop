<?php

declare(strict_types=1);

namespace JTL\Export\Exporter;

use JTL\Export\AsyncCallback;

/**
 * Class CronExporter
 * @package JTL\Export\Exporter
 */
class CronExporter extends AbstractExporter
{
    public function getNextStep(AsyncCallback $cb): void
    {
        $this->finish($cb);
    }

    protected function finish(AsyncCallback $cb): void
    {
        $this->logger->notice('Finalizing job...');
        if ($this->finishedInThisRun === true || $this->started === false) {
            $this->writer?->deleteOldExports();
            parent::finish($cb);
        }
        $this->logger->notice(
            'Finished after {snd}s. Product cache hits: {hts}, misses: {mss}',
            [
                'snd' => \round(\microtime(true) - $this->startedAt, 4),
                'hts' => $this->cacheHits,
                'mss' => $this->cacheMisses
            ]
        );
    }
}
