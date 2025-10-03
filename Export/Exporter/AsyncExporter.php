<?php

declare(strict_types=1);

namespace JTL\Export\Exporter;

use JTL\Cron\QueueEntry;
use JTL\Export\AsyncCallback;
use Psr\Http\Message\ResponseInterface;

/**
 * Class AsyncExporter
 * @package JTL\Export\Exporter
 */
class AsyncExporter extends AbstractExporter
{
    protected function progress(QueueEntry $queueEntry): void
    {
        // async needs no progress
    }

    public function getNextStep(AsyncCallback $cb): ResponseInterface
    {
        if ($this->started === true) {
            return $cb->getResponse();
        }
        return $this->finish($cb);
    }

    protected function finish(AsyncCallback $cb): ResponseInterface
    {
        parent::finish($cb);

        return $cb->setIsFinished(true)
            ->setIsFirst(false)
            ->getResponse();
    }
}
