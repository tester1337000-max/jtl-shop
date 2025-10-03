<?php

declare(strict_types=1);

namespace JTL\dbeS\Push;

use JTL\Customer\Customer;
use JTL\Shop;

/**
 * Class Customers
 * @package JTL\dbeS\Push
 */
final class Customers extends AbstractPush
{
    private const LIMIT_CUSTOMERS = 100;

    /**
     * @return array<mixed>
     */
    public function getData(): array
    {
        $xml           = [];
        $customers     = $this->db->getArrays(
            "SELECT kKunde, kKundengruppe, kSprache, cKundenNr, cPasswort, cAnrede, cTitel, cVorname,
            cNachname, cFirma, cStrasse, cHausnummer, cAdressZusatz, cPLZ, cOrt, cBundesland, cLand, cTel,
            cMobil, cFax, cMail, cUSTID, cWWW, fGuthaben, cNewsletter, dGeburtstag, fRabatt,
            cHerkunft, dErstellt, dVeraendert, cAktiv, cAbgeholt,
            date_format(dGeburtstag, '%d.%m.%Y') AS dGeburtstag_formatted, nRegistriert, cZusatz
                FROM tkunde
                    WHERE cAbgeholt = 'N'
                    ORDER BY kKunde LIMIT :lmt",
            ['lmt' => self::LIMIT_CUSTOMERS]
        );
        $customerCount = \count($customers);
        if ($customerCount === 0) {
            return $xml;
        }
        $crypto     = Shop::Container()->getCryptoService();
        $attributes = [];
        foreach ($customers as &$customer) {
            $customer['cAnrede']   = Customer::mapSalutation($customer['cAnrede'] ?? '', (int)$customer['kSprache']);
            $customer['cNachname'] = \trim($crypto->decryptXTEA($customer['cNachname'] ?? ''));
            $customer['cFirma']    = \trim($crypto->decryptXTEA($customer['cFirma'] ?? ''));
            $customer['cStrasse']  = \trim($crypto->decryptXTEA($customer['cStrasse'] ?? ''));
            // Strasse und Hausnummer zusammenfuehren
            $customer['cStrasse'] .= ' ' . \trim($customer['cHausnummer']);
            unset($customer['cHausnummer'], $customer['cPasswort']);
            $attribute  = $this->buildAttributes($customer);
            $additional = $customer['cZusatz'];
            unset($customer['cZusatz']);
            $customer['cZusatz']         = \trim($crypto->decryptXTEA($additional ?? ''));
            $customer['tkundenattribut'] = $this->db->getArrays(
                'SELECT * 
                    FROM tkundenattribut 
                    WHERE kKunde = :cid',
                ['cid' => (int)$attribute['kKunde']]
            );
            foreach ($customer['tkundenattribut'] as $o => $attr) {
                $customer['tkundenattribut'][$o . ' attr'] = $this->buildAttributes($attr);
            }
            $attributes[] = $attribute;
        }
        unset($customer);
        foreach ($attributes as $i => $attribute) {
            $customers[$i . ' attr'] = $attribute;
        }
        $xml['kunden']['tkunde']      = $customers;
        $xml['kunden attr']['anzahl'] = $customerCount;

        return $xml;
    }
}
