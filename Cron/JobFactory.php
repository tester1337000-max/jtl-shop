<?php

declare(strict_types=1);

namespace JTL\Cron;

use InvalidArgumentException;
use JTL\Cache\JTLCacheInterface;
use JTL\Cron\Job\Dummy;
use JTL\DB\DbInterface;
use JTL\Mapper\JobTypeToJob;
use Psr\Log\LoggerInterface;

/**
 * Class JobFactory
 * @package JTL\Cron
 */
class JobFactory
{
    public function __construct(
        protected DbInterface $db,
        protected LoggerInterface $logger,
        protected JTLCacheInterface $cache
    ) {
    }

    public function create(QueueEntry $data): JobInterface
    {
        $mapper = new JobTypeToJob();
        try {
            $class = $mapper->map($data->jobType);
        } catch (InvalidArgumentException) {
            $class = Dummy::class;
        }
        $job = new $class($this->db, $this->logger, new JobHydrator(), $this->cache);
        $job->hydrate($data);

        return $job;
    }
}
