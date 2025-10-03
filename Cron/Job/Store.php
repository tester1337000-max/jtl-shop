<?php

declare(strict_types=1);

namespace JTL\Cron\Job;

use JTL\Cron\Job;
use JTL\Cron\JobInterface;
use JTL\Cron\QueueEntry;

/**
 * Class Store
 * @package JTL\Cron\Job
 */
final class Store extends Job
{
    /**
     * @inheritdoc
     */
    public function start(QueueEntry $queueEntry): JobInterface
    {
        parent::start($queueEntry);

        if ($this->getJobData() !== null) {
            $this->setFinished(true);
        }
        $this->setFinished(true);

        return $this;
    }
}
