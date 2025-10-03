<?php

declare(strict_types=1);

namespace JTL\Cron;

use DateTime;
use JTL\DB\DbInterface;
use JTL\Shop;
use Psr\Log\LoggerInterface;
use stdClass;

/**
 * Class Queue
 * @package JTL\Cron
 */
class Queue
{
    /**
     * @since 5.3.0
     */
    public function __construct(
        private readonly DbInterface $db,
        private readonly LoggerInterface $logger,
        private readonly JobFactory $factory,
        private int $timestampCronHasStartedAt = 0
    ) {
        if ($this->timestampCronHasStartedAt === 0) {
            $this->timestampCronHasStartedAt = \time();
        }
        Shop::Container()->getGetText()->loadAdminLocale('pages/cron');
    }

    /**
     * @return QueueEntry[]
     */
    public function loadQueueFromDB(): array
    {
        /** @var QueueEntry[] $queueEntries */
        $queueEntries = $this->db->getCollection(
            'SELECT tjobqueue.*, tcron.nextStart, tcron.startTime AS cronStartTime, tcron.frequency, '
            . $this->timestampCronHasStartedAt . ' AS cronHasStartedAt
                FROM tjobqueue
                JOIN tcron
                    ON tcron.cronID = tjobqueue.cronID
                WHERE tjobqueue.isRunning = 0
                    AND tjobqueue.startTime <= NOW()'
        )->map(fn(stdClass $e): QueueEntry => new QueueEntry($e))->toArray();
        $this->logger->debug('Loaded {cnt} existing job(s).', ['cnt' => \count($queueEntries)]);

        return $queueEntries;
    }

    public function unStuckQueues(): int
    {
        return $this->db->getAffectedRows(
            'UPDATE tjobqueue
                SET isRunning = 0
                WHERE isRunning = 1
                    AND startTime <= NOW()
                    AND lastStart IS NOT NULL
                    AND DATE_SUB(CURTIME(), INTERVAL :ntrvl Hour) > lastStart',
            ['ntrvl' => \QUEUE_MAX_STUCK_HOURS]
        );
    }

    /**
     * @param stdClass[] $jobs
     */
    public function enqueueCronJobs(array $jobs): void
    {
        foreach ($jobs as $job) {
            $queueEntry                = new stdClass();
            $queueEntry->cronID        = $job->cronID;
            $queueEntry->foreignKeyID  = $job->foreignKeyID ?? '_DBNULL_';
            $queueEntry->foreignKey    = $job->foreignKey ?? '_DBNULL_';
            $queueEntry->tableName     = $job->tableName;
            $queueEntry->jobType       = $job->jobType;
            $queueEntry->startTime     = 'NOW()';
            $queueEntry->taskLimit     = 0;
            $queueEntry->tasksExecuted = 0;
            $queueEntry->isRunning     = 0;

            $this->db->insert('tjobqueue', $queueEntry);
        }
    }

    /**
     * @throws \Exception
     */
    public function run(Checker $checker): int
    {
        if ($checker->isLocked()) {
            $this->logger->debug('Cron currently locked');

            return -1;
        }
        $checker->lock();
        $this->enqueueCronJobs($checker->check());
        $affected = $this->unStuckQueues();
        if ($affected > 0) {
            $this->logger->debug('Unstuck {cnt} job(s).', ['cnt' => $affected]);
        }
        $queueEntries = $this->loadQueueFromDB();
        \shuffle($queueEntries);
        foreach ($queueEntries as $i => $queueEntry) {
            if ($i >= \JOBQUEUE_LIMIT_JOBS) {
                $this->logger->debug('Job limit reached after {cnt} jobs.', ['cnt' => \JOBQUEUE_LIMIT_JOBS]);
                break;
            }
            $job                       = $this->factory->create($queueEntry);
            $queueEntry->tasksExecuted = $job->getExecuted();
            $queueEntry->taskLimit     = $job->getLimit();
            $queueEntry->isRunning     = 1;
            $this->logger->notice(
                'Got job {jb} (ID = {id}, type = {tp}, frequency = {frq})',
                [
                    'jb'  => \get_class($job),
                    'id'  => $job->getCronID(),
                    'tp'  => $job->getType(),
                    'frq' => $job->getFrequency()
                ]
            );
            $job->start($queueEntry);

            $queueEntry->isRunning = 0;
            $queueEntry->lastStart = new DateTime();

            $st        = $queueEntry->cronStartTime;
            $now       = new DateTime();
            $nextStart = new DateTime();
            $nextStart->setTime((int)$st->format('H'), (int)$st->format('i'), (int)$st->format('s'));
            if ($job->getFrequency() > 0) {
                while ($nextStart <= $now) {
                    $nextStart->modify('+' . $job->getFrequency() . ' hours');
                }
            }
            $this->db->update(
                'tcron',
                'cronID',
                $job->getCronID(),
                (object)[
                    'nextStart'  => $nextStart->format('Y-m-d H:i:s'),
                    'lastFinish' => $queueEntry->lastFinish->format('Y-m-d H:i')
                ]
            );
            \executeHook(\HOOK_JOBQUEUE_INC_BEHIND_SWITCH, [
                'oJobQueue' => $queueEntry,
                'job'       => $job,
                'logger'    => $this->logger
            ]);
            $job->saveProgress($queueEntry);
            if ($job->isFinished()) {
                $this->logger->notice('Job {jid} successfully finished.', ['jid' => $job->getID()]);
                $job->delete();
            }
        }
        $checker->unlock();

        return \count($queueEntries);
    }
}
