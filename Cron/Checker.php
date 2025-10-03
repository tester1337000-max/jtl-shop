<?php

declare(strict_types=1);

namespace JTL\Cron;

use JTL\DB\DbInterface;
use Psr\Log\LoggerInterface;

/**
 * Class Checker
 * @package JTL\Cron
 */
class Checker
{
    /**
     * @var resource|false
     */
    private $filePointer;

    public function __construct(private readonly DbInterface $db, private readonly LoggerInterface $logger)
    {
        if (!\file_exists(\JOBQUEUE_LOCKFILE)) {
            \touch(\JOBQUEUE_LOCKFILE);
        }
        $this->filePointer = \fopen(\JOBQUEUE_LOCKFILE, 'rb');
    }

    public function __destruct()
    {
        if ($this->filePointer !== false) {
            \fclose($this->filePointer);
        }
    }

    public function isLocked(): bool
    {
        if ($this->filePointer === false || $this->lock()) {
            return false;
        }
        $this->unlock();

        return true;
    }

    public function lock(): bool
    {
        return $this->filePointer !== false && \flock($this->filePointer, \LOCK_EX | \LOCK_NB);
    }

    public function unlock(): bool
    {
        return $this->filePointer !== false && \flock($this->filePointer, \LOCK_UN);
    }

    /**
     * @return \stdClass[]
     */
    public function check(): array
    {
        $jobs = $this->db->getObjects(
            'SELECT tcron.*
                FROM tcron
                LEFT JOIN tjobqueue 
                    ON tjobqueue.cronID = tcron.cronID
                WHERE (tcron.lastStart IS NULL
                           OR tcron.nextStart IS NULL
                           OR tcron.nextStart < NOW())
                    AND tcron.startDate < NOW()
                    AND tjobqueue.jobQueueID IS NULL'
        );
        $this->logger->debug('Found {cnt} new cron jobs.', ['cnt' => \count($jobs)]);

        return $jobs;
    }
}
