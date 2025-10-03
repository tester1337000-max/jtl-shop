<?php

declare(strict_types=1);

namespace JTL\Cron;

use JTL\Abstracts\AbstractService;

/**
 * Class CronService
 * @package JTL\Cron
 */
class CronService extends AbstractService
{
    public function __construct(
        protected CronRepository $repository = new CronRepository(),
        protected JobQueueService $jobQueueService = new JobQueueService()
    ) {
    }

    public function getRepository(): CronRepository
    {
        return $this->repository;
    }

    public function getJobQueueService(): JobQueueService
    {
        return $this->jobQueueService;
    }

    /**
     * @return string[]
     */
    public static function getPermanentJobTypes(): array
    {
        return [
            Type::LICENSE_CHECK,
            Type::MAILQUEUE,
        ];
    }

    /**
     * @param int[] $cronIDs
     */
    public function delete(array $cronIDs): bool
    {
        $this->getRepository()->deleteCron($cronIDs, self::getPermanentJobTypes());

        return $this->getJobQueueService()->delete($cronIDs);
    }

    /**
     * @param int[] $cronIDs
     */
    public function startAsap(array $cronIDs): bool
    {
        return $this->getRepository()->startCronAsap($cronIDs);
    }
}
