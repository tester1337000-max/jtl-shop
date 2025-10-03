<?php

declare(strict_types=1);

namespace JTL\dbeS\Sync;

use DateTime;
use Exception;
use JTL\Cart\Cart;
use JTL\Checkout\Adresse;
use JTL\Checkout\Bestellung;
use JTL\Checkout\Lieferadresse;
use JTL\Checkout\OrderHandler;
use JTL\Checkout\Rechnungsadresse;
use JTL\Customer\Customer;
use JTL\dbeS\Starter;
use JTL\GeneralDataProtection\Journal;
use JTL\Language\LanguageHelper;
use JTL\Mail\Mail\Mail;
use JTL\Plugin\Payment\LegacyMethod;
use JTL\Plugin\Payment\MethodInterface;
use JTL\Session\Frontend;
use JTL\Shop;
use stdClass;

/**
 * Class Orders
 * @package JTL\dbeS\Sync
 */
final class Orders extends AbstractSync
{
    public function handle(Starter $starter): void
    {
        foreach ($starter->getXML() as $item) {
            /**
             * @var string               $file
             * @var array<string, mixed> $xml
             */
            [$file, $xml] = [\key($item), \reset($item)];
            if (\str_contains($file, 'ack_bestellung.xml')) {
                $this->handleACK($xml);
            } elseif (\str_contains($file, 'del_bestellung.xml')) {
                $this->handleDeletes($xml);
            } elseif (\str_contains($file, 'delonly_bestellung.xml')) {
                $this->handleDeleteOnly($xml);
            } elseif (\str_contains($file, 'storno_bestellung.xml')) {
                $this->handleCancelation($xml);
            } elseif (\str_contains($file, 'reaktiviere_bestellung.xml')) {
                $this->handleReactivation($xml);
            } elseif (\str_contains($file, 'ack_zahlungseingang.xml')) {
                $this->handlePaymentACK($xml);
            } elseif (\str_contains($file, 'set_bestellung.xml')) {
                $this->handleSet($xml);
            } elseif (\str_contains($file, 'upd_bestellung.xml')) {
                $this->handleUpdate($xml);
            } elseif (\str_contains($file, 'ins_bestellung.xml')) {
                $this->handleInsert($xml);
            }
        }
    }

    /**
     * @param array<mixed> $xml
     */
    private function handleACK(array $xml): void
    {
        $source = $xml['ack_bestellungen']['kBestellung'] ?? [];
        if (\is_numeric($source)) {
            $source = [$source];
        }
        foreach (\array_filter(\array_map('\intval', $source)) as $orderID) {
            $this->db->update('tbestellung', 'kBestellung', $orderID, (object)['cAbgeholt' => 'Y']);
            $this->db->update(
                'tbestellung',
                ['kBestellung', 'cStatus'],
                [$orderID, \BESTELLUNG_STATUS_OFFEN],
                (object)['cStatus' => \BESTELLUNG_STATUS_IN_BEARBEITUNG]
            );
            $this->db->update('tzahlungsinfo', 'kBestellung', $orderID, (object)['cAbgeholt' => 'Y']);
        }
    }

    private function getPaymentMethod(int $orderID): ?MethodInterface
    {
        $order = $this->db->getSingleObject(
            'SELECT tbestellung.kBestellung, tzahlungsart.cModulId
                FROM tbestellung
                LEFT JOIN tzahlungsart 
                    ON tbestellung.kZahlungsart = tzahlungsart.kZahlungsart
                WHERE tbestellung.kBestellung = :oid
                LIMIT 1',
            ['oid' => $orderID]
        );

        return ($order === null || empty($order->cModulId)) ? null : LegacyMethod::create($order->cModulId);
    }

    /**
     * @param array<mixed> $xml
     */
    private function handleDeletes(array $xml): void
    {
        $source = $xml['del_bestellungen']['kBestellung'] ?? [];
        if (\is_numeric($source)) {
            $source = [$source];
        }
        foreach (\array_filter(\array_map('\intval', $source)) as $orderID) {
            $orderID = (int)$orderID;
            if ($orderID <= 0) {
                continue;
            }
            $module = $this->getPaymentMethod($orderID);
            $module?->cancelOrder($orderID, true);
            $this->deleteOrder($orderID);
            // uploads (bestellungen)
            $this->db->delete('tuploadschema', ['kCustomID', 'nTyp'], [$orderID, 2]);
            $this->db->delete('tuploaddatei', ['kCustomID', 'nTyp'], [$orderID, 2]);
        }
    }

    /**
     * @param array<mixed> $xml
     */
    private function handleDeleteOnly(array $xml): void
    {
        $orderIDs = \is_array($xml['del_bestellungen']['kBestellung'])
            ? $xml['del_bestellungen']['kBestellung']
            : [$xml['del_bestellungen']['kBestellung']];
        foreach (\array_filter(\array_map('\intval', $orderIDs)) as $orderID) {
            $module = $this->getPaymentMethod($orderID);
            $module?->cancelOrder($orderID, true);
            $this->deleteOrder($orderID);
        }
    }

    /**
     * @param array<mixed> $xml
     */
    private function handleCancelation(array $xml): void
    {
        $source = $xml['storno_bestellungen']['kBestellung'] ?? [];
        if (\is_numeric($source)) {
            $source = [$source];
        }
        $passwordService = Shop::Container()->getPasswordService();
        try {
            $mailer = Shop::Container()->getMailer();
        } catch (Exception) {
            $this->logger->error('Mailer not available.');

            return;
        }
        foreach (\array_filter(\array_map('\intval', $source)) as $orderID) {
            $module   = $this->getPaymentMethod($orderID);
            $tmpOrder = new Bestellung($orderID, false, $this->db);
            $customer = new Customer($tmpOrder->kKunde, $passwordService, $this->db);
            $tmpOrder->fuelleBestellung();
            if ($module !== null) {
                $module->cancelOrder($orderID);
            } else {
                if (
                    !empty($customer->cMail)
                    && $tmpOrder->Zahlungsart !== null
                    && ($tmpOrder->Zahlungsart->nMailSenden & \ZAHLUNGSART_MAIL_STORNO)
                ) {
                    $data              = new stdClass();
                    $data->tkunde      = $customer;
                    $data->tbestellung = $tmpOrder;

                    $mail = new Mail();
                    $mailer->send($mail->createFromTemplateID(\MAILTEMPLATE_BESTELLUNG_STORNO, $data));
                }
                $this->db->update(
                    'tbestellung',
                    'kBestellung',
                    $orderID,
                    (object)['cStatus' => \BESTELLUNG_STATUS_STORNO]
                );
            }
            \executeHook(\HOOK_BESTELLUNGEN_XML_BEARBEITESTORNO, [
                'oBestellung' => &$tmpOrder,
                'oKunde'      => &$customer,
                'oModule'     => $module
            ]);
        }
    }

    /**
     * @param array<mixed> $xml
     */
    private function handleReactivation(array $xml): void
    {
        $source = $xml['reaktiviere_bestellungen']['kBestellung'] ?? [];
        if (\is_numeric($source)) {
            $source = [$source];
        }
        $passwordService = Shop::Container()->getPasswordService();
        try {
            $mailer = Shop::Container()->getMailer();
        } catch (Exception) {
            $this->logger->error('Mailer not available.');

            return;
        }
        foreach (\array_filter(\array_map('\intval', $source)) as $orderID) {
            $module = $this->getPaymentMethod($orderID);
            \executeHook(\HOOK_BESTELLUNGEN_XML_HANDLEREACTIVATION, [
                'orderID' => $orderID,
                'module'  => $module,
            ]);
            if ($module !== null) {
                $module->reactivateOrder($orderID);
            } else {
                $tmpOrder = new Bestellung($orderID, false, $this->db);
                $customer = new Customer($tmpOrder->kKunde, $passwordService, $this->db);
                $tmpOrder->fuelleBestellung();
                if (
                    $tmpOrder->Zahlungsart !== null
                    && ($tmpOrder->Zahlungsart->nMailSenden & \ZAHLUNGSART_MAIL_STORNO)
                    && $customer->cMail !== ''
                ) {
                    $data              = new stdClass();
                    $data->tkunde      = $customer;
                    $data->tbestellung = $tmpOrder;

                    try {
                        $mail = new Mail();
                        $mailer->send($mail->createFromTemplateID(\MAILTEMPLATE_BESTELLUNG_RESTORNO, $data));
                    } catch (Exception) {
                        $this->logger->error('Mailer not available.');
                    }
                }
                $this->db->update(
                    'tbestellung',
                    'kBestellung',
                    $orderID,
                    (object)['cStatus' => \BESTELLUNG_STATUS_IN_BEARBEITUNG]
                );
            }
        }
    }

    /**
     * @param array<mixed> $xml
     */
    private function handlePaymentACK(array $xml): void
    {
        $source = $xml['ack_zahlungseingang']['kZahlungseingang'] ?? [];
        if (\is_numeric($source)) {
            $source = [$source];
        }
        foreach (\array_filter(\array_map('\intval', $source)) as $id) {
            $this->db->update(
                'tzahlungseingang',
                'kZahlungseingang',
                $id,
                (object)['cAbgeholt' => 'Y']
            );
        }
    }

    /**
     * @param array<mixed> $xml
     */
    private function handleUpdate(array $xml): void
    {
        $order  = new stdClass();
        $orders = $this->mapper->mapArray($xml, 'tbestellung', 'mBestellung');
        if (\count($orders) === 1) {
            $order = $orders[0];
        }
        if (empty($order->kBestellung)) {
            \syncException(
                'Keine kBestellung in tbestellung! XML:' . \print_r($xml, true),
                \FREIDEFINIERBARER_FEHLER
            );
        }
        $order->kBestellung = (int)$order->kBestellung;
        $oldOrder           = $this->getShopOrder($order->kBestellung);
        if ($oldOrder === null) {
            \syncException(
                'Keine Bestellung in Shop gefunden:' . \print_r($xml, true),
                \FREIDEFINIERBARER_FEHLER
            );
        }
        $billingAddress = $this->getBillingAddress($oldOrder, $xml);
        if (!$oldOrder->kBestellung || \trim($order->cBestellNr) !== \trim($oldOrder->cBestellNr)) {
            \syncException(
                'Fehler: Zur Bestellung ' . $order->cBestellNr .
                ' gibt es keine Bestellung im Shop! Bestellung wurde nicht aktualisiert!',
                \FREIDEFINIERBARER_FEHLER
            );
        }
        $paymentMethod    = $this->getPaymentMethodFromXML($order, $xml);
        $correctionFactor = $this->applyCorrectionFactor($order);
        // Die Wawi schickt in fGesamtsumme die Rechnungssumme (Summe aller Positionen), der Shop erwartet hier
        // aber tatsächlich eine Gesamtsumme oder auch den Zahlungsbetrag (Rechnungssumme abzgl. evtl. Guthaben)
        $order->fGesamtsumme -= $order->fGuthaben;

        $this->updateOrderData($oldOrder, $order, $paymentMethod);
        $this->updateAddresses($oldOrder, $billingAddress, $xml);
        $this->updateCartItems($oldOrder, $correctionFactor, $xml);
        if (isset($xml['tbestellung']['tbestellattribut'])) {
            $this->editAttributes(
                $order->kBestellung,
                $this->mapper->isAssoc($xml['tbestellung']['tbestellattribut'])
                    ? [$xml['tbestellung']['tbestellattribut']]
                    : $xml['tbestellung']['tbestellattribut']
            );
        }
        $customer = new Customer((int)$oldOrder->kKunde);
        $this->sendMail($oldOrder, $order, $customer);

        \executeHook(\HOOK_BESTELLUNGEN_XML_BEARBEITEUPDATE, [
            'oBestellung'    => &$order,
            'oBestellungAlt' => &$oldOrder,
            'oKunde'         => &$customer
        ]);
    }

    private function validateCustomer(int $customerId): Customer
    {
        $customer = new Customer($customerId);
        if ($customer->kKunde > 0) {
            return $customer;
        }
        \syncException(
            'Fehler: Der Kunde mit der ID ' . $customerId . ' existiert nicht im Shop!',
            \FREIDEFINIERBARER_FEHLER
        );
    }

    private function validateOrderNr(string $orderNr, Customer $customer, Cart $cart): string
    {
        if ($orderNr !== '') {
            $order = $this->db->select('tbestellung', 'cBestellNr', $orderNr);
            if ($order !== null && (int)$order->kBestellung > 0) {
                \syncException(
                    'Fehler: Die Bestellung mit der Bestellnummer ' . $orderNr . ' existiert bereits im Shop!',
                    \FREIDEFINIERBARER_FEHLER
                );
            }

            return $orderNr;
        }

        return (new OrderHandler($this->db, $customer, $cart))->createOrderNo();
    }

    /**
     * @param array<mixed> $xml
     * @throws Exception
     */
    private function handleInsert(array $xml): void
    {
        $orders = $this->mapper->mapArray($xml, 'tbestellung', 'mBestellung');
        if (\count($orders) !== 1) {
            \syncException('Fehler: keine XML-Daten vorhanden', 5);
        }
        $orderData = $orders[0];

        $orderData->kRechnungsadresse = 0;
        $orderData->kLieferadresse    = 0;
        $orderData->kKunde            = (int)$orderData->kKunde;

        if ((int)($orderData->kBestellung ?? 0) > 0) {
            $this->handleUpdate($xml);

            return;
        }

        $frontSession = Frontend::getInstance();
        $savedLang    = Shop::getLanguageID();
        Shop::updateLanguage((int)$orderData->kSprache);

        $cart                  = new Cart();
        $customer              = $this->validateCustomer($orderData->kKunde);
        $orderData->cBestellNr = $this->validateOrderNr($orderData->cBestellNr ?? '', $customer, $cart);
        $correctionFactor      = $this->applyCorrectionFactor($orderData);
        $billingAddress        = $this->getBillingAddress($orderData, $xml);
        $deliveryAddress       = $this->getDeliveryAddress($orderData, $xml);
        $paymentMethod         = $this->getPaymentMethodFromXML($orderData, $xml);
        $shippingMethod        = $this->getShippingMethod($orderData);
        // Die Wawi schickt in fGesamtsumme die Rechnungssumme (Summe aller Positionen), der Shop erwartet hier
        // aber tatsächlich eine Gesamtsumme oder auch den Zahlungsbetrag (Rechnungssumme abzgl. evtl. Guthaben)
        $orderData->fGesamtsumme      -= $orderData->fGuthaben;
        $orderData->kRechnungsadresse = $billingAddress->insertInDB();
        $orderData->kLieferadresse    = $deliveryAddress->insertInDB();

        if ($paymentMethod === null) {
            $paymentMethod = (object)[
                'kZahlungsart'    => 0,
                'angezeigterName' => [Shop::getLanguageCode() => $orderData->cZahlungsartName],
            ];
        }

        Frontend::set('Zahlungsart', $paymentMethod);
        Frontend::set('Versandart', $shippingMethod);
        Frontend::setDeliveryAddress($deliveryAddress);

        $orderHandler = new OrderHandler($this->db, $customer, $cart);
        $orderHandler->persistOrder(false, $orderData->cBestellNr);
        $orderData->kBestellung = (int)Frontend::get('kBestellung');
        $orderData->kWarenkorb  = $cart->kWarenkorb;

        $this->updateCartItems($orderData, $correctionFactor, $xml);
        $this->updateAddresses($orderData, $billingAddress, $xml);
        $this->updateOrderData($orderData, $orderData, $paymentMethod);

        $frontSession->cleanUp();
        if (isset($xml['tbestellung']['tbestellattribut'])) {
            $this->editAttributes(
                $orderData->kBestellung,
                $this->mapper->isAssoc($xml['tbestellung']['tbestellattribut'])
                    ? [$xml['tbestellung']['tbestellattribut']]
                    : $xml['tbestellung']['tbestellattribut']
            );
        }

        $this->sendMail($orderData, $orderData, $customer);
        Shop::updateLanguage($savedLang);

        \executeHook(\HOOK_BESTELLUNGEN_XML_BEARBEITEINSERT, [
            'oBestellung' => &$orderData,
            'oKunde'      => &$customer
        ]);
    }

    /**
     * @param array<mixed> $xml
     */
    private function updateAddresses(stdClass $oldOrder, Rechnungsadresse $billingAddress, array $xml): void
    {
        $deliveryAddress = new Lieferadresse($oldOrder->kLieferadresse);
        $this->mapper->mapObject($deliveryAddress, $xml['tbestellung']['tlieferadresse'], 'mLieferadresse');
        if (isset($deliveryAddress->cAnrede)) {
            $deliveryAddress->cAnrede = $this->mapSalutation($deliveryAddress->cAnrede);
        }
        // Hausnummer extrahieren
        $this->extractStreet($deliveryAddress);
        // Workaround for WAWI-39370
        $deliveryAddress->cLand = Adresse::checkISOCountryCode($deliveryAddress->cLand);
        // lieferadresse ungleich rechungsadresse?
        if (
            $deliveryAddress->cVorname !== $billingAddress->cVorname
            || $deliveryAddress->cNachname !== $billingAddress->cNachname
            || $deliveryAddress->cStrasse !== $billingAddress->cStrasse
            || $deliveryAddress->cHausnummer !== $billingAddress->cHausnummer
            || $deliveryAddress->cPLZ !== $billingAddress->cPLZ
            || $deliveryAddress->cOrt !== $billingAddress->cOrt
            || $deliveryAddress->cLand !== $billingAddress->cLand
        ) {
            if ($deliveryAddress->kLieferadresse > 0) {
                $deliveryAddress->updateInDB();
            } else {
                $deliveryAddress->kKunde         = $oldOrder->kKunde;
                $deliveryAddress->kLieferadresse = $deliveryAddress->insertInDB();
                $this->db->update(
                    'tbestellung',
                    'kBestellung',
                    (int)$oldOrder->kBestellung,
                    (object)['kLieferadresse' => (int)$deliveryAddress->kLieferadresse]
                );
            }
        } elseif ($oldOrder->kLieferadresse > 0) {
            $this->db->update(
                'tbestellung',
                'kBestellung',
                (int)$oldOrder->kBestellung,
                (object)['kLieferadresse' => 0]
            );
        }
        $billingAddress->updateInDB();
    }

    private function applyCorrectionFactor(stdClass $order): float
    {
        $correctionFactor = 1.0;
        if (isset($order->kWaehrung)) {
            $currentCurrency = $this->db->select('twaehrung', 'kWaehrung', $order->kWaehrung);
            $defaultCurrency = $this->db->select('twaehrung', 'cStandard', 'Y');
            if ($currentCurrency !== null && isset($currentCurrency->kWaehrung, $defaultCurrency->kWaehrung)) {
                $correctionFactor    = (float)$currentCurrency->fFaktor;
                $order->fGesamtsumme /= $correctionFactor;
                $order->fGuthaben    /= $correctionFactor;
            }
        }

        return $correctionFactor;
    }

    /**
     * @param array<mixed> $xml
     */
    private function getPaymentMethodFromXML(stdClass $order, array $xml): ?stdClass
    {
        if (empty($xml['tbestellung']['cZahlungsartName'])) {
            return null;
        }
        // Von Wawi kommt in $xml['tbestellung']['cZahlungsartName'] nur der deutsche Wert,
        // deshalb immer Abfrage auf tzahlungsart.cName
        $paymentMethodName = $xml['tbestellung']['cZahlungsartName'];

        return $this->db->getSingleObject(
            'SELECT tzahlungsart.kZahlungsart, IFNULL(tzahlungsartsprache.cName, tzahlungsart.cName) AS cName
            FROM tzahlungsart
            LEFT JOIN tzahlungsartsprache
                ON tzahlungsartsprache.kZahlungsart = tzahlungsart.kZahlungsart
                AND tzahlungsartsprache.cISOSprache = :iso
            WHERE tzahlungsart.cName LIKE :search
            ORDER BY CASE
                WHEN tzahlungsart.cName = :name1 THEN 1
                WHEN tzahlungsart.cName LIKE :name2 THEN 2
                WHEN tzahlungsart.cName LIKE :name3 THEN 3
                END, kZahlungsart',
            [
                'iso'    => LanguageHelper::getLanguageDataByType('', (int)$order->kSprache),
                'search' => '%' . $paymentMethodName . '%',
                'name1'  => $paymentMethodName,
                'name2'  => $paymentMethodName . '%',
                'name3'  => '%' . $paymentMethodName . '%',
            ]
        );
    }

    private function getShippingMethod(stdClass $order): stdClass
    {
        $shippingMethod = new stdClass();

        $shippingMethod->kVersandart     = 0;
        $shippingMethod->cName           = $order->cLogistik;
        $shippingMethod->nMinLiefertage  = $order->nLongestMinDelivery;
        $shippingMethod->nMaxLiefertage  = $order->nLongestMaxDelivery;
        $shippingMethod->angezeigterName = [Shop::getLanguageCode() => $order->cLogistik];

        return $shippingMethod;
    }

    /**
     * @param array<mixed> $xml
     */
    private function getBillingAddress(stdClass $oldOrder, array $xml): Rechnungsadresse
    {
        $billingAddress = new Rechnungsadresse($oldOrder->kRechnungsadresse);
        $this->mapper->mapObject($billingAddress, $xml['tbestellung']['trechnungsadresse'], 'mRechnungsadresse');
        if (!empty($billingAddress->cAnrede)) {
            $billingAddress->cAnrede = $this->mapSalutation($billingAddress->cAnrede);
        }
        $this->extractStreet($billingAddress);
        // Workaround for WAWI-39370
        $billingAddress->cLand = Adresse::checkISOCountryCode($billingAddress->cLand);
        if (!$billingAddress->cNachname && !$billingAddress->cFirma && !$billingAddress->cStrasse) {
            \syncException(
                'Error Bestellung Update. Rechnungsadresse enthaelt keinen Nachnamen, Firma und Strasse! XML:' .
                \print_r($xml, true),
                \FREIDEFINIERBARER_FEHLER
            );
        }

        return $billingAddress;
    }

    /**
     * @param array<mixed> $xml
     */
    private function getDeliveryAddress(stdClass $oldOrder, array $xml): Lieferadresse
    {
        $deliveryAddress = new Lieferadresse($oldOrder->kLieferadresse ?? 0);
        $this->mapper->mapObject($deliveryAddress, $xml['tbestellung']['tlieferadresse'], 'mLieferadresse');
        if (isset($deliveryAddress->cAnrede)) {
            $deliveryAddress->cAnrede = $this->mapSalutation($deliveryAddress->cAnrede);
        }
        $this->extractStreet($deliveryAddress);
        $deliveryAddress->cLand = Adresse::checkISOCountryCode($deliveryAddress->cLand);
        if (!$deliveryAddress->cNachname && !$deliveryAddress->cFirma && !$deliveryAddress->cStrasse) {
            \syncException(
                'Error Bestellung Update. Lieferadress enthaelt keinen Nachnamen, Firma und Strasse! XML:' .
                \print_r($xml, true),
                \FREIDEFINIERBARER_FEHLER
            );
        }

        return $deliveryAddress;
    }

    private function updateOrderData(stdClass $oldOrder, stdClass $order, ?stdClass $paymentMethod): void
    {
        $params    = [
            'fg'    => $order->fGuthaben,
            'total' => $order->fGesamtsumme,
            'cmt'   => $order->cKommentar,
            'oid'   => $oldOrder->kBestellung
        ];
        $updateSql = '';
        if ($paymentMethod !== null && $paymentMethod->kZahlungsart > 0) {
            $params['pmid'] = (int)$paymentMethod->kZahlungsart;
            $params['pmnm'] = $paymentMethod->cName;
            $updateSql      = ' , kZahlungsart = :pmid, cZahlungsartName = :pmnm ';
        }
        if (isset($order->nLongestMinDelivery)) {
            $updateSql            .= ', nLongestMinDelivery = :longestMin';
            $params['longestMin'] = (int)$order->nLongestMinDelivery;
        }
        if (isset($order->nLongestMaxDelivery)) {
            $updateSql            .= ', nLongestMaxDelivery = :longestMax';
            $params['longestMax'] = (int)$order->nLongestMaxDelivery;
        }
        /** @noinspection SqlWithoutWhere */
        $this->db->queryPrepared(
            'UPDATE tbestellung SET
                fGuthaben = :fg,
                fGesamtsumme = :total,
                cKommentar = :cmt ' . $updateSql . '
            WHERE kBestellung = :oid',
            $params
        );
    }

    private function sendMail(stdClass $oldOrder, stdClass $order, Customer $customer): void
    {
        $module = $this->getPaymentMethod($oldOrder->kBestellung);
        $mail   = new Mail();
        $test   = $mail->createFromTemplateID(\MAILTEMPLATE_BESTELLUNG_AKTUALISIERT);
        $tpl    = $test->getTemplate();
        if (
            $tpl !== null
            && (!isset($order->cSendeEMail) || $order->cSendeEMail === 'Y')
            && $tpl->getModel() !== null
            && $tpl->getModel()->getActive() === true
        ) {
            if ($module !== null) {
                $module->sendMail($oldOrder->kBestellung, \MAILTEMPLATE_BESTELLUNG_AKTUALISIERT);
            } else {
                $data              = new stdClass();
                $data->tkunde      = $customer;
                $data->tbestellung = new Bestellung((int)$oldOrder->kBestellung, true, $this->db);

                try {
                    $mailer = Shop::Container()->getMailer();
                    $mailer->send($mail->createFromTemplateID(\MAILTEMPLATE_BESTELLUNG_AKTUALISIERT, $data));
                } catch (Exception) {
                    $this->logger->error('Mailer not available.');
                }
            }
        }
    }

    /**
     * @param array<mixed> $xml
     */
    private function updateCartItems(stdClass $oldOrder, float $correctionFactor, array $xml): void
    {
        $oldItems = $this->db->selectAll(
            'twarenkorbpos',
            'kWarenkorb',
            $oldOrder->kWarenkorb
        );
        $map      = [];
        foreach ($oldItems as $key => $oldItem) {
            $this->db->delete(
                'twarenkorbposeigenschaft',
                'kWarenkorbPos',
                (int)$oldItem->kWarenkorbPos
            );
            if ($oldItem->kArtikel > 0) {
                $map[$oldItem->kArtikel] = $key;
            }
        }
        $this->db->delete('twarenkorbpos', 'kWarenkorb', $oldOrder->kWarenkorb);
        $cartItems = $this->mapper->mapArray($xml['tbestellung'], 'twarenkorbpos', 'mWarenkorbpos');
        $itemCount = \count($cartItems);
        for ($i = 0; $i < $itemCount; $i++) {
            $oldItem = \array_key_exists($cartItems[$i]->kArtikel, $map)
                ? $oldItems[$map[$cartItems[$i]->kArtikel]]
                : null;
            unset($cartItems[$i]->kWarenkorbPos);
            $cartItems[$i]->kWarenkorb        = $oldOrder->kWarenkorb;
            $cartItems[$i]->fPreis            /= $correctionFactor;
            $cartItems[$i]->fPreisEinzelNetto /= $correctionFactor;
            // persistiere nLongestMin/MaxDelivery wenn nicht von Wawi übetragen
            if (!isset($cartItems[$i]->nLongestMinDelivery)) {
                $cartItems[$i]->nLongestMinDelivery = $oldItem->nLongestMinDelivery ?? 0;
            }
            if (!isset($cartItems[$i]->nLongestMaxDelivery)) {
                $cartItems[$i]->nLongestMaxDelivery = $oldItem->nLongestMaxDelivery ?? 0;
            }
            $cartItems[$i]->kWarenkorbPos = $this->db->insert(
                'twarenkorbpos',
                $cartItems[$i]
            );
            \executeHook(\HOOK_ORDER_XML_UPDATE_CARTITEMS, [
                'newItem' => $cartItems[$i],
                'oldItem' => $oldItem
            ]);
            if (\count($cartItems) < 2) {
                $cartItemAttributes = $this->mapper->mapArray(
                    $xml['tbestellung']['twarenkorbpos'],
                    'twarenkorbposeigenschaft',
                    'mWarenkorbposeigenschaft'
                );
            } else {
                $cartItemAttributes = $this->mapper->mapArray(
                    $xml['tbestellung']['twarenkorbpos'][$i],
                    'twarenkorbposeigenschaft',
                    'mWarenkorbposeigenschaft'
                );
            }
            foreach ($cartItemAttributes as $posAttribute) {
                unset($posAttribute->kWarenkorbPosEigenschaft);
                $posAttribute->kWarenkorbPos = $cartItems[$i]->kWarenkorbPos;
                $this->db->insert('twarenkorbposeigenschaft', $posAttribute);
            }
        }
    }

    private function getShopOrder(int $orderID): ?stdClass
    {
        $order = $this->db->select('tbestellung', 'kBestellung', $orderID);
        if ($order === null || !isset($order->kBestellung) || $order->kBestellung <= 0) {
            return null;
        }
        $order->kBestellung       = (int)$order->kBestellung;
        $order->kWarenkorb        = (int)$order->kWarenkorb;
        $order->kKunde            = (int)$order->kKunde;
        $order->kRechnungsadresse = (int)$order->kRechnungsadresse;
        $order->kLieferadresse    = (int)$order->kLieferadresse;
        $order->kZahlungsart      = (int)$order->kZahlungsart;
        $order->kVersandart       = (int)$order->kVersandart;
        $order->kSprache          = (int)$order->kSprache;
        $order->kWaehrung         = (int)$order->kWaehrung;
        $order->cStatus           = (int)$order->cStatus;

        return $order;
    }

    private function getOrderState(stdClass $shopOrder, stdClass $order): int
    {
        if ($shopOrder->cStatus === \BESTELLUNG_STATUS_STORNO) {
            return \BESTELLUNG_STATUS_STORNO;
        }
        $state = \BESTELLUNG_STATUS_IN_BEARBEITUNG;
        if (isset($order->cBezahlt) && $order->cBezahlt === 'Y') {
            $state = \BESTELLUNG_STATUS_BEZAHLT;
        }
        if (isset($order->dVersandt) && $order->dVersandt !== '') {
            $state = \BESTELLUNG_STATUS_VERSANDT;
        }
        $updatedOrder = new Bestellung($shopOrder->kBestellung, true, $this->db);
        if ((int)($order->nKomplettAusgeliefert ?? -1) === 0 && \count($updatedOrder->oLieferschein_arr) > 0) {
            $state = \BESTELLUNG_STATUS_TEILVERSANDT;
        }

        return $state;
    }

    private function getTrackingURL(stdClass $shopOrder, stdClass $order): string
    {
        $trackingURL = '';
        if (($order->cIdentCode ?? '') !== '') {
            $trackingURL = $order->cLogistikURL;
            if ($shopOrder->kLieferadresse > 0) {
                $deliveryAddress = $this->db->getSingleObject(
                    'SELECT cPLZ
                        FROM tlieferadresse 
                        WHERE kLieferadresse = :dai',
                    ['dai' => $shopOrder->kLieferadresse]
                );
                if ($deliveryAddress !== null && $deliveryAddress->cPLZ) {
                    $trackingURL = \str_replace('#PLZ#', $deliveryAddress->cPLZ, $trackingURL);
                }
            } else {
                $customer    = new Customer($shopOrder->kKunde);
                $trackingURL = \str_replace('#PLZ#', $customer->cPLZ ?? '', $trackingURL);
            }
            $trackingURL = \str_replace('#IdentCode#', $order->cIdentCode, $trackingURL);
        }

        return $trackingURL;
    }

    private function updateOrder(stdClass $shopOrder, stdClass $order, int $state): Bestellung
    {
        $trackingURL = $this->getTrackingURL($shopOrder, $order);
        $methodName  = $this->db->escape($order->cZahlungsartName);
        $clearedDate = $this->db->escape($order->dBezahltDatum);
        $shippedDate = $this->db->escape($order->dVersandt);
        if ($shippedDate === '') {
            $shippedDate = '_DBNULL_';
        }

        $upd                = new stdClass();
        $upd->dVersandDatum = $shippedDate;
        $upd->cTracking     = $this->db->escape($order->cIdentCode);
        $upd->cLogistiker   = $this->db->escape($order->cLogistik);
        $upd->cTrackingURL  = $this->db->escape($trackingURL);
        $upd->cStatus       = $state;
        $upd->cVersandInfo  = $this->db->escape($order->cVersandInfo);
        if ($methodName !== '') {
            $upd->cZahlungsartName = $methodName;
        }
        $upd->dBezahltDatum = empty($clearedDate)
            ? '_DBNULL_'
            : $clearedDate;

        $this->db->update('tbestellung', 'kBestellung', $order->kBestellung, $upd);

        return new Bestellung($shopOrder->kBestellung, true, $this->db);
    }

    private function sendStatusMail(Bestellung $updatedOrder, stdClass $shopOrder, int $state, Customer $customer): void
    {
        $doSend = false;
        foreach ($updatedOrder->oLieferschein_arr as $note) {
            if ($note->getEmailVerschickt() === false) {
                $doSend = true;
                break;
            }
        }
        try {
            $earlier = new DateTime(\date('Y-m-d', \strtotime($updatedOrder->dVersandDatum ?? '1970-01-01') ?: null));
        } catch (Exception) {
            $earlier = new DateTime();
        }
        $now  = new DateTime();
        $diff = $now->diff($earlier)->format('%a');

        if (
            ($state === \BESTELLUNG_STATUS_VERSANDT &&
                $shopOrder->cStatus !== \BESTELLUNG_STATUS_VERSANDT &&
                $diff <= \BESTELLUNG_VERSANDBESTAETIGUNG_MAX_TAGE) ||
            ($state === \BESTELLUNG_STATUS_TEILVERSANDT && $doSend === true)
        ) {
            $mailType = $state === \BESTELLUNG_STATUS_VERSANDT
                ? \MAILTEMPLATE_BESTELLUNG_VERSANDT
                : \MAILTEMPLATE_BESTELLUNG_TEILVERSANDT;
            $module   = $this->getPaymentMethod($shopOrder->kBestellung);
            if (
                !isset($updatedOrder->oVersandart->cSendConfirmationMail)
                || $updatedOrder->oVersandart->cSendConfirmationMail !== 'N'
            ) {
                if ($module !== null) {
                    $module->sendMail($shopOrder->kBestellung, $mailType);
                } else {
                    $data              = new stdClass();
                    $data->tkunde      = $customer;
                    $data->tbestellung = $updatedOrder;

                    try {
                        $mailer = Shop::Container()->getMailer();
                        $mail   = new Mail();
                        $mailer->send($mail->createFromTemplateID($mailType, $data));
                    } catch (Exception) {
                        $this->logger->error('Mailer not available.');
                    }
                }
            }
            foreach ($updatedOrder->oLieferschein_arr as $note) {
                $note->setEmailVerschickt(true)->update();
            }
        }
    }

    private function sendPaymentMail(stdClass $shopOrder, stdClass $order, Customer $customer): void
    {
        if (!$shopOrder->dBezahltDatum && $order->dBezahltDatum && $customer->kKunde > 0) {
            try {
                $earlier = new DateTime(\date('Y-m-d', \strtotime($order->dBezahltDatum)));
            } catch (Exception) {
                $earlier = new DateTime();
            }
            $now  = new DateTime();
            $diff = $now->diff($earlier)->format('%a');

            if ($diff >= \BESTELLUNG_ZAHLUNGSBESTAETIGUNG_MAX_TAGE) {
                return;
            }

            $module = $this->getPaymentMethod($order->kBestellung);
            if ($module !== null) {
                $module->sendMail($order->kBestellung, \MAILTEMPLATE_BESTELLUNG_BEZAHLT);
            } else {
                $updatedOrder = new Bestellung((int)$shopOrder->kBestellung, true, $this->db);
                if (
                    $updatedOrder->Zahlungsart !== null
                    && ($updatedOrder->Zahlungsart->nMailSenden & \ZAHLUNGSART_MAIL_EINGANG)
                    && $customer->cMail !== ''
                ) {
                    $data              = new stdClass();
                    $data->tkunde      = $customer;
                    $data->tbestellung = $updatedOrder;

                    try {
                        $mailer = Shop::Container()->getMailer();
                        $mail   = new Mail();
                        $mailer->send($mail->createFromTemplateID(\MAILTEMPLATE_BESTELLUNG_BEZAHLT, $data));
                    } catch (Exception) {
                        $this->logger->error('Mailer not available.');
                    }
                }
            }
        }
    }

    /**
     * @param array<mixed> $xml
     */
    private function handleSet(array $xml): void
    {
        $orders  = $this->mapper->mapArray($xml['tbestellungen'], 'tbestellung', 'mBestellung');
        $service = Shop::Container()->getPasswordService();
        foreach ($orders as $order) {
            $order->kBestellung = (int)$order->kBestellung;
            $shopOrder          = $this->getShopOrder($order->kBestellung);
            if ($shopOrder === null) {
                continue;
            }
            $state = $this->getOrderState($shopOrder, $order);
            \executeHook(\HOOK_BESTELLUNGEN_XML_BESTELLSTATUS, [
                'status'      => &$state,
                'oBestellung' => &$shopOrder
            ]);
            $updatedOrder = $this->updateOrder($shopOrder, $order, $state);
            $customer     = null;
            if (
                (!$shopOrder->dVersandDatum && $order->dVersandt)
                || (!$shopOrder->dBezahltDatum && $order->dBezahltDatum)
            ) {
                $tmp = $this->db->getSingleObject(
                    'SELECT kKunde FROM tbestellung WHERE kBestellung = :oid',
                    ['oid' => $order->kBestellung]
                );
                if ($tmp !== null) {
                    $customer = new Customer((int)$tmp->kKunde, $service, $this->db);
                }
            }
            if ($customer === null) {
                $customer = new Customer($shopOrder->kKunde, $service, $this->db);
            }
            $this->sendStatusMail($updatedOrder, $shopOrder, $state, $customer);
            $this->sendPaymentMail($shopOrder, $order, $customer);

            \executeHook(\HOOK_BESTELLUNGEN_XML_BEARBEITESET, [
                'oBestellung'     => &$shopOrder,
                'oKunde'          => &$customer,
                'oBestellungWawi' => &$order
            ]);
        }
    }

    private function deleteOrder(int $orderID): void
    {
        $customerID = (int)($this->db->getSingleObject(
            'SELECT tbestellung.kKunde
                FROM tbestellung
                INNER JOIN tkunde ON tbestellung.kKunde = tkunde.kKunde
                WHERE tbestellung.kBestellung = :oid
                    AND tkunde.nRegistriert = 0',
            ['oid' => $orderID]
        )->kKunde ?? 0);
        $cartID     = (int)($this->db->select(
            'tbestellung',
            'kBestellung',
            $orderID,
            null,
            null,
            null,
            null,
            false,
            'kWarenkorb'
        )->kWarenkorb ?? 0);

        \executeHook(\HOOK_BESTELLUNGEN_XML_DELETEORDER, [
            'orderId' => $orderID
        ]);

        $this->db->delete('tbestellung', 'kBestellung', $orderID);
        $this->db->delete('tbestellid', 'kBestellung', $orderID);
        $this->db->delete('tbestellstatus', 'kBestellung', $orderID);
        $this->db->delete('tkuponbestellung', 'kBestellung', $orderID);
        $this->db->delete('tuploaddatei', ['kCustomID', 'nTyp'], [$orderID, \UPLOAD_TYP_BESTELLUNG]);
        $this->db->delete('tuploadqueue', 'kBestellung', $orderID);
        if ($cartID > 0) {
            $this->db->delete('twarenkorb', 'kWarenkorb', $cartID);
            $this->db->delete('twarenkorbpos', 'kWarenkorb', $cartID);
            foreach (
                $this->db->selectAll(
                    'twarenkorbpos',
                    'kWarenkorb',
                    $cartID,
                    'kWarenkorbPos'
                ) as $item
            ) {
                $this->db->delete(
                    'twarenkorbposeigenschaft',
                    'kWarenkorbPos',
                    (int)$item->kWarenkorbPos
                );
            }
            $this->db->delete('tgratisgeschenk', 'kWarenkorb', $cartID);
        }
        if ($customerID > 0) {
            (new Customer($customerID))->deleteAccount(Journal::ISSUER_TYPE_DBES, $orderID);
        }
    }

    /**
     * @param stdClass[] $orderAttributes
     */
    private function editAttributes(int $orderID, array $orderAttributes): void
    {
        $updated = [];
        foreach ($orderAttributes as $orderAttributeData) {
            $orderAttribute    = (object)$orderAttributeData;
            $orderAttributeOld = $this->db->select(
                'tbestellattribut',
                ['kBestellung', 'cName'],
                [$orderID, $orderAttribute->key]
            );
            if ($orderAttributeOld !== null && isset($orderAttributeOld->kBestellattribut)) {
                $this->db->update(
                    'tbestellattribut',
                    'kBestellattribut',
                    (int)$orderAttributeOld->kBestellattribut,
                    (object)['cValue' => $orderAttribute->value]
                );
                $updated[] = (int)$orderAttributeOld->kBestellattribut;
            } else {
                $updated[] = $this->db->insert(
                    'tbestellattribut',
                    (object)[
                        'kBestellung' => $orderID,
                        'cName'       => $orderAttribute->key,
                        'cValue'      => $orderAttribute->value,
                    ]
                );
            }
        }

        if (\count($updated) > 0) {
            $this->db->queryPrepared(
                'DELETE FROM tbestellattribut
                    WHERE kBestellung = :oid
                        AND kBestellattribut NOT IN (' . \implode(', ', $updated) . ')',
                ['oid' => $orderID]
            );
        } else {
            $this->db->delete('tbestellattribut', 'kBestellung', $orderID);
        }
    }
}
