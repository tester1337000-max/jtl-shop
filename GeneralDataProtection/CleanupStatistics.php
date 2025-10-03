<?php

declare(strict_types=1);

namespace JTL\GeneralDataProtection;

/**
 * Class CleanupStatistics
 * @package JTL\GeneralDataProtection
 *
 * Delete old statistics.
 * (interval former "interval_clear_statistics" = 365 days)
 *
 * names of the tables, we manipulate:
 *
 * `consent_statistics`
 */
class CleanupStatistics extends Method implements MethodInterface
{
    /**
     * @var string[]
     */
    private array $methodName = [
        'consentStatistics'
    ];

    public function execute(): void
    {
        $workLimitStart = $this->workLimit;
        foreach ($this->methodName as $method) {
            if ($this->workLimit === 0) {
                $this->isFinished = false;
                return;
            }
            $affected = $this->$method();

            $this->workLimit -= $affected; // reduce $workLimit locallly for the next method
            $this->workSum   += $affected; // summarize complete work
        }
        $this->isFinished = ($this->workSum < $workLimitStart);
    }

    /**
     * delete consent statistics older than given interval
     */
    private function consentStatistics(): int
    {
        return $this->db->getAffectedRows(
            'DELETE FROM consent_statistics
                WHERE eventDate <= :dateLimit
                ORDER BY eventDate
                LIMIT :workLimit',
            [
                'dateLimit' => $this->dateLimit,
                'workLimit' => $this->workLimit
            ]
        );
    }
}
