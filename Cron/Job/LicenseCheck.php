<?php

declare(strict_types=1);

namespace JTL\Cron\Job;

use DateTime;
use Exception;
use JTL\Cron\Job;
use JTL\Cron\JobInterface;
use JTL\Cron\QueueEntry;
use JTL\License\Checker;
use JTL\License\Manager;

/**
 * Class LicenseCheck
 * @package JTL\Cron\Job
 */
final class LicenseCheck extends Job
{
    /**
     * @inheritdoc
     */
    public function start(QueueEntry $queueEntry): JobInterface
    {
        parent::start($queueEntry);
        $manager = new Manager($this->db, $this->cache);
        try {
            $res                       = $manager->update(true);
            $queueEntry->tasksExecuted = 0;
            if ($res <= 0) {
                return $this;
            }
        } catch (Exception $e) {
            $this->logger->error('Caught exception: ' . $e->getMessage());
            // use tasksExecuted as exception counter
            ++$queueEntry->tasksExecuted;
            if ($queueEntry->tasksExecuted >= \LICENSE_CHECK_MAX_TRY_COUNT) {
                $queueEntry->tasksExecuted = 0;
                $this->setFinished(true);
            }
            // randomise next queue start time to avoid ddos-ing the license server
            $nextJobStartTime = (new DateTime('now'))->modify('+' . \random_int(30, 180) . ' minute');
            $this->db->update(
                'tjobqueue',
                'jobQueueID',
                $queueEntry->jobQueueID,
                (object)['startTime' => $nextJobStartTime->format('Y-m-d H:i:s')]
            );
            // also randomise next job start time
            $queueEntry->cronStartTime = $nextJobStartTime;

            return $this;
        }
        $checker = new Checker($this->logger, $this->db, $this->cache);
        $checker->handleExpiredLicenses($manager);
        $this->setFinished(true);

        return $this;
    }
}
