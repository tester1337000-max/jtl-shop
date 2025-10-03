<?php

declare(strict_types=1);

namespace JTL\Cron\Job;

use InvalidArgumentException;
use JTL\Cron\Job;
use JTL\Cron\JobInterface;
use JTL\Cron\QueueEntry;
use JTL\Export\Exporter\Factory;
use stdClass;

/**
 * Class Export
 * @package JTL\Cron\Job
 */
final class Export extends Job
{
    /**
     * @inheritdoc
     */
    public function hydrate(object $data): self
    {
        parent::hydrate($data);
        if (\JOBQUEUE_LIMIT_M_EXPORTE > 0) {
            $this->setLimit((int)\JOBQUEUE_LIMIT_M_EXPORTE);
        }

        return $this;
    }

    public function updateExportformatQueueBearbeitet(QueueEntry $queueEntry): bool
    {
        if ($queueEntry->jobQueueID <= 0) {
            return false;
        }
        $this->db->delete('texportformatqueuebearbeitet', 'kJobQueue', $queueEntry->jobQueueID);

        $ins                   = new stdClass();
        $ins->kJobQueue        = $queueEntry->jobQueueID;
        $ins->kExportformat    = $queueEntry->foreignKeyID;
        $ins->nLimitN          = $queueEntry->tasksExecuted;
        $ins->nLimitM          = $queueEntry->taskLimit;
        $ins->nInArbeit        = $queueEntry->isRunning;
        $ins->dStartZeit       = $queueEntry->startTime->format('Y-m-d H:i');
        $ins->dZuletztGelaufen = $queueEntry->lastStart->format('Y-m-d H:i');

        $this->db->insert('texportformatqueuebearbeitet', $ins);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function start(QueueEntry $queueEntry): JobInterface
    {
        parent::start($queueEntry);
        $id = $this->getForeignKeyID();
        if ($id === null) {
            return $this;
        }
        $factory  = new Factory($this->db, $this->logger, $this->cache);
        $finished = false;
        $ef       = null;
        try {
            $ef       = $factory->getExporter($id, false, true);
            $finished = $ef->start($queueEntry);
        } catch (InvalidArgumentException $e) {
            $this->logger->warning($e->getMessage());
        }
        $this->updateExportformatQueueBearbeitet($queueEntry);
        $this->setFinished($finished || ($ef?->isFinished() ?? false));

        return $this;
    }
}
