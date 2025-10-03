<?php

declare(strict_types=1);

namespace JTL\dbeS\Push;

use JTL\Checkout\Lieferadresse;
use JTL\Checkout\Rechnungsadresse;
use JTL\Services\JTL\CryptoServiceInterface;
use JTL\Shop;

/**
 * Class Orders
 * @package JTL\dbeS\Push
 */
final class Orders extends AbstractPush
{
    private const LIMIT_ORDERS = 100;

    /**
     * @return array<mixed>
     */
    public function getData(): array
    {
        $xml    = [];
        $orders = $this->getLastOrders();
        if (\count($orders) === 0) {
            return $xml;
        }
        $crypto          = Shop::Container()->getCryptoService();
        $orderAttributes = [];
        foreach ($orders as &$order) {
            $orderAttribute         = $this->buildAttributes($order);
            $orderID                = (int)$orderAttribute['kBestellung'];
            $order['tkampagne']     = $this->getCampaignInfo($orderID);
            $order['ttrackinginfo'] = $this->getTrackingInfo($orderID);

            $items          = $this->db->getArrays(
                'SELECT *
                    FROM twarenkorbpos
                    WHERE kWarenkorb = :cid',
                ['cid' => (int)$orderAttribute['kWarenkorb']]
            );
            $itemAttributes = [];
            foreach ($items as &$item) {
                $itemAttribute = $this->buildAttributes($item, ['cUnique', 'kKonfigitem', 'kBestellpos']);

                $itemAttribute['kBestellung']     = $orderAttribute['kBestellung'];
                $item['twarenkorbposeigenschaft'] = $this->db->getArrays(
                    'SELECT *
                        FROM twarenkorbposeigenschaft
                        WHERE kWarenkorbPos = :cid',
                    ['cid' => (int)$itemAttribute['kWarenkorbPos']]
                );
                unset($itemAttribute['kWarenkorb']);
                $itemAttributes[] = $itemAttribute;
                foreach ($item['twarenkorbposeigenschaft'] as $j => $prop) {
                    $item['twarenkorbposeigenschaft'][$j . ' attr'] = $this->buildAttributes($prop);
                }
            }
            unset($item);
            $order['twarenkorbpos'] = $items;
            foreach ($itemAttributes as $i => $attribute) {
                $order['twarenkorbpos'][$i . ' attr'] = $attribute;
            }
            $shippingAddress = $this->getShippingAddress((int)$orderAttribute['kLieferadresse']);
            $attr            = $this->buildAttributes($shippingAddress);
            // Strasse und Hausnummer zusammenführen
            if (isset($shippingAddress['cHausnummer'])) {
                $shippingAddress['cStrasse'] .= ' ' . \trim($shippingAddress['cHausnummer']);
            }
            $shippingAddress['cStrasse'] = \trim($shippingAddress['cStrasse'] ?? '');
            unset($shippingAddress['cHausnummer']);
            $order['tlieferadresse']      = $shippingAddress;
            $order['tlieferadresse attr'] = $attr;

            $billingAddress = $this->getBillingAddress((int)$orderAttribute['kRechnungsadresse']);
            $attr           = $this->buildAttributes($billingAddress);
            // Strasse und Hausnummer zusammenführen
            $billingAddress['cStrasse'] .= ' ' . \trim($billingAddress['cHausnummer'] ?? '');
            $billingAddress['cStrasse'] = \trim($billingAddress['cStrasse']);
            unset($billingAddress['cHausnummer']);
            $order['trechnungsadresse']      = $billingAddress;
            $order['trechnungsadresse attr'] = $attr;

            $payment                     = $this->getPaymentData($orderID, $crypto);
            $attr                        = $this->buildAttributes($payment);
            $order['tzahlungsinfo']      = $payment;
            $order['tzahlungsinfo attr'] = $attr;
            unset($orderAttribute['kVersandArt'], $orderAttribute['kWarenkorb']);

            $order['tbestellattribut'] = $this->db->getArrays(
                'SELECT cName AS `key`, cValue AS `value`
                    FROM tbestellattribut
                    WHERE kBestellung = :oid',
                ['oid' => $orderID]
            );
            if (\count($order['tbestellattribut']) === 0) {
                unset($order['tbestellattribut']);
            }
            $orderAttributes[] = $orderAttribute;
        }
        unset($order);
        $xml['bestellungen']['tbestellung'] = $orders;
        foreach ($orderAttributes as $i => $attribute) {
            $xml['bestellungen']['tbestellung'][$i . ' attr'] = $attribute;
        }
        $orderCount                         = \count($orders);
        $xml['bestellungen attr']['anzahl'] = $orderCount;

        return $xml;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function getLastOrders(): array
    {
        $orders = $this->db->getArrays(
            "SELECT tbestellung.kBestellung, tbestellung.kWarenkorb, tbestellung.kKunde, tbestellung.kLieferadresse,
            tbestellung.kRechnungsadresse, tbestellung.kZahlungsart, tbestellung.kVersandart, tbestellung.kSprache, 
            tbestellung.kWaehrung, '0' AS nZahlungsTyp, tbestellung.fGuthaben, tbestellung.cSession, 
            tbestellung.cZahlungsartName, tbestellung.cBestellNr, tbestellung.cVersandInfo, tbestellung.dVersandDatum, 
            tbestellung.cTracking, tbestellung.cKommentar, tbestellung.cAbgeholt, tbestellung.cStatus, 
            date_format(tbestellung.dErstellt, \"%d.%m.%Y\") AS dErstellt_formatted, tbestellung.dErstellt, 
            COALESCE(tzahlungsart.cModulId, '') AS cModulId, tbestellung.cPUIZahlungsdaten,
            tbestellung.nLongestMinDelivery, tbestellung.nLongestMaxDelivery, tbestellung.fWaehrungsFaktor
            FROM tbestellung
            LEFT JOIN tzahlungsart
                ON tzahlungsart.kZahlungsart = tbestellung.kZahlungsart
            WHERE cAbgeholt = 'N'
            ORDER BY tbestellung.kBestellung
            LIMIT " . self::LIMIT_ORDERS
        );
        foreach ($orders as $i => $order) {
            if (
                \strlen($order['cPUIZahlungsdaten'] ?? '') > 0
                && \preg_match('/^kPlugin_(\d+)_paypalexpress$/', $order['cModulId'])
            ) {
                $orders[$i]['cModulId'] = 'za_paypal_pui_jtl';
            }
        }

        return $orders;
    }

    /**
     * @param int $id
     * @return array<string, mixed>
     */
    private function getBillingAddress(int $id): array
    {
        $billingAddress        = new Rechnungsadresse($id);
        $country               = $this->db->select(
            'tland',
            'cISO',
            $billingAddress->cLand ?? '',
            null,
            null,
            null,
            null,
            false,
            'cDeutsch'
        );
        $iso                   = $billingAddress->cLand;
        $billingAddress->cLand = isset($country) ? $country->cDeutsch : $billingAddress->angezeigtesLand;
        unset($billingAddress->angezeigtesLand);
        $address = $billingAddress->gibRechnungsadresseAssoc();

        if (\count($address) > 0) {
            // Work Around um der Wawi die ausgeschriebene Anrede mitzugeben
            $address['cAnrede'] = $address['cAnredeLocalized'] ?? null;
            // Am Ende zusätzlich Ländercode cISO mitgeben
            $address['cISO'] = $iso;
        }

        return $address;
    }

    /**
     * @param int $id
     * @return array<string, mixed>
     */
    private function getShippingAddress(int $id): array
    {
        $deliveryAddress        = new Lieferadresse($id);
        $country                = $this->db->select(
            'tland',
            'cISO',
            $deliveryAddress->cLand ?? '',
            null,
            null,
            null,
            null,
            false,
            'cDeutsch'
        );
        $iso                    = $deliveryAddress->cLand;
        $deliveryAddress->cLand = isset($country) ? $country->cDeutsch : $deliveryAddress->angezeigtesLand;
        unset($deliveryAddress->angezeigtesLand);
        $address = $deliveryAddress->gibLieferadresseAssoc();
        if (\count($address) > 0) {
            // Work Around um der Wawi die ausgeschriebene Anrede mitzugeben
            $address['cAnrede'] = $address['cAnredeLocalized'] ?? null;
            // Am Ende zusätzlich Ländercode cISO mitgeben
            $address['cISO'] = $iso;
        }

        return $address;
    }

    /**
     * @return array<string, string>|null
     */
    private function getCampaignInfo(int $orderID): ?array
    {
        return $this->db->getSingleArray(
            "SELECT tkampagne.cName, tkampagne.cParameter cIdentifier,
                COALESCE(tkampagnevorgang.cParamWert, '') cWert
                FROM tkampagnevorgang
                INNER JOIN tkampagne 
                    ON tkampagne.kKampagne = tkampagnevorgang.kKampagne
                INNER JOIN tkampagnedef 
                    ON tkampagnedef.kKampagneDef = tkampagnevorgang.kKampagneDef
                WHERE tkampagnedef.cKey = 'kBestellung'
                    AND tkampagnevorgang.kKey = :oid
                ORDER BY tkampagnevorgang.kKampagneDef DESC LIMIT 1",
            ['oid' => $orderID]
        );
    }

    /**
     * @return array<string, string>|null
     */
    private function getTrackingInfo(int $orderID): ?array
    {
        return $this->db->getSingleArray(
            'SELECT cUserAgent, cReferer
                FROM tbesucher
                WHERE kBestellung = :oid
                LIMIT 1',
            ['oid' => $orderID]
        );
    }

    /**
     * @return array<string, string|null>
     */
    private function getPaymentData(int $orderID, CryptoServiceInterface $crypto): array
    {
        $payment = $this->db->getSingleArray(
            "SELECT *
                FROM tzahlungsinfo
                WHERE kBestellung = :oid AND cAbgeholt = 'N'
                ORDER BY kZahlungsInfo DESC LIMIT 1",
            ['oid' => $orderID]
        );
        $keys    = ['cBankName', 'cBLZ', 'cInhaber', 'cKontoNr', 'cIBAN', 'cBIC', 'cKartenNr', 'cCVV'];
        if ($payment === null) {
            $payment = [];
            foreach ($keys as $key) {
                $payment[$key] = null;
            }
        } else {
            foreach ($payment as $key => $value) {
                if ($value !== null && \in_array($key, $keys, true)) {
                    $payment[$key] = \trim($crypto->decryptXTEA($value));
                }
            }
            if ($payment['cCVV'] !== null && \strlen($payment['cCVV']) > 4) {
                $payment['cCVV'] = \substr($payment['cCVV'], 0, 4);
            }
        }

        return $payment;
    }
}
