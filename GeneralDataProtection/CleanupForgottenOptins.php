<?php

declare(strict_types=1);

namespace JTL\GeneralDataProtection;

/**
 * Class CleanupForgottenOptins
 * @package JTL\GeneralDataProtection
 *
 * cleanup double optins forgotten by user or
 * created by bots and still open for 24 hours
 *
 * names of the tables, we manipulate:
 *
 * `toptin`
 *
 * data will be removed here!
 */
class CleanupForgottenOptins extends Method implements MethodInterface
{
    public function execute(): void
    {
        $this->cleanupOptins();
        $this->isFinished = ($this->workSum < $this->workLimit);
    }

    /**
     * remove all unconfirmed ("open") OptIns from `toptin`
     * and OptIns they are not in `tnewsletterempfaenger`
     * in chunks of size of `workLimit`
     * (preserves NL-receivers activated by admin (e.nAktiv = 1) not by OptIn!)
     */
    private function cleanupOptins(): void
    {
        $result = $this->db->getObjects(
            'SELECT
                o.kOptin AS "o_kOptin",
                o.kOptinCode AS "o_kOptinCode",
                e.kNewsletterEmpfaenger AS "e_kNewsletterEmpfaenger",
                SUBSTRING(e.cOptCode, 3) AS "e_cOptCode"
            FROM
                toptin o
                LEFT JOIN tnewsletterempfaenger e
                    ON SUBSTRING(e.cOptCode, 3) = o.kOptinCode
                    AND e.nAktiv = 0
                    OR e.cOptCode IS NULL
            WHERE
                o.dCreated <= :dateLimit
                AND o.dActivated IS NULL
            ORDER BY o.kOptin
            LIMIT :workLimit',
            [
                'dateLimit' => $this->dateLimit,
                'workLimit' => $this->workLimit
            ]
        );

        $optinIDs     = [];
        $recipientIDs = [];
        foreach ($result as $row) {
            $optinIDs[]     = $row->o_kOptin;
            $recipientIDs[] = $row->e_kNewsletterEmpfaenger;
        }
        $recipientIDs = \array_filter($recipientIDs);
        $optinIDs     = \array_filter($optinIDs);
        if (\count($optinIDs) > 0) {
            $this->workSum += $this->db->getAffectedRows(
                'DELETE FROM toptin WHERE kOptin IN (' . \implode(',', $optinIDs) . ')'
            );
        }
        if (\count($recipientIDs) > 0) {
            $this->workSum += $this->db->getAffectedRows(
                'DELETE from tnewsletterempfaenger WHERE kNewsletterEmpfaenger IN (' .
                \implode(',', $recipientIDs) .
                ')'
            );
        }
    }
}
