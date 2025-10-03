<?php

declare(strict_types=1);

namespace JTL\GeneralDataProtection;

use JTL\Customer\Customer;

/**
 * Class CleanupCustomerRelicts
 * @package JTL\GeneralDataProtection
 *
 * clean up multiple tables at each run
 * (normaly one times a day)
 *
 * names of the tables, we manipulate:
 *
 * `tbesucherarchiv`
 * `tkundenattribut`
 * `tkundenkontodaten`
 * `tzahlungsinfo`
 * `tlieferadresse`
 * `trechnungsadresse`
 *
 * data will be removed here!
 */
class CleanupCustomerRelicts extends Method implements MethodInterface
{
    /**
     * @var string[]
     */
    private array $methodName = [
        'cleanupVisitorArchive',
        'cleanupCustomerAttributes',
        'cleanupPaymentInformation',
        'cleanupCustomerAccountData',
        'cleanupDeliveryAddresses',
        'cleanupBillingAddresses'
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

    private function cleanupVisitorArchive(): int
    {
        return $this->db->getAffectedRows(
            'DELETE FROM tbesucherarchiv
            WHERE
                kKunde > 0
                AND NOT EXISTS (
                    SELECT kKunde
                    FROM tkunde
                    WHERE
                        tkunde.kKunde = tbesucherarchiv.kKunde
                        AND tkunde.cVorname != :anonString
                        AND tkunde.cNachname != :anonString
                        AND tkunde.cKundenNr != :anonString
                )
                LIMIT :workLimit',
            [
                'workLimit'  => $this->workLimit,
                'anonString' => Customer::CUSTOMER_ANONYM
            ]
        );
    }

    private function cleanupCustomerAttributes(): int
    {
        return $this->db->getAffectedRows(
            'DELETE FROM tkundenattribut
            WHERE
                NOT EXISTS (
                    SELECT kKunde
                    FROM tkunde
                    WHERE
                        tkunde.kKunde = tkundenattribut.kKunde
                        AND tkunde.cVorname != :anonString
                        AND tkunde.cNachname != :anonString
                        AND tkunde.cKundenNr != :anonString
                )
            LIMIT :workLimit',
            [
                'workLimit'  => $this->workLimit,
                'anonString' => Customer::CUSTOMER_ANONYM
            ]
        );
    }

    private function cleanupPaymentInformation(): int
    {
        return $this->db->getAffectedRows(
            'DELETE FROM tzahlungsinfo
            WHERE
                kKunde > 0
                AND NOT EXISTS (
                    SELECT kKunde
                    FROM tkunde
                    WHERE
                        tkunde.kKunde = tzahlungsinfo.kKunde
                        AND tkunde.cVorname != :anonString
                        AND tkunde.cNachname != :anonString
                        AND tkunde.cKundenNr != :anonString
                )
            LIMIT :workLimit',
            [
                'workLimit'  => $this->workLimit,
                'anonString' => Customer::CUSTOMER_ANONYM
            ]
        );
    }

    private function cleanupCustomerAccountData(): int
    {
        return $this->db->getAffectedRows(
            'DELETE FROM tkundenkontodaten
            WHERE
                kKunde > 0
                AND NOT EXISTS (
                    SELECT kKunde
                     FROM tkunde
                     WHERE
                        tkunde.kKunde = tkundenkontodaten.kKunde
                        AND tkunde.cVorname != :anonString
                        AND tkunde.cNachname != :anonString
                        AND tkunde.cKundenNr != :anonString
                )
            LIMIT :workLimit',
            [
                'workLimit'  => $this->workLimit,
                'anonString' => Customer::CUSTOMER_ANONYM
            ]
        );
    }

    private function cleanupDeliveryAddresses(): int
    {
        return $this->db->getAffectedRows(
            "DELETE k
            FROM tlieferadresse k
                JOIN tbestellung b ON b.kKunde = k.kKunde
            WHERE
                b.cAbgeholt = 'Y'
                AND b.cStatus IN (:stateShipped, :stateCanceled)
                AND NOT EXISTS (
                    SELECT kKunde
                    FROM tkunde
                    WHERE
                        tkunde.kKunde = k.kKunde
                        AND tkunde.cVorname != :anonString
                        AND tkunde.cNachname != :anonString
                        AND tkunde.cKundenNr != :anonString
                )",
            [
                'stateShipped'  => \BESTELLUNG_STATUS_VERSANDT,
                'stateCanceled' => \BESTELLUNG_STATUS_STORNO,
                'anonString'    => Customer::CUSTOMER_ANONYM
            ]
        );
    }

    private function cleanupBillingAddresses(): int
    {
        return $this->db->getAffectedRows(
            "DELETE k
            FROM trechnungsadresse k
                JOIN tbestellung b ON b.kKunde = k.kKunde
            WHERE
                b.cAbgeholt = 'Y'
                AND b.cStatus IN (:stateShipped, :stateCanceled)
                AND NOT EXISTS (
                    SELECT kKunde
                    FROM tkunde
                    WHERE
                        tkunde.kKunde = k.kKunde
                        AND tkunde.cVorname != :anonString
                        AND tkunde.cNachname != :anonString
                        AND tkunde.cKundenNr != :anonString
                )",
            [
                'stateShipped'  => \BESTELLUNG_STATUS_VERSANDT,
                'stateCanceled' => \BESTELLUNG_STATUS_STORNO,
                'anonString'    => Customer::CUSTOMER_ANONYM
            ]
        );
    }
}
