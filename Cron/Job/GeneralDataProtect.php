<?php

declare(strict_types=1);

namespace JTL\Cron\Job;

use JTL\Cron\Job;
use JTL\Cron\JobInterface;
use JTL\Cron\QueueEntry;
use JTL\GeneralDataProtection\TableCleaner;

/**
 * Class GeneralDataProtect
 * @package JTL\Cron\Job
 */
final class GeneralDataProtect extends Job
{
    protected int $taskIdx;

    protected int $taskRepetitions;

    protected int $lastProductID;

    /**
     * @inheritdoc
     */
    public function saveProgress(QueueEntry $queueEntry): bool
    {
        parent::saveProgress($queueEntry);
        $this->db->update(
            'tjobqueue',
            'jobQueueID',
            $this->getQueueID(),
            (object)['foreignKey' => (string)$this->taskIdx]
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function start(QueueEntry $queueEntry): JobInterface
    {
        parent::start($queueEntry);
        // using `tjobqueue`.`foreignKey` as a task index storage
        $this->taskIdx = (int)$queueEntry->foreignKey;
        // using `tjobqueue`.`lastProductID` as "index of work" in one table
        $this->lastProductID = $queueEntry->lastProductID;
        // using `tjobqueue`.`tasksExecuted` as repetition "down counter"
        $this->taskRepetitions = $queueEntry->tasksExecuted;
        if ($queueEntry->foreignKey === '') {
            $queueEntry->foreignKey = '0';
        }
        $tableCleaner = new TableCleaner();
        $tableCleaner->executeByStep(
            $this->taskIdx,
            $this->taskRepetitions,
            $this->lastProductID
        );
        $queueEntry->tasksExecuted = $tableCleaner->getTaskRepetitions();
        $queueEntry->lastProductID = $tableCleaner->getLastProductID();
        if ($tableCleaner->getIsFinished()) {
            $this->setForeignKey((string)$this->taskIdx++);
        }
        $this->setFinished($this->taskIdx >= $tableCleaner->getMethodCount());

        return $this;
    }
}
