<?php

declare(strict_types=1);

namespace JTL\dbeS\Job;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;

/**
 * Interface JobInterface
 * @package JTL\dbeS\Job
 */
interface JobInterface
{
    /**
     * @param DbInterface       $db
     * @param JTLCacheInterface $cache
     */
    public function __construct(DbInterface $db, JTLCacheInterface $cache);

    /**
     * @return void
     */
    public function run(): void;
}
