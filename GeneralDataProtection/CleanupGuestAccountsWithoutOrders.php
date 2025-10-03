<?php

declare(strict_types=1);

namespace JTL\GeneralDataProtection;

use JTL\Customer\Customer;

/**
 * Class CleanupGuestAccountsWithoutOrders
 * @package JTL\GeneralDataProtection
 *
 * Deleted guest accounts with no open orders
 *
 * names of the tables, we manipulate:
 *
 * `tkunde`
 */
class CleanupGuestAccountsWithoutOrders extends Method implements MethodInterface
{
    protected int $taskRepetitions = 5;

    /**
     * @inheritdoc
     */
    public function execute(): void
    {
        $this->workLimit = 100;
        $this->cleanupCustomers();
    }

    private function cleanupCustomers(): void
    {
        $guestAccounts       = $this->db->getObjects(
            "SELECT kKunde
                FROM tkunde
                WHERE
                    nRegistriert = 0
                    AND cAbgeholt = 'Y'
                    AND cKundenNr != :anonym
                    AND cVorname != :anonym
                    AND cNachname != :anonym
                    AND kKunde > :lastid
                ORDER BY kKunde
                LIMIT :worklimit",
            [
                'anonym'    => Customer::CUSTOMER_ANONYM,
                'worklimit' => $this->workLimit,
                'lastid'    => $this->lastProductID
            ]
        );
        $workCount           = \count($guestAccounts);
        $this->lastProductID = $workCount > 0 ? (int)$guestAccounts[$workCount - 1]->kKunde : 0;
        foreach ($guestAccounts as $guestAccount) {
            $customer = new Customer((int)$guestAccount->kKunde, null, $this->db);
            $delRes   = $customer->deleteAccount(Journal::ISSUER_TYPE_APPLICATION, 0);
            if (
                $delRes === Customer::CUSTOMER_DELETE_DEACT ||
                $delRes === Customer::CUSTOMER_DELETE_DONE
            ) {
                $this->workSum++;
            }
        }
        if ($this->workSum === 0) {
            $finished              = true;
            $this->taskRepetitions = 0;
        } else {
            $finished = false;
            $this->taskRepetitions--;
        }
        $this->isFinished = ($finished || $this->taskRepetitions === 0);
    }
}
