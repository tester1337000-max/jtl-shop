<?php

declare(strict_types=1);

namespace JTL\Cron\Job;

use JTL\Cron\Job;
use JTL\Cron\JobInterface;
use JTL\Cron\QueueEntry;

/**
 * Class Statusmail
 * @package JTL\Cron\Job
 */
final class Statusmail extends Job
{
    /**
     * @inheritdoc
     */
    public function hydrate(object $data): self
    {
        parent::hydrate($data);
        if (\JOBQUEUE_LIMIT_M_STATUSEMAIL > 0) {
            $this->setLimit((int)\JOBQUEUE_LIMIT_M_STATUSEMAIL);
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function start(QueueEntry $queueEntry): JobInterface
    {
        parent::start($queueEntry);
        $jobData = $this->getJobData();
        if ($jobData === null) {
            $this->setFinished(true);

            return $this;
        }
        $statusMail = new \JTL\Statusmail($this->db);
        $this->setFinished($statusMail->send($jobData));

        return $this;
    }
}
