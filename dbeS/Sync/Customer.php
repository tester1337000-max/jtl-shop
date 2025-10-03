<?php

declare(strict_types=1);

namespace JTL\dbeS\Sync;

use JTL\Catalog\Currency;
use JTL\Catalog\Product\Preise;
use JTL\Checkout\Adresse;
use JTL\Customer\Customer as CustomerClass;
use JTL\Customer\CustomerAttribute;
use JTL\Customer\CustomerField;
use JTL\Customer\DataHistory;
use JTL\dbeS\Starter;
use JTL\GeneralDataProtection\Journal;
use JTL\Helpers\GeneralObject;
use JTL\Helpers\Text;
use JTL\Language\LanguageHelper;
use JTL\Mail\Mail\Mail;
use JTL\Services\JTL\CryptoServiceInterface;
use JTL\Shop;
use JTL\SimpleMail;
use JTL\XML;
use stdClass;

/**
 * Class Customer
 * @package JTL\dbeS\Sync
 */
final class Customer extends AbstractSync
{
    /**
     * @return array<mixed>|null
     */
    public function handle(Starter $starter): ?array
    {
        foreach ($starter->getXML() as $item) {
            /**
             * @var string               $file
             * @var array<string, mixed> $xml
             */
            [$file, $xml] = [\key($item), \reset($item)];

            $fileName = \pathinfo($file, \PATHINFO_BASENAME);
            // the first 5 cases come from Kunden_xml.php
            if ($fileName === 'del_kunden.xml') {
                $this->handleDeletes($xml);
            } elseif ($fileName === 'ack_kunden.xml') {
                $this->handleACKCustomer($xml);
            } elseif ($fileName === 'ack_deleted_customers.xml') {
                $this->handleACKDeleted($xml);
            } elseif ($fileName === 'gutscheine.xml') {
                $this->handleVouchers($xml);
            } elseif ($fileName === 'aktiviere_kunden.xml') {
                $this->activate($xml);
            } elseif ($fileName === 'passwort_kunden.xml') {
                $this->generatePasswords($xml);
            } else {
                return $this->handleInserts($xml); // from SetKunde_xml.php
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $xml
     */
    private function activate(array $xml): void
    {
        $customers = $this->mapper->mapArray($xml['aktiviere_kunden'], 'tkunde', '');
        $service   = Shop::Container()->getPasswordService();
        foreach ($customers as $customerData) {
            if (!($customerData->kKunde > 0 && $customerData->kKundenGruppe > 0)) {
                continue;
            }
            $customerData->kKunde        = (int)$customerData->kKunde;
            $customerData->kKundenGruppe = (int)$customerData->kKundenGruppe;

            $customer = new CustomerClass($customerData->kKunde, $service, $this->db);
            if ($customer->kKunde > 0 && $customer->kKundengruppe !== $customerData->kKundenGruppe) {
                $this->db->update(
                    'tkunde',
                    'kKunde',
                    $customerData->kKunde,
                    (object)['kKundengruppe' => $customerData->kKundenGruppe]
                );
                $customer->kKundengruppe = $customerData->kKundenGruppe;
                $obj                     = new stdClass();
                $obj->tkunde             = $customer;
                if ($customer->cMail) {
                    $mailer = Shop::Container()->getMailer();
                    $mail   = new Mail();
                    $mailer->send($mail->createFromTemplateID(\MAILTEMPLATE_KUNDENGRUPPE_ZUWEISEN, $obj));
                }
            }
            $this->db->update('tkunde', 'kKunde', $customerData->kKunde, (object)['cAktiv' => 'Y']);
        }
    }

    /**
     * @param array<mixed> $xml
     */
    private function generatePasswords(array $xml): void
    {
        $customers = $this->mapper->mapArray($xml['passwort_kunden'], 'tkunde', '');
        $service   = Shop::Container()->getPasswordService();
        foreach ($customers as $customerData) {
            if (empty($customerData->kKunde)) {
                continue;
            }
            $customer = new CustomerClass((int)$customerData->kKunde, $service, $this->db);
            if ($customer->nRegistriert === 1 && $customer->cMail) {
                $customer->prepareResetPassword(false);
            } else {
                \syncException(
                    'Kunde hat entweder keine Emailadresse oder es ist ein unregistrierter Kunde',
                    \FREIDEFINIERBARER_FEHLER
                );
            }
        }
    }

    /**
     * @param array<mixed> $xml
     */
    private function handleDeletes(array $xml): void
    {
        $source = $xml['del_kunden']['kKunde'] ?? null;
        if ($source === null) {
            return;
        }
        if (!\is_array($source)) {
            $source = [$source];
        }
        $service = Shop::Container()->getPasswordService();
        foreach (\array_filter($source, '\is_numeric') as $customerID) {
            $customer = new CustomerClass((int)$customerID, $service, $this->db);
            $customer->deleteAccount(Journal::ISSUER_TYPE_DBES, 0, true);
        }
    }

    /**
     * @param array<mixed> $xml
     */
    private function handleACKCustomer(array $xml): void
    {
        $definition = [
            'table' => 'tkunde',
            'field' => 'cAbgeholt',
            'value' => 'Y',
        ];
        $this->handleACK($xml, $definition);
    }

    /**
     * @param array<mixed> $xml
     */
    private function handleACKDeleted(array $xml): void
    {
        $definition = [
            'table' => 'deleted_customers',
            'field' => 'ack',
            'value' => 1,
        ];
        $this->handleACK($xml, $definition);
    }

    /**
     * @param array<mixed>                                           $xml
     * @param array{table: string, field: string, value: int|string} $definition
     */
    private function handleACK(array $xml, array $definition): void
    {
        $source = $xml['ack_kunden']['kKunde'] ?? null;
        if ($source === null) {
            return;
        }
        if (!\is_array($source)) {
            $source = [$source];
        }
        foreach (\array_filter($source, '\is_numeric') as $customerID) {
            $this->db->update(
                $definition['table'],
                'kKunde',
                (int)$customerID,
                (object)[$definition['field'] => $definition['value']]
            );
        }
    }

    /**
     * @param array<mixed> $xml
     */
    private function handleVouchers(array $xml): void
    {
        if (!isset($xml['gutscheine']['gutschein']) || !\is_array($xml['gutscheine']['gutschein'])) {
            return;
        }
        $mailer          = Shop::Container()->getMailer();
        $defaultCurrency = (new Currency())->getDefault();
        foreach ($this->mapper->mapArray($xml['gutscheine'], 'gutschein', 'mGutschein') as $voucher) {
            if (!($voucher->kGutschein > 0 && $voucher->kKunde > 0)) {
                continue;
            }
            $exists = $this->db->select('tgutschein', 'kGutschein', (int)$voucher->kGutschein);
            if ($exists !== null && !empty($exists->kGutschein)) {
                continue;
            }
            $this->db->insert('tgutschein', $voucher);
            $this->logger->debug(
                'Gutschein fuer kKunde ' .
                (int)$voucher->kKunde . ' wurde eingeloest. ' .
                \print_r($voucher, true)
            );
            $this->db->query(
                'UPDATE tkunde 
                    SET fGuthaben = fGuthaben + ' . (float)$voucher->fWert . ' 
                    WHERE kKunde = ' . (int)$voucher->kKunde
            );
            $this->db->queryPrepared(
                'UPDATE tkunde 
                    SET fGuthaben = 0 
                    WHERE kKunde = :cid 
                        AND fGuthaben < 0',
                ['cid' => (int)$voucher->kKunde]
            );
            $voucher->cLocalizedWert = Preise::getLocalizedPriceString($voucher->fWert, $defaultCurrency, false);
            $customer                = new CustomerClass((int)$voucher->kKunde);
            $obj                     = new stdClass();
            $obj->tkunde             = $customer;
            $obj->tgutschein         = $voucher;
            if ($customer->cMail) {
                $mail = new Mail();
                $mailer->send($mail->createFromTemplateID(\MAILTEMPLATE_GUTSCHEIN, $obj));
            }
        }
    }

    /**
     * @param array<mixed> $xml
     * @return array<mixed>
     */
    private function handleInserts(array $xml): array
    {
        $source = $xml['tkunde'] ?? null;
        $res    = [];
        if (!\is_array($source)) {
            return $res;
        }
        $customer = $this->getCustomerObject($xml);
        $this->mapper->mapObject($customer, $source, 'mKunde');
        $customerAttributes = $this->getCustomerAttributes($source);
        $customer->cAnrede  = $this->mapSalutation($customer->cAnrede);

        $lang = $this->db->select('tsprache', 'kSprache', $customer->kSprache);
        if ($lang === null || empty($lang->kSprache)) {
            $customer->kSprache = (int)($this->db->select('tsprache', 'cShopStandard', 'Y')->kSprache ?? 0);
        }

        \executeHook(\HOOK_SYNC_HANDLE_CUSTOMER_INSERTS, [
            'customer'   => $customer,
            'attributes' => &$customerAttributes,
        ]);

        $kInetKunde  = (int)($xml['tkunde attr']['kKunde'] ?? 0);
        $oldCustomer = new CustomerClass($kInetKunde);
        // Kunde existiert mit dieser kInetKunde
        // Kunde wird aktualisiert bzw. seine KdGrp wird geändert
        if ($oldCustomer->kKunde > 0) {
            $res = $this->merge($customer, $oldCustomer, $kInetKunde, $customerAttributes, $res);
        } else {
            // Kunde existiert mit dieser kInetKunde im Shop nicht. Gib diese Info zurück an Wawi
            if ($kInetKunde > 0) {
                $res['keys']['tkunde attr']['kKunde'] = 0;
                $res['keys']['tkunde']                = '';
                $this->logger->error(
                    'Verknuepfter Kunde in Wawi existiert nicht im Shop: ' .
                    XML::serialize($res)
                );

                return $res;
            }
            // Kunde existiert nicht im Shop - check, ob email schon belegt
            $oldCustomer = $this->db->select(
                'tkunde',
                'nRegistriert',
                1,
                'cMail',
                $customer->cMail,
                null,
                null,
                false,
                'kKunde'
            );
            if (isset($oldCustomer->kKunde) && $oldCustomer->kKunde > 0) {
                // Email vergeben -> Kunde wird nicht neu angelegt, sondern der Kunde wird an Wawi zurückgegeben
                return $this->notifyDuplicateCustomer($oldCustomer);
            }
            // Email noch nicht belegt, der Kunde muss neu erstellt werden -> KUNDE WIRD NEU ERSTELLT
            $kInetKunde = $this->addNewCustomer($customer, $customerAttributes);

            $res['keys']['tkunde attr']['kKunde'] = $kInetKunde;
            $res['keys']['tkunde']                = '';
        }

        return $kInetKunde > 0 ? $this->addAddressData($kInetKunde, $res, $source) : $res;
    }

    /**
     * @param array<mixed> $xml
     */
    private function getCustomerObject(array $xml): CustomerClass
    {
        $customer                = new CustomerClass();
        $customer->kKundengruppe = 0;
        if (isset($xml['tkunde attr']) && \is_array($xml['tkunde attr'])) {
            $customer->kKundengruppe = (int)$xml['tkunde attr']['kKundengruppe'];
            $customer->kSprache      = (int)$xml['tkunde attr']['kSprache'];
        }

        return $customer;
    }

    /**
     * @param stdClass $oldCustomer
     * @return array<mixed>
     */
    private function notifyDuplicateCustomer(stdClass $oldCustomer): array
    {
        $cstmr  = $this->db->getArrays(
            "SELECT kKunde, kKundengruppe, kSprache, cKundenNr, cPasswort, cAnrede, cTitel, cVorname,
                    cNachname, cFirma, cZusatz, cStrasse, cHausnummer, cAdressZusatz, cPLZ, cOrt, cBundesland, 
                    cLand, cTel, cMobil, cFax, cMail, cUSTID, cWWW, fGuthaben, cNewsletter, dGeburtstag, fRabatt,
                    cHerkunft, dErstellt, dVeraendert, cAktiv, cAbgeholt,
                    date_format(dGeburtstag, '%d.%m.%Y') AS dGeburtstag_formatted, nRegistriert
                FROM tkunde
                WHERE kKunde = :cid",
            ['cid' => (int)$oldCustomer->kKunde]
        );
        $crypto = Shop::Container()->getCryptoService();

        $cstmr[0]['cNachname'] = \trim($crypto->decryptXTEA($cstmr[0]['cNachname']));
        $cstmr[0]['cFirma']    = \trim($crypto->decryptXTEA($cstmr[0]['cFirma']));
        $cstmr[0]['cZusatz']   = \trim($crypto->decryptXTEA($cstmr[0]['cZusatz']));
        $cstmr[0]['cStrasse']  = \trim($crypto->decryptXTEA($cstmr[0]['cStrasse']));
        $cstmr[0]['cAnrede']   = CustomerClass::mapSalutation($cstmr[0]['cAnrede'], (int)$cstmr[0]['kSprache']);
        // Strasse und Hausnummer zusammenführen
        $cstmr[0]['cStrasse'] .= ' ' . $cstmr[0]['cHausnummer'];
        unset($cstmr[0]['cHausnummer']);
        // Land ausgeschrieben der Wawi geben
        $cstmr[0]['cLand'] = LanguageHelper::getCountryCodeByCountryName($cstmr[0]['cLand']);
        unset($cstmr[0]['cPasswort']);
        $cstmr['0 attr']             = $this->buildAttributes($cstmr[0]);
        $cstmr[0]['tkundenattribut'] = $this->db->getArrays(
            'SELECT *
                FROM tkundenattribut
                 WHERE kKunde = :cid',
            ['cid' => (int)$cstmr['0 attr']['kKunde']]
        );
        foreach ($cstmr[0]['tkundenattribut'] as $o => $attr) {
            $cstmr[0]['tkundenattribut'][$o . ' attr'] = $this->buildAttributes($attr);
        }
        $xml                          = [];
        $xml['kunden attr']['anzahl'] = 1;
        $xml['kunden']['tkunde']      = $cstmr;
        $this->logger->error('Dieser Kunde existiert: ' . XML::serialize($xml));

        return $xml;
    }

    /**
     * @param array<mixed> $source
     * @return stdClass[]
     */
    private function getCustomerAttributes(array $source): array
    {
        $customerAttributes = [];
        if (GeneralObject::hasCount('tkundenattribut', $source)) {
            $members = \array_keys($source['tkundenattribut']);
            if ($members[0] === 0) {
                foreach ($source['tkundenattribut'] as $data) {
                    $customerAttribute        = new stdClass();
                    $customerAttribute->cName = $data['cName'];
                    $customerAttribute->cWert = $data['cWert'];
                    $customerAttributes[]     = $customerAttribute;
                }
            } else {
                $customerAttribute        = new stdClass();
                $customerAttribute->cName = $source['tkundenattribut']['cName'];
                $customerAttribute->cWert = $source['tkundenattribut']['cWert'];
                $customerAttributes[]     = $customerAttribute;
            }
        }

        return $customerAttributes;
    }

    /**
     * @param stdClass[] $customerAttributes
     */
    private function addNewCustomer(CustomerClass $customer, array $customerAttributes): int
    {
        $passwordService             = Shop::Container()->getPasswordService();
        $customer->dErstellt         = 'NOW()';
        $customer->cPasswortKlartext = $passwordService->generate(12);
        $customer->cPasswort         = $passwordService->hash($customer->cPasswortKlartext);
        $customer->nRegistriert      = 1;
        $customer->cAbgeholt         = 'Y';
        $customer->cAktiv            = 'Y';
        $customer->cSperre           = 'N';
        $this->extractStreet($customer);
        // mail an Kunden mit Accounterstellung durch Shopbetreiber
        $obj         = new stdClass();
        $obj->tkunde = $customer;
        if ($customer->cMail) {
            $mailer = Shop::Container()->getMailer();
            $mail   = new Mail();
            $mailer->send($mail->createFromTemplateID(\MAILTEMPLATE_ACCOUNTERSTELLUNG_DURCH_BETREIBER, $obj));
        }
        unset($customer->cPasswortKlartext, $customer->Anrede);
        $kInetKunde = $customer->insertInDB();
        if (\count($customerAttributes) > 0) {
            $this->saveAttribute((int)$customer->kKunde, (int)$customer->kSprache, $customerAttributes);
        }

        return $kInetKunde;
    }

    /**
     * @param CustomerClass $customer
     * @param CustomerClass $oldCustomer
     * @param int           $kInetKunde
     * @param array<mixed>  $customerAttributes
     * @param array<mixed>  $res
     * @return array<mixed>
     */
    private function merge(
        CustomerClass $customer,
        CustomerClass $oldCustomer,
        int $kInetKunde,
        array $customerAttributes,
        array $res
    ): array {
        // Angaben vom alten Kunden übernehmen
        $customer->kKunde      = $kInetKunde;
        $customer->cAbgeholt   = 'Y';
        $customer->cAktiv      = 'Y';
        $customer->dVeraendert = 'NOW()';

        if ($customer->cMail !== $oldCustomer->cMail) {
            // E-Mail Adresse geändert - Verwendung prüfen!
            if (
                Text::filterEmailAddress($customer->cMail) === false
                || SimpleMail::checkBlacklist($customer->cMail)
                || $this->db->select('tkunde', 'cMail', $customer->cMail, 'nRegistriert', 1) !== null
            ) {
                // E-Mail ist invalide, blacklisted bzw. wird bereits im Shop verwendet
                $res['keys']['tkunde attr']['kKunde'] = 0;
                $res['keys']['tkunde']                = '';

                return $res;
            }
            // Mail an Kunden mit Info, dass Zugang verändert wurde
            $obj         = new stdClass();
            $obj->tkunde = $customer;

            $mailer = Shop::Container()->getMailer();
            $mail   = new Mail();
            $mailer->send($mail->createFromTemplateID(\MAILTEMPLATE_ACCOUNTERSTELLUNG_DURCH_BETREIBER, $obj));
        }

        $customer->cPasswort    = $oldCustomer->cPasswort;
        $customer->nRegistriert = $oldCustomer->nRegistriert;
        $customer->dErstellt    = $oldCustomer->dErstellt;
        $customer->fGuthaben    = $oldCustomer->fGuthaben;
        $customer->cHerkunft    = $oldCustomer->cHerkunft;
        // schaue, ob dieser Kunde diese Kundengruppe schon hat
        if ($oldCustomer->kKundengruppe !== $customer->kKundengruppe && $customer->cMail) {
            // Mail an Kunden mit Info, dass Kundengruppe verändert wurde
            $obj         = new stdClass();
            $obj->tkunde = $customer;

            $mailer = Shop::Container()->getMailer();
            $mail   = new Mail();
            $mailer->send($mail->createFromTemplateID(\MAILTEMPLATE_KUNDENGRUPPE_ZUWEISEN, $obj));
        }
        // Hausnummer extrahieren
        $this->extractStreet($customer);
        // Workaround for WAWI-39370
        $customer->cLand = Adresse::checkISOCountryCode($customer->cLand ?? '');
        // $this->upsert('tkunde', [$Kunde], 'kKunde');
        $customer->updateInDB();
        DataHistory::saveHistory($oldCustomer, $customer, DataHistory::QUELLE_DBES);
        if (\count($customerAttributes) > 0) {
            $this->saveAttribute((int)$customer->kKunde, (int)$customer->kSprache, $customerAttributes);
        }
        $res['keys']['tkunde attr']['kKunde'] = $kInetKunde;
        $res['keys']['tkunde']                = '';

        return $res;
    }

    /**
     * @param int          $kInetKunde
     * @param array<mixed> $res
     * @param array<mixed> $source
     * @return array<mixed>
     */
    private function addAddressData(int $kInetKunde, array $res, array $source): array
    {
        // kunde akt. bzw. neu inserted
        $service = Shop::Container()->getCryptoService();
        if (
            GeneralObject::hasCount('tadresse', $source)
            && (!isset($source['tadresse attr']) || !\is_array($source['tadresse attr']))
        ) {
            // mehrere adressen
            $nr           = 0;
            $addressCount = \count($source['tadresse']) / 2;
            for ($i = 0; $i < $addressCount; $i++) {
                $deliveryAddress         = new stdClass();
                $deliveryAddress->kKunde = $kInetKunde;
                if ($source['tadresse'][$i . ' attr']['kInetAdresse'] > 0) {
                    $deliveryAddress->kLieferadresse = $source['tadresse'][$i . ' attr']['kInetAdresse'];
                    $this->mapper->mapObject($deliveryAddress, $source['tadresse'][$i], 'mLieferadresse');
                    $deliveryAddress = $this->getDeliveryAddress($deliveryAddress, $service);
                    $this->upsert('tlieferadresse', [$deliveryAddress], 'kLieferadresse');
                } else {
                    $this->mapper->mapObject($deliveryAddress, $source['tadresse'][$i], 'mLieferadresse');
                    $deliveryAddress    = $this->getDeliveryAddress($deliveryAddress, $service);
                    $kInetLieferadresse = $this->db->insert('tlieferadresse', $deliveryAddress);
                    if ($kInetLieferadresse > 0) {
                        if (!\is_array($res['keys']['tkunde'])) {
                            $res['keys']['tkunde'] = ['tadresse' => []];
                        }
                        $res['keys']['tkunde']['tadresse'][$nr . ' attr'] = [
                            'kAdresse'     => $source['tadresse'][$i . ' attr']['kAdresse'],
                            'kInetAdresse' => $kInetLieferadresse,
                        ];
                        $res['keys']['tkunde']['tadresse'][$nr]           = '';
                        $nr++;
                    }
                }
            }

            return $res;
        }
        if (GeneralObject::isCountable('tadresse attr', $source)) {
            // nur eine lieferadresse
            $deliveryAddress         = new stdClass();
            $deliveryAddress->kKunde = $kInetKunde;
            if ($source['tadresse attr']['kInetAdresse'] > 0) {
                $deliveryAddress->kLieferadresse = $source['tadresse attr']['kInetAdresse'];
                $this->mapper->mapObject($deliveryAddress, $source['tadresse'], 'mLieferadresse');
                $deliveryAddress = $this->getDeliveryAddress($deliveryAddress, $service);
                $this->upsert('tlieferadresse', [$deliveryAddress], 'kLieferadresse');
            } else {
                $this->mapper->mapObject($deliveryAddress, $source['tadresse'], 'mLieferadresse');
                $deliveryAddress    = $this->getDeliveryAddress($deliveryAddress, $service);
                $kInetLieferadresse = $this->db->insert('tlieferadresse', $deliveryAddress);
                if ($kInetLieferadresse > 0) {
                    $res['keys']['tkunde'] = [
                        'tadresse attr' => [
                            'kAdresse'     => $source['tadresse attr']['kAdresse'],
                            'kInetAdresse' => $kInetLieferadresse,
                        ],
                        'tadresse'      => '',
                    ];
                }
            }
        }

        return $res;
    }

    private function getDeliveryAddress(stdClass $address, CryptoServiceInterface $crypto): stdClass
    {
        $this->extractStreet($address);
        $address->cNachname = $crypto->encryptXTEA(\trim($address->cNachname));
        $address->cFirma    = $crypto->encryptXTEA(\trim($address->cFirma));
        $address->cZusatz   = $crypto->encryptXTEA(\trim($address->cZusatz));
        $address->cStrasse  = $crypto->encryptXTEA(\trim($address->cStrasse));
        $address->cAnrede   = $this->mapSalutation($address->cAnrede);
        // Workaround for WAWI-39370
        $address->cLand = Adresse::checkISOCountryCode($address->cLand);

        return $address;
    }

    /**
     * @param int        $customerID
     * @param int        $languageID
     * @param stdClass[] $attributes
     */
    private function saveAttribute(int $customerID, int $languageID, array $attributes): void
    {
        if ($customerID <= 0 || $languageID <= 0) {
            return;
        }
        foreach ($attributes as $attribute) {
            $field = CustomerField::loadByName($attribute->cName, $languageID);
            if ($field->getID() > 0 && $field->validate($attribute->cWert) === CustomerField::VALIDATE_OK) {
                $customerAttr = new CustomerAttribute();
                $customerAttr->setCustomerID($customerID);
                $customerAttr->setCustomerFieldID($field->getID());
                $customerAttr->setName($attribute->cName);
                $customerAttr->setValue($attribute->cWert);
                $customerAttr->save();
            }
        }
    }
}
