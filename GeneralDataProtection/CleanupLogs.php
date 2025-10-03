<?php

declare(strict_types=1);

namespace JTL\GeneralDataProtection;

/**
 * Class CleanupLogs
 * @package JTL\GeneralDataProtection
 *
 * Delete old logs containing personal data.
 * (interval former "interval_clear_logs" = 90 days)
 *
 * names of the tables, we manipulate:
 *
 * `temailhistory`
 * `tkontakthistory`
 * `tzahlungslog`
 * `tproduktanfragehistory`
 * `tverfuegbarkeitsbenachrichtigung`
 * `tjtllog`
 * `tzahlungseingang`
 * `tkundendatenhistory`
 * `tfloodprotect`
 */
class CleanupLogs extends Method implements MethodInterface
{
    /**
     * @var string[]
     */
    private array $methodName = [
        'cleanupEmailHistory',
        'cleanupContactHistory',
        'cleanupFloodProtect',
        'cleanupPaymentLogEntries',
        'cleanupProductInquiries',
        'cleanupAvailabilityInquiries',
        'cleanupLogs',
        'cleanupPaymentConfirmations',
        'cleanupCustomerDataHistory',
    ];

    public function execute(): void
    {
        $workLimitStart = $this->workLimit;
        foreach ($this->methodName as $method) {
            if ($this->workLimit === 0) {
                $this->isFinished = false;

                return;
            }
            $affected        = $this->$method();
            $this->workLimit -= $affected; // reduce $workLimit locallly for the next method
            $this->workSum   += $affected; // summarize complete work
        }
        $this->isFinished = ($this->workSum < $workLimitStart);
    }

    private function cleanupEmailHistory(): int
    {
        return $this->db->getAffectedRows(
            'DELETE FROM temailhistory
                WHERE dSent <= :dateLimit
                ORDER BY dSent ASC
                LIMIT :workLimit',
            [
                'dateLimit' => $this->dateLimit,
                'workLimit' => $this->workLimit
            ]
        );
    }

    private function cleanupContactHistory(): int
    {
        return $this->db->getAffectedRows(
            'DELETE FROM tkontakthistory
                WHERE dErstellt <= :dateLimit
                ORDER BY dErstellt ASC
                LIMIT :workLimit',
            [
                'dateLimit' => $this->dateLimit,
                'workLimit' => $this->workLimit
            ]
        );
    }

    private function cleanupFloodProtect(): int
    {
        return $this->db->getAffectedRows(
            'DELETE FROM tfloodprotect
                WHERE dErstellt <= :dateLimit
                ORDER BY dErstellt ASC
                LIMIT :workLimit',
            [
                'dateLimit' => $this->dateLimit,
                'workLimit' => $this->workLimit
            ]
        );
    }

    private function cleanupPaymentLogEntries(): int
    {
        return $this->db->getAffectedRows(
            'DELETE FROM tzahlungslog
                WHERE dDatum <= :dateLimit
                ORDER BY dDatum ASC
                LIMIT :workLimit',
            [
                'dateLimit' => $this->dateLimit,
                'workLimit' => $this->workLimit
            ]
        );
    }

    private function cleanupProductInquiries(): int
    {
        return $this->db->getAffectedRows(
            'DELETE FROM tproduktanfragehistory
                WHERE dErstellt <= :dateLimit
                ORDER BY dErstellt ASC
                LIMIT :workLimit',
            [
                'dateLimit' => $this->dateLimit,
                'workLimit' => $this->workLimit
            ]
        );
    }

    private function cleanupAvailabilityInquiries(): int
    {
        return $this->db->getAffectedRows(
            'DELETE FROM tverfuegbarkeitsbenachrichtigung
                WHERE dBenachrichtigtAm <= :dateLimit
                ORDER BY dBenachrichtigtAm ASC
                LIMIT :workLimit',
            [
                'dateLimit' => $this->dateLimit,
                'workLimit' => $this->workLimit
            ]
        );
    }

    private function cleanupLogs(): int
    {
        return $this->db->getAffectedRows(
            "DELETE FROM tjtllog
                WHERE
                    (cLog LIKE '%@%' OR cLog LIKE '%kKunde%')
                    AND dErstellt <= :dateLimit
                ORDER BY dErstellt ASC
                LIMIT :workLimit",
            [
                'dateLimit' => $this->dateLimit,
                'workLimit' => $this->workLimit
            ]
        );
    }

    private function cleanupPaymentConfirmations(): int
    {
        return $this->db->getAffectedRows(
            "DELETE FROM tzahlungseingang
                WHERE
                    cAbgeholt != 'Y'
                    AND dZeit <= :dateLimit
                ORDER BY dZeit ASC
                LIMIT :workLimit",
            [
                'dateLimit' => $this->dateLimit,
                'workLimit' => $this->workLimit
            ]
        );
    }

    private function cleanupCustomerDataHistory(): int
    {
        return $this->db->getAffectedRows(
            'DELETE FROM tkundendatenhistory
                WHERE dErstellt < MAKEDATE(YEAR(:nowTime) - 1, 1)
                ORDER BY dErstellt ASC
                LIMIT :workLimit',
            [
                'nowTime'   => $this->now->format('Y-m-d H:i:s'),
                'workLimit' => $this->workLimit
            ]
        );
    }
}
