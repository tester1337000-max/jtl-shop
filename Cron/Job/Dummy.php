<?php

declare(strict_types=1);

namespace JTL\Cron\Job;

use JTL\Cron\Job;
use JTL\Cron\JobInterface;
use JTL\Cron\QueueEntry;

/**
 * Class Dummy
 * @package JTL\Cron\Job
 */
final class Dummy extends Job
{
    /**
     * @inheritdoc
     */
    public function hydrate(object $data): self
    {
        parent::hydrate($data);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function start(QueueEntry $queueEntry): JobInterface
    {
        parent::start($queueEntry);

        return $this;
    }
}
