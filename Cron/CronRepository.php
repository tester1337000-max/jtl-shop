<?php

declare(strict_types=1);

namespace JTL\Cron;

use JTL\Abstracts\AbstractDBRepository;

/**
 * Class CronRepository
 * @package JTL\Cron
 */
class CronRepository extends AbstractDBRepository
{
    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tcron';
    }

    /**
     * @inheritdoc
     */
    public function getKeyName(): string
    {
        return 'cronID';
    }

    /**
     * @param int[]    $ids
     * @param string[] $exclude
     */
    public function deleteCron(array $ids, array $exclude): bool
    {
        $affected = $this->getDB()->getAffectedRows(
            'DELETE FROM ' . $this->getTableName() . ' WHERE cronID IN (:ids) AND jobType NOT IN (:jobTypes)',
            [
                'jobTypes' => \implode(',', $exclude),
                'ids'      => \implode(',', $ids)
            ]
        );

        return $affected > 0;
    }

    /**
     * @param int[] $ids
     */
    public function startCronAsap(array $ids): bool
    {
        $affected = $this->getDB()->getAffectedRows(
            'UPDATE ' . $this->getTableName() . ' SET nextStart = NOW() WHERE cronID IN (:ids)',
            ['ids' => \implode(',', $ids)]
        );

        return $affected > 0;
    }
}
