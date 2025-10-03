<?php

declare(strict_types=1);

namespace JTL\dbeS\Push;

/**
 * Class DeletedCustomers
 * @package JTL\dbeS\Push
 */
class DeletedCustomers extends AbstractPush
{
    private const LIMIT_CUSTOMERS = 100;

    /**
     * @return array<mixed>
     */
    public function getData(): array
    {
        $xml         = [];
        $customers   = $this->db->getArrays(
            'SELECT kKunde, customer_id, deleted, issuer 
                FROM deleted_customers
                WHERE ack = 0
                LIMIT :lmt',
            ['lmt' => self::LIMIT_CUSTOMERS]
        );
        $customerMax = $this->db->getSingleInt(
            'SELECT COUNT(id) AS cnt
                FROM deleted_customers
                WHERE ack = 0',
            'cnt'
        );

        $xml['kunden']['deleted_customer'] = $customers;
        $xml['kunden attr']['anzahl']      = \count($customers);
        $xml['kunden attr']['max']         = $customerMax;

        return $xml;
    }
}
