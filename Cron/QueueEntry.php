<?php

declare(strict_types=1);

namespace JTL\Cron;

use DateTime;
use stdClass;

/**
 * Class QueueEntry
 * @package JTL\Cron
 */
class QueueEntry
{
    public int $jobQueueID;

    public int $cronID;

    public int $foreignKeyID;

    public int $taskLimit;

    public int $tasksExecuted;

    public int $lastProductID;

    public int $isRunning = 0;

    public string $jobType;

    public ?string $tableName;

    public ?string $foreignKey;

    public DateTime $cronStartTime;

    public DateTime $startTime;

    public DateTime $lastStart;

    public DateTime $lastFinish;

    public DateTime $nextStart;

    public int $frequency;

    /**
     * compatibility only
     */
    public int $nLimitN;

    /**
     * compatibility only
     */
    public int $nLimitM;

    /**
     * timestamp at which the cronjob processing has started (unix-timestamp)
     * @since 5.3.0
     */
    public int $timestampCronHasStartedAt;

    public function __construct(stdClass $data)
    {
        $this->jobQueueID                = (int)$data->jobQueueID;
        $this->cronID                    = (int)$data->cronID;
        $this->foreignKeyID              = (int)$data->foreignKeyID;
        $this->taskLimit                 = (int)$data->taskLimit;
        $this->nLimitN                   = (int)$data->tasksExecuted;
        $this->tasksExecuted             = (int)$data->tasksExecuted;
        $this->nLimitM                   = (int)$data->taskLimit;
        $this->lastProductID             = (int)$data->lastProductID;
        $this->frequency                 = (int)($data->frequency ?? 0);
        $this->jobType                   = $data->jobType;
        $this->tableName                 = $data->tableName;
        $this->foreignKey                = $data->foreignKey;
        $this->cronStartTime             = new DateTime($data->cronStartTime ?? '');
        $this->startTime                 = new DateTime($data->startTime ?? '');
        $this->lastStart                 = new DateTime($data->lastStart ?? '');
        $this->lastFinish                = new DateTime($data->lastFinish ?? '');
        $this->nextStart                 = new DateTime($data->nextStart ?? '');
        $this->timestampCronHasStartedAt = (int)($data->cronHasStartedAt ?? \time());
    }
}
