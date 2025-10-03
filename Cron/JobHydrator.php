<?php

declare(strict_types=1);

namespace JTL\Cron;

/**
 * Class JobHydrator
 * @package JTL\Cron
 */
final class JobHydrator
{
    /**
     * @var array<string, string>
     */
    private static array $mapping = [
        'cronID'        => 'CronID',
        'jobType'       => 'Type',
        'taskLimit'     => 'Limit',
        'tasksExecuted' => 'Executed',
        'foreignKeyID'  => 'ForeignKeyID',
        'foreignKey'    => 'ForeignKey',
        'tableName'     => 'TableName',
        'jobQueueID'    => 'QueueID',
        'lastStart'     => 'DateLastStarted',
        'startTime'     => 'StartTime',
        'frequency'     => 'Frequency',
        'isRunning'     => 'Running',
        'lastFinish'    => 'DateLastFinished',
        'nextStart'     => 'NextStartDate'
    ];

    private function getMapping(string $key): ?string
    {
        return self::$mapping[$key] ?? null;
    }

    public function hydrate(JobInterface $class, object $data): JobInterface
    {
        foreach (\get_object_vars($data) as $key => $value) {
            if (($mapping = $this->getMapping($key)) === null) {
                continue;
            }
            $method = 'set' . $mapping;
            $class->$method($value);
        }

        return $class;
    }
}
