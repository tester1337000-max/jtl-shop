<?php

declare(strict_types=1);

namespace JTL\Checkout;

use JTL\Campaign;
use JTL\Cart\Cart;
use JTL\Cart\CartHelper;
use JTL\Cart\CartItem;
use JTL\Catalog\Product\Preise;
use JTL\Catalog\Wishlist\Wishlist;
use JTL\CheckBox;
use JTL\Customer\Customer;
use JTL\DB\DbInterface;
use JTL\Extensions\Upload\Upload;
use JTL\Helpers\Date;
use JTL\Helpers\Order;
use JTL\Helpers\Request;
use JTL\Helpers\Tax;
use JTL\Helpers\Text;
use JTL\Language\LanguageHelper;
use JTL\Mail\Mail\Mail;
use JTL\Session\Frontend;
use JTL\Settings\Option\Globals;
use JTL\Settings\Settings;
use JTL\Shop;
use Monolog\Logger;
use stdClass;

/**
 * Class OrderHandler
 * @package JTL\Checkout
 */
class OrderHandler
{
    private int $languageID;

    private StockUpdater $stockUpdater;

    /**
     * @var string[]
     */
    private array $tagsToFlush = [];

    public function __construct(
        private readonly DbInterface $db,
        private Customer $customer,
        private readonly Cart $cart
    ) {
        $this->languageID   = Shop::getLanguageID();
        $this->stockUpdater = new StockUpdater($db, $customer, $cart);
    }

    /**
     * @former bestellungInDB()
     * @since 5.2.0
     */
    public function persistOrder(bool $cleared = false, ?string $orderNo = null): void
    {
        $this->unhtmlSession();
        $order             = new Bestellung(0, false, $this->db);
        $customer          = $this->customer;
        $deliveryAddress   = Frontend::getDeliveryAddress();
        $order->cBestellNr = $this->createOrderNo($orderNo);
        $cartItems         = [];
        if ($this->customer->getID() <= 0) {
            $customerAttributes      = $customer->getCustomerAttributes();
            $customer->kKundengruppe = $this->customer->getGroupID();
            $customer->kSprache      = $this->languageID;
            $customer->cAbgeholt     = 'N';
            $customer->cAktiv        = 'Y';
            $customer->cSperre       = 'N';
            $customer->dErstellt     = 'NOW()';
            $customer->nRegistriert  = 0;
            $cPasswortKlartext       = '';
            if ($customer->cPasswort) {
                $customer->nRegistriert = 1;
                $cPasswortKlartext      = $customer->cPasswort;
                $customer->cPasswort    = \md5($customer->cPasswort);
            }
            $this->cart->kKunde = $customer->insertInDB();
            if (Frontend::get('customerAttributes') !== null) {
                $customerAttributes->assign(Frontend::get('customerAttributes'));
            }
            $customer->kKunde = $this->cart->kKunde;
            $customer->cLand  = $customer->pruefeLandISO($customer->cLand);
            $customerAttributes->setCustomerID($customer->kKunde);
            $customerAttributes->save();
            Frontend::set('customerAttributes', null);

            if (!empty($customer->cPasswort)) {
                $customer->cPasswortKlartext = $cPasswortKlartext;

                $obj         = new stdClass();
                $obj->tkunde = $customer;

                \executeHook(\HOOK_BESTELLABSCHLUSS_INC_BESTELLUNGINDB_NEUKUNDENREGISTRIERUNG);

                $mailer = Shop::Container()->getMailer();
                $mail   = new Mail();
                $mailer->send($mail->createFromTemplateID(\MAILTEMPLATE_NEUKUNDENREGISTRIERUNG, $obj));
            }
        } else {
            $this->cart->kKunde = $customer->kKunde;
            $this->db->update(
                'tkunde',
                'kKunde',
                (int)$customer->kKunde,
                (object)['cAbgeholt' => 'N']
            );
        }

        $deliveryAddressID = (int)(
            $_SESSION['Bestellung']->kLieferadresse ?? $deliveryAddress->kLieferadresse ?? '0'
        );
        if ($deliveryAddressID > 0) {
            \executeHook(
                \HOOK_BESTELLABSCHLUSS_INC_BESTELLUNGINDB_LIEFERADRESSE_ALT,
                ['deliveryAddressID' => $deliveryAddressID]
            );
            $this->cart->kLieferadresse = $deliveryAddressID;
        } else {
            $deliveryAddress->kKunde = $this->cart->kKunde;
            \executeHook(
                \HOOK_BESTELLABSCHLUSS_INC_BESTELLUNGINDB_LIEFERADRESSE_NEU,
                ['deliveryAddress' => $deliveryAddress]
            );
            $this->cart->kLieferadresse = $deliveryAddress->insertInDB();
            if (isset($_SESSION['newShippingAddressPreset'])) {
                $deliveryAddressTemplate = DeliveryAddressTemplate::createFromObject($deliveryAddress);
                if ((int)($_SESSION['setAsDefaultShippingAddressPreset'] ?? 0) === 1) {
                    $deliveryAddressTemplate->nIstStandardLieferadresse = 1;
                    unset($_SESSION['setAsDefaultShippingAddressPreset']);
                }
                $deliveryAddressTemplate->kKunde = $this->cart->kKunde;
                $deliveryAddressTemplate->persist();
                unset($_SESSION['newShippingAddressPreset']);
            }
        }
        // füge Warenkorb ein
        \executeHook(\HOOK_BESTELLABSCHLUSS_INC_WARENKORBINDB, ['oWarenkorb' => $this->cart, 'oBestellung' => &$order]);
        $this->cart->kWarenkorb = $this->cart->insertInDB();
        // füge alle Warenkorbpositionen ein
        $this->tagsToFlush = [];
        $langCode          = Shop::getLanguageCode();
        if (\is_array($this->cart->PositionenArr) && \count($this->cart->PositionenArr) > 0) {
            $productFilter = Settings::intValue(Globals::PRODUCT_VIEW_FILTER);
            foreach ($this->cart->PositionenArr as $item) {
                if ($item->nPosTyp === \C_WARENKORBPOS_TYP_ARTIKEL) {
                    $item->fLagerbestandVorAbschluss = $item->Artikel->fLagerbestand !== null
                        ? (double)$item->Artikel->fLagerbestand
                        : 0;
                }
                $item->cName         = Text::unhtmlentities($item->getName($langCode));
                $item->cLieferstatus = Text::unhtmlentities($item->cLieferstatus[$langCode] ?? '');
                $item->kWarenkorb    = $this->cart->kWarenkorb;
                $item->fMwSt         = Tax::getSalesTax($item->kSteuerklasse);
                $item->kWarenkorbPos = $item->insertInDB();
                if (\count($item->WarenkorbPosEigenschaftArr) > 0) {
                    $idx = Shop::getLanguageCode();
                    // Bei einem Varkombikind dürfen nur FREIFELD oder PFLICHT-FREIFELD gespeichert werden,
                    // da sonst eventuelle Aufpreise in der Wawi doppelt berechnet werden
                    if (isset($item->Artikel->kVaterArtikel) && $item->Artikel->kVaterArtikel > 0) {
                        foreach ($item->WarenkorbPosEigenschaftArr as $itm) {
                            if ($itm->cTyp !== 'FREIFELD' && $itm->cTyp !== 'PFLICHT-FREIFELD') {
                                continue;
                            }
                            $itm->kWarenkorbPos        = $item->kWarenkorbPos;
                            $itm->cEigenschaftName     = \is_array($itm->cEigenschaftName)
                                ? $itm->cEigenschaftName[$idx]
                                : $itm->cEigenschaftName;
                            $itm->cEigenschaftWertName = \is_array($itm->cEigenschaftWertName)
                                ? $itm->cEigenschaftWertName[$idx]
                                : $itm->cEigenschaftWertName;
                            $itm->cFreifeldWert        = $itm->cEigenschaftWertName;
                            $itm->insertInDB();
                        }
                    } else {
                        foreach ($item->WarenkorbPosEigenschaftArr as $itm) {
                            $itm->kWarenkorbPos        = $item->kWarenkorbPos;
                            $itm->cEigenschaftName     = \is_array($itm->cEigenschaftName)
                                ? $itm->cEigenschaftName[$idx]
                                : $itm->cEigenschaftName;
                            $itm->cEigenschaftWertName = \is_array($itm->cEigenschaftWertName)
                                ? $itm->cEigenschaftWertName[$idx]
                                : $itm->cEigenschaftWertName;
                            if ($itm->cTyp === 'FREIFELD' || $itm->cTyp === 'PFLICHT-FREIFELD') {
                                $itm->cFreifeldWert = $itm->cEigenschaftWertName;
                            }
                            $itm->insertInDB();
                        }
                    }
                }
                // bestseller tabelle füllen
                if ($item->nPosTyp === \C_WARENKORBPOS_TYP_ARTIKEL && \is_object($item->Artikel)) {
                    // Lagerbestand verringern
                    $this->stockUpdater->updateStock(
                        $item->Artikel,
                        $item->nAnzahl,
                        $item->WarenkorbPosEigenschaftArr,
                        $productFilter
                    );
                    $this->stockUpdater->updateBestsellers($item->kArtikel, $item->nAnzahl);
                    // xsellkauf füllen
                    foreach ($this->cart->PositionenArr as $cartItem) {
                        if (
                            $cartItem->kArtikel !== null
                            && $cartItem->nPosTyp === \C_WARENKORBPOS_TYP_ARTIKEL
                            && $cartItem->kArtikel !== $item->kArtikel
                        ) {
                            $this->stockUpdater->updateXSelling($item->kArtikel, $cartItem->kArtikel);
                        }
                    }
                    $cartItems[]         = $item;
                    $this->tagsToFlush[] = \CACHING_GROUP_ARTICLE . '_' . $item->kArtikel;
                } elseif ($item->nPosTyp === \C_WARENKORBPOS_TYP_GRATISGESCHENK) {
                    $this->stockUpdater->updateStock(
                        $item->Artikel,
                        $item->nAnzahl,
                        $item->WarenkorbPosEigenschaftArr,
                        $productFilter
                    );
                    $cartItems[]         = $item;
                    $this->tagsToFlush[] = \CACHING_GROUP_ARTICLE . '_' . $item->kArtikel;
                }

                $order->Positionen[] = $item;
            }
            // Falls die Einstellung global_wunschliste_artikel_loeschen_nach_kauf auf Y (Ja) steht und
            // Artikel vom aktuellen Wunschzettel gekauft wurden, sollen diese vom Wunschzettel geloescht werden
            if (Frontend::getWishList()->getID() > 0) {
                Wishlist::pruefeArtikelnachBestellungLoeschen(
                    Frontend::getWishList()->getID(),
                    $cartItems
                );
            }
        }
        $billingAddress                = new Rechnungsadresse();
        $billingAddress->kKunde        = $customer->kKunde;
        $billingAddress->cAnrede       = $customer->cAnrede;
        $billingAddress->cTitel        = $customer->cTitel;
        $billingAddress->cVorname      = $customer->cVorname;
        $billingAddress->cNachname     = $customer->cNachname;
        $billingAddress->cFirma        = $customer->cFirma;
        $billingAddress->cZusatz       = $customer->cZusatz;
        $billingAddress->cStrasse      = $customer->cStrasse;
        $billingAddress->cHausnummer   = $customer->cHausnummer;
        $billingAddress->cAdressZusatz = $customer->cAdressZusatz;
        $billingAddress->cPLZ          = $customer->cPLZ;
        $billingAddress->cOrt          = $customer->cOrt;
        $billingAddress->cBundesland   = $customer->cBundesland;
        $billingAddress->cLand         = $customer->cLand;
        $billingAddress->cTel          = $customer->cTel;
        $billingAddress->cMobil        = $customer->cMobil;
        $billingAddress->cFax          = $customer->cFax;
        $billingAddress->cUSTID        = $customer->cUSTID;
        $billingAddress->cWWW          = $customer->cWWW;
        $billingAddress->cMail         = $customer->cMail;

        \executeHook(\HOOK_BESTELLABSCHLUSS_INC_BESTELLUNGINDB_RECHNUNGSADRESSE, ['billingAddress' => $billingAddress]);

        $billingAddressID = $billingAddress->insertInDB();
        if (isset($_POST['kommentar'])) {
            $_SESSION['kommentar'] = \mb_substr(\strip_tags($_POST['kommentar']), 0, 1000);
        } elseif (!isset($_SESSION['kommentar'])) {
            $_SESSION['kommentar'] = '';
        }

        $order->kKunde            = $this->cart->kKunde;
        $order->kWarenkorb        = $this->cart->kWarenkorb;
        $order->kLieferadresse    = $this->cart->kLieferadresse;
        $order->kRechnungsadresse = $billingAddressID;
        $order->kZahlungsart      = (int)$_SESSION['Zahlungsart']->kZahlungsart;
        $order->kVersandart       = (int)$_SESSION['Versandart']->kVersandart;
        $order->kSprache          = $this->languageID;
        $order->kWaehrung         = Frontend::getCurrency()->getID();
        $order->fGesamtsumme      = $this->cart->gibGesamtsummeWaren(true);
        $order->cVersandartName   = $_SESSION['Versandart']->angezeigterName[$langCode];
        $order->cVersandInfo      = $_SESSION['Versandart']->angezeigterHinweistext[$langCode];
        $order->cZahlungsartName  = $_SESSION['Zahlungsart']->angezeigterName[$langCode] ?? '';
        $order->cSession          = \session_id();
        $order->cKommentar        = $_SESSION['kommentar'];
        $order->cAbgeholt         = 'N';
        $order->cStatus           = \BESTELLUNG_STATUS_OFFEN;
        $order->dErstellt         = 'NOW()';
        $order->berechneEstimatedDelivery();
        if (
            isset($_SESSION['Bestellung']->GuthabenNutzen, $_SESSION['Bestellung']->fGuthabenGenutzt)
            && (int)$_SESSION['Bestellung']->GuthabenNutzen === 1
        ) {
            $order->fGuthaben = -$_SESSION['Bestellung']->fGuthabenGenutzt;
            $this->db->queryPrepared(
                'UPDATE tkunde
                    SET fGuthaben = fGuthaben - :cred
                    WHERE kKunde = :cid',
                [
                    'cred' => (float)$_SESSION['Bestellung']->fGuthabenGenutzt,
                    'cid'  => (int)$order->kKunde
                ]
            );
            $customer->fGuthaben -= $_SESSION['Bestellung']->fGuthabenGenutzt;
        }
        // Gesamtsumme entspricht 0
        if ((int)$order->fGesamtsumme === 0) {
            $order->cStatus          = \BESTELLUNG_STATUS_BEZAHLT;
            $order->dBezahltDatum    = 'NOW()';
            $order->cZahlungsartName = Shop::Lang()->get('paymentNotNecessary', 'checkout');
        }
        // no anonymization is done here anymore, cause we got a contract
        $order->cIP = $_SESSION['IP']->cIP ?? Request::getRealIP();
        // #8544
        $order->fWaehrungsFaktor = Frontend::getCurrency()->getConversionFactor();

        \executeHook(\HOOK_BESTELLABSCHLUSS_INC_BESTELLUNGINDB, ['oBestellung' => &$order]);

        $orderID = $order->insertInDB();
        // OrderAttributes
        if (!empty($_SESSION['Warenkorb']->OrderAttributes)) {
            foreach ($_SESSION['Warenkorb']->OrderAttributes as $orderAttr) {
                $obj              = new stdClass();
                $obj->kBestellung = $orderID;
                $obj->cName       = $orderAttr->cName;
                $obj->cValue      = $orderAttr->cName === 'Finanzierungskosten'
                    ? (float)\str_replace(',', '.', $orderAttr->cValue)
                    : $orderAttr->cValue;
                $this->db->insert('tbestellattribut', $obj);
            }
        }

        $logger = Shop::Container()->getLogService();
        if ($logger instanceof Logger && $logger->isHandling(\JTLLOG_LEVEL_DEBUG)) {
            $logger->withName('kBestellung')->debug('Bestellung gespeichert: ' . \print_r($order, true), [$orderID]);
        }
        $bestellid              = new stdClass();
        $bestellid->cId         = \uniqid('', true);
        $bestellid->kBestellung = $orderID;
        $bestellid->dDatum      = 'NOW()';
        $this->db->insert('tbestellid', $bestellid);
        $bestellstatus              = new stdClass();
        $bestellstatus->kBestellung = $orderID;
        $bestellstatus->dDatum      = 'NOW()';
        $bestellstatus->cUID        = \uniqid('', true);
        $this->db->insert('tbestellstatus', $bestellstatus);
        // füge ZahlungsInfo ein, falls es die Versandart erfordert
        if (isset($_SESSION['Zahlungsart']->ZahlungsInfo) && $_SESSION['Zahlungsart']->ZahlungsInfo) {
            $this->savePaymentInfo($order->kKunde, $orderID);
        }

        $_SESSION['BestellNr']   = $order->cBestellNr;
        $_SESSION['kBestellung'] = $orderID;
        $this->stockUpdater->updateCouponUsages($order);
        Campaign::setCampaignAction(\KAMPAGNE_DEF_VERKAUF, $orderID, 1.0);
        Campaign::setCampaignAction(\KAMPAGNE_DEF_VERKAUFSSUMME, $orderID, $order->fGesamtsumme);
        \executeHook(\HOOK_BESTELLABSCHLUSS_INC_BESTELLUNGINDB_ENDE, [
            'oBestellung'   => &$order,
            'bestellID'     => &$bestellid,
            'bestellstatus' => &$bestellstatus,
        ]);
    }

    /**
     * @former finalisiereBestellung()
     * @since 5.2.0
     */
    public function finalizeOrder(?string $orderNo = null, bool $sendMail = true): Bestellung
    {
        $obj                      = new stdClass();
        $obj->cVerfuegbarkeit_arr = $this->checkAvailability();

        $this->persistOrder(false, $orderNo);

        $order  = (new Bestellung($_SESSION['kBestellung'], false, $this->db))->fuelleBestellung(false);
        $helper = new Order($order);
        $amount = $helper->getTotal(4);

        $upd              = new stdClass();
        $upd->kKunde      = $this->cart->kKunde;
        $upd->kBestellung = (int)$order->kBestellung;
        $this->db->update('tbesucher', 'kKunde', $upd->kKunde, $upd);
        $obj->tkunde         = $this->customer;
        $obj->tbestellung    = $order;
        $obj->payments       = $order->getIncommingPayments(false);
        $obj->totalLocalized = Preise::getLocalizedPriceWithoutFactor(
            $amount->total[CartHelper::GROSS],
            $amount->currency,
            false
        );

        if (isset($order->oEstimatedDelivery->longestMin, $order->oEstimatedDelivery->longestMax)) {
            $obj->tbestellung->cEstimatedDeliveryEx = Date::dateAddWeekday(
                $order->dErstellt,
                $order->oEstimatedDelivery->longestMin
            )->format('d.m.Y')
                . ' - '
                . Date::dateAddWeekday($order->dErstellt, $order->oEstimatedDelivery->longestMax)->format('d.m.Y');
        }
        $customer = new Customer();
        $customer->kopiereSession();
        if ($sendMail === true) {
            $mailer = Shop::Container()->getMailer();
            $mail   = new Mail();
            $mailer->send($mail->createFromTemplateID(\MAILTEMPLATE_BESTELLBESTAETIGUNG, $obj));
        }
        $_SESSION['Kunde'] = $customer;
        $customerGroupID   = $this->customer->getGroupID();
        $checkbox          = new CheckBox(0, $this->db);
        $checkbox->triggerSpecialFunction(
            \CHECKBOX_ORT_BESTELLABSCHLUSS,
            $customerGroupID,
            true,
            $_POST,
            ['oBestellung' => $order, 'oKunde' => $customer]
        );
        $checkbox->checkLogging(\CHECKBOX_ORT_BESTELLABSCHLUSS, $customerGroupID, $_POST, true);
        if (\count($this->tagsToFlush) > 0) {
            Shop::Container()->getCache()->flushTags($this->tagsToFlush);
        }

        return $order;
    }

    /**
     * Schaut nach ob eine Bestellmenge > Lagersbestand ist und falls dies erlaubt ist, gibt es einen Hinweis.
     *
     * @return array<mixed>
     * @former pruefeVerfuegbarkeit()
     * @since 5.2.0
     */
    public function checkAvailability(): array
    {
        $res    = ['cArtikelName_arr' => []];
        $confOK = Settings::boolValue(Globals::DELAY_SHOW);
        foreach ($this->cart->PositionenArr as $item) {
            if (
                $item->nPosTyp === \C_WARENKORBPOS_TYP_ARTIKEL
                && isset($item->Artikel->cLagerBeachten)
                && $item->Artikel->cLagerBeachten === 'Y'
                && $item->Artikel->cLagerKleinerNull === 'Y'
                && $confOK
                && $item->nAnzahl > $item->Artikel->fLagerbestand
            ) {
                $res['cArtikelName_arr'][] = $item->Artikel->cName;
            }
        }

        if (\count($res['cArtikelName_arr']) > 0) {
            $res['cHinweis'] = \str_replace('%s', '', Shop::Lang()->get('orderExpandInventory', 'basket'));
        }

        return $res;
    }

    /**
     * @former fakeBestellung()
     * @since 5.2.0
     */
    public function fakeOrder(): Bestellung
    {
        $langCode = Shop::getLanguageCode();
        if (isset($_POST['kommentar'])) {
            $_SESSION['kommentar'] = \mb_substr(
                \strip_tags($this->db->escape($_POST['kommentar'])),
                0,
                1000
            );
        }
        $customer                = $this->customer;
        $order                   = new Bestellung(0, false, $this->db);
        $order->kKunde           = $this->cart->kKunde;
        $order->kWarenkorb       = $this->cart->kWarenkorb;
        $order->kLieferadresse   = $this->cart->kLieferadresse;
        $order->kZahlungsart     = (int)$_SESSION['Zahlungsart']->kZahlungsart;
        $order->kVersandart      = (int)$_SESSION['Versandart']->kVersandart;
        $order->kSprache         = $this->languageID;
        $order->fGesamtsumme     = $this->cart->gibGesamtsummeWaren(true);
        $order->fWarensumme      = $order->fGesamtsumme;
        $order->cVersandartName  = $_SESSION['Versandart']->angezeigterName[$langCode];
        $order->cZahlungsartName = $_SESSION['Zahlungsart']->angezeigterName[$langCode];
        $order->cSession         = \session_id() ?: null;
        $order->cKommentar       = $_SESSION['kommentar'];
        $order->cAbgeholt        = 'N';
        $order->cStatus          = \BESTELLUNG_STATUS_OFFEN;
        $order->dErstellt        = 'NOW()';
        $order->Zahlungsart      = $_SESSION['Zahlungsart'];
        $order->Positionen       = [];
        $order->Waehrung         = Frontend::getCurrency();
        $order->kWaehrung        = Frontend::getCurrency()->getID();
        $order->fWaehrungsFaktor = Frontend::getCurrency()->getConversionFactor();

        $order->oRechnungsadresse              = $order->oRechnungsadresse ?? new Rechnungsadresse();
        $order->oRechnungsadresse->cVorname    = $customer->cVorname;
        $order->oRechnungsadresse->cNachname   = $customer->cNachname;
        $order->oRechnungsadresse->cFirma      = $customer->cFirma;
        $order->oRechnungsadresse->kKunde      = $customer->kKunde;
        $order->oRechnungsadresse->cAnrede     = $customer->cAnrede;
        $order->oRechnungsadresse->cTitel      = $customer->cTitel;
        $order->oRechnungsadresse->cStrasse    = $customer->cStrasse;
        $order->oRechnungsadresse->cHausnummer = $customer->cHausnummer;
        $order->oRechnungsadresse->cPLZ        = $customer->cPLZ;
        $order->oRechnungsadresse->cOrt        = $customer->cOrt;
        $order->oRechnungsadresse->cLand       = $customer->cLand;
        $order->oRechnungsadresse->cTel        = $customer->cTel;
        $order->oRechnungsadresse->cMobil      = $customer->cMobil;
        $order->oRechnungsadresse->cFax        = $customer->cFax;
        $order->oRechnungsadresse->cUSTID      = $customer->cUSTID;
        $order->oRechnungsadresse->cWWW        = $customer->cWWW;
        $order->oRechnungsadresse->cMail       = $customer->cMail;

        if (\mb_strlen(Frontend::getDeliveryAddress()->cVorname) > 0) {
            $order->Lieferadresse = $this->getShippingAddress();
        }
        if (
            isset($_SESSION['Bestellung']->GuthabenNutzen, $_SESSION['Bestellung']->fGuthabenGenutzt)
            && (int)$_SESSION['Bestellung']->GuthabenNutzen === 1
        ) {
            $order->fGuthaben = -$_SESSION['Bestellung']->fGuthabenGenutzt;
        }
        $order->cBestellNr = \date('dmYHis') . \mb_substr($order->cSession, 0, 4);
        $order->cIP        = Request::getRealIP();
        $order->fuelleBestellung(false, 1);
        $order->Positionen = [];
        foreach ($this->cart->PositionenArr as $i => $item) {
            $cartItem = new CartItem();
            foreach (\array_keys(\get_object_vars($item)) as $member) {
                $cartItem->$member = $item->$member;
            }
            $cartItem->cName = $cartItem->getName($langCode);
            $cartItem->fMwSt = Tax::getSalesTax($item->kSteuerklasse);
            $cartItem->setzeGesamtpreisLocalized();
            $order->Positionen[$i] = $cartItem;
        }

        return $order;
    }

    /**
     * @former gibLieferadresseAusSession()
     * @since 5.2.0
     */
    public function getShippingAddress(): ?stdClass
    {
        $deliveryAddress = Frontend::getDeliveryAddress();
        if (empty($deliveryAddress->cVorname)) {
            return null;
        }
        $shippingAddress              = new stdClass();
        $shippingAddress->cVorname    = $deliveryAddress->cVorname;
        $shippingAddress->cNachname   = $deliveryAddress->cNachname;
        $shippingAddress->cFirma      = $deliveryAddress->cFirma ?? null;
        $shippingAddress->kKunde      = $deliveryAddress->kKunde;
        $shippingAddress->cAnrede     = $deliveryAddress->cAnrede;
        $shippingAddress->cTitel      = $deliveryAddress->cTitel;
        $shippingAddress->cStrasse    = $deliveryAddress->cStrasse;
        $shippingAddress->cHausnummer = $deliveryAddress->cHausnummer;
        $shippingAddress->cPLZ        = $deliveryAddress->cPLZ;
        $shippingAddress->cOrt        = $deliveryAddress->cOrt;
        $shippingAddress->cLand       = $deliveryAddress->cLand;
        $shippingAddress->cTel        = $deliveryAddress->cTel;
        $shippingAddress->cMobil      = $deliveryAddress->cMobil ?? null;
        $shippingAddress->cFax        = $deliveryAddress->cFax ?? null;
        $shippingAddress->cUSTID      = $deliveryAddress->cUSTID ?? null;
        $shippingAddress->cWWW        = $deliveryAddress->cWWW ?? null;
        $shippingAddress->cMail       = $deliveryAddress->cMail;

        return $shippingAddress;
    }

    /**
     * @former speicherUploads()
     * @since 5.2.0
     */
    public function saveUploads(Bestellung $order): void
    {
        if (!empty($order->kBestellung) && Upload::checkLicense()) {
            Upload::speicherUploadDateien($this->cart, $order->kBestellung);
        }
    }

    /**
     * @former baueBestellnummer()
     * @since 5.2.0
     */
    public function createOrderNo(?string $currentOrderNumber = null): string
    {
        $conf = Shop::getSettingSection(\CONF_KAUFABWICKLUNG);
        if ($this->isValidOrderNo($currentOrderNumber, $conf)) {
            return $currentOrderNumber;
        }
        $number    = new Nummern(\JTL_GENNUMBER_ORDERNUMBER);
        $increment = (int)($conf['bestellabschluss_bestellnummer_anfangsnummer'] ?? 1);
        $orderNo   = ($number->getNummer() ?? 0) + $increment;
        $number->setNummer($number->getNummer() + 1);
        $number->update();

        $prefix = $this->getOrderNoPrefix($conf['bestellabschluss_bestellnummer_praefix']);
        $suffix = $this->getOrderNoSuffix($conf['bestellabschluss_bestellnummer_suffix']);
        \executeHook(\HOOK_BESTELLABSCHLUSS_INC_BAUEBESTELLNUMMER, [
            'orderNo' => &$orderNo,
            'prefix'  => &$prefix,
            'suffix'  => &$suffix
        ]);

        $nrLen = \mb_strlen($prefix . $orderNo);
        if ($nrLen > 20) {
            $prefix = \mb_substr($prefix, $nrLen - 20);
            $suffix = '';
            Shop::Container()->getLogService()->error(
                'length of order number exceeds limit - prefix will be shortened'
            );
        } elseif ($nrLen + \mb_strlen($suffix) > 20) {
            $suffix = \mb_substr($suffix, 0, 20 - $nrLen);
            Shop::Container()->getLogService()->error(
                'length of order number exceeds limit - suffix will be shortened'
            );
        }

        return $prefix . $orderNo . $suffix;
    }

    /**
     *  Placeholder pre- and suffix
     *   %Y = - current year
     *   %m = - current month
     *   %d = - current day
     *   %W = - current week
     */
    protected function getOrderNoPrefix(string $orderNoPrefix): string
    {
        return \str_replace(
            ['%Y', '%m', '%d', '%W'],
            [\date('Y'), \date('m'), \date('d'), \date('W')],
            $orderNoPrefix
        );
    }

    protected function getOrderNoSuffix(string $orderNoSuffix): string
    {
        return \str_replace(
            ['%Y', '%m', '%d', '%W'],
            [\date('Y'), \date('m'), \date('d'), \date('W')],
            $orderNoSuffix
        );
    }

    /**
     * @param array<mixed> $conf
     */
    protected function isValidOrderNo(?string $orderNo, array $conf): bool
    {
        if (empty($orderNo)) {
            return false;
        }
        $prefix = $this->getOrderNoPrefix($conf['bestellabschluss_bestellnummer_praefix']);
        $suffix = $this->getOrderNoSuffix($conf['bestellabschluss_bestellnummer_suffix']);

        return (bool)\preg_match('/^' . $prefix . '[\d]+' . $suffix . '$/', $orderNo);
    }

    public function unhtmlSession(): void
    {
        $customer           = new Customer();
        $sessionCustomer    = $this->customer;
        $customerAttributes = Frontend::get('customerAttributes');
        if ($sessionCustomer->kKunde > 0) {
            $customer->kKunde = $sessionCustomer->kKunde;
            $customer->getCustomerAttributes()->load($customer->getID());
        } elseif ($customerAttributes !== null) {
            $customer->getCustomerAttributes()->assign($customerAttributes);
        }
        $customer->kKundengruppe = $this->customer->getGroupID();
        if ($sessionCustomer->kKundengruppe > 0) {
            $customer->kKundengruppe = $sessionCustomer->kKundengruppe;
        }
        $customer->kSprache = $this->languageID;
        if ($sessionCustomer->kSprache > 0) {
            $customer->kSprache = $sessionCustomer->kSprache;
        }
        if ($sessionCustomer->cKundenNr) {
            $customer->cKundenNr = $sessionCustomer->cKundenNr;
        }
        if ($sessionCustomer->cPasswort) {
            $customer->cPasswort = $sessionCustomer->cPasswort;
        }
        if ($sessionCustomer->fGuthaben) {
            $customer->fGuthaben = $sessionCustomer->fGuthaben;
        }
        if ($sessionCustomer->fRabatt) {
            $customer->fRabatt = $sessionCustomer->fRabatt;
        }
        if ($sessionCustomer->dErstellt) {
            $customer->dErstellt = $sessionCustomer->dErstellt;
        }
        if ($sessionCustomer->cAktiv) {
            $customer->cAktiv = $sessionCustomer->cAktiv;
        }
        if ($sessionCustomer->cAbgeholt) {
            $customer->cAbgeholt = $sessionCustomer->cAbgeholt;
        }
        if (isset($sessionCustomer->nRegistriert)) {
            $customer->nRegistriert = $sessionCustomer->nRegistriert;
        }
        $customer->cAnrede       = Text::unhtmlentities($sessionCustomer->cAnrede);
        $customer->cVorname      = Text::unhtmlentities($sessionCustomer->cVorname);
        $customer->cNachname     = Text::unhtmlentities($sessionCustomer->cNachname);
        $customer->cStrasse      = Text::unhtmlentities($sessionCustomer->cStrasse);
        $customer->cHausnummer   = Text::unhtmlentities($sessionCustomer->cHausnummer);
        $customer->cPLZ          = Text::unhtmlentities($sessionCustomer->cPLZ);
        $customer->cOrt          = Text::unhtmlentities($sessionCustomer->cOrt);
        $customer->cLand         = Text::unhtmlentities($sessionCustomer->cLand);
        $customer->cMail         = Text::unhtmlentities($sessionCustomer->cMail);
        $customer->cTel          = Text::unhtmlentities($sessionCustomer->cTel);
        $customer->cFax          = Text::unhtmlentities($sessionCustomer->cFax);
        $customer->cFirma        = Text::unhtmlentities($sessionCustomer->cFirma);
        $customer->cZusatz       = Text::unhtmlentities($sessionCustomer->cZusatz);
        $customer->cTitel        = Text::unhtmlentities($sessionCustomer->cTitel);
        $customer->cAdressZusatz = Text::unhtmlentities($sessionCustomer->cAdressZusatz);
        $customer->cMobil        = Text::unhtmlentities($sessionCustomer->cMobil);
        $customer->cWWW          = Text::unhtmlentities($sessionCustomer->cWWW);
        $customer->cUSTID        = Text::unhtmlentities($sessionCustomer->cUSTID);
        $customer->dGeburtstag   = Text::unhtmlentities($sessionCustomer->dGeburtstag);
        $customer->cBundesland   = Text::unhtmlentities($sessionCustomer->cBundesland);

        $_SESSION['Kunde'] = $customer;
        $this->customer    = $customer;

        $shippingAddress = new Lieferadresse();
        $deliveryAddress = Frontend::getDeliveryAddress();
        if (($cid = $deliveryAddress->kKunde) > 0) {
            $shippingAddress->kKunde = $cid;
        }
        if (($did = $deliveryAddress->kLieferadresse) > 0) {
            $shippingAddress->kLieferadresse = $did;
        }
        $shippingAddress->cVorname      = Text::unhtmlentities($deliveryAddress->cVorname);
        $shippingAddress->cNachname     = Text::unhtmlentities($deliveryAddress->cNachname);
        $shippingAddress->cFirma        = Text::unhtmlentities($deliveryAddress->cFirma);
        $shippingAddress->cZusatz       = Text::unhtmlentities($deliveryAddress->cZusatz);
        $shippingAddress->cStrasse      = Text::unhtmlentities($deliveryAddress->cStrasse);
        $shippingAddress->cHausnummer   = Text::unhtmlentities($deliveryAddress->cHausnummer);
        $shippingAddress->cPLZ          = Text::unhtmlentities($deliveryAddress->cPLZ);
        $shippingAddress->cOrt          = Text::unhtmlentities($deliveryAddress->cOrt);
        $shippingAddress->cLand         = Text::unhtmlentities($deliveryAddress->cLand);
        $shippingAddress->cAnrede       = Text::unhtmlentities($deliveryAddress->cAnrede);
        $shippingAddress->cMail         = Text::unhtmlentities($deliveryAddress->cMail);
        $shippingAddress->cBundesland   = Text::unhtmlentities($deliveryAddress->cBundesland);
        $shippingAddress->cTel          = Text::unhtmlentities($deliveryAddress->cTel);
        $shippingAddress->cFax          = Text::unhtmlentities($deliveryAddress->cFax);
        $shippingAddress->cTitel        = Text::unhtmlentities($deliveryAddress->cTitel);
        $shippingAddress->cAdressZusatz = Text::unhtmlentities($deliveryAddress->cAdressZusatz);
        $shippingAddress->cMobil        = Text::unhtmlentities($deliveryAddress->cMobil);

        $shippingAddress->angezeigtesLand = LanguageHelper::getCountryCodeByCountryName($shippingAddress->cLand);

        $deliveryAddress = $shippingAddress;
        Frontend::setDeliveryAddress($deliveryAddress);
    }

    /**
     * @former saveZahlungsInfo()
     * @since 5.2.0
     */
    public function savePaymentInfo(int $customerID, int $orderID, bool $payAgain = false): bool
    {
        if (!$customerID || !$orderID) {
            return false;
        }
        $info = $_SESSION['Zahlungsart']->ZahlungsInfo;

        $_SESSION['ZahlungsInfo']               = new ZahlungsInfo();
        $_SESSION['ZahlungsInfo']->kBestellung  = $orderID;
        $_SESSION['ZahlungsInfo']->kKunde       = $customerID;
        $_SESSION['ZahlungsInfo']->cKartenTyp   = Text::unhtmlentities($info->cKartenTyp ?? null);
        $_SESSION['ZahlungsInfo']->cGueltigkeit = Text::unhtmlentities($info->cGueltigkeit ?? null);
        $_SESSION['ZahlungsInfo']->cBankName    = Text::unhtmlentities($info->cBankName ?? null);
        $_SESSION['ZahlungsInfo']->cKartenNr    = Text::unhtmlentities($info->cKartenNr ?? null);
        $_SESSION['ZahlungsInfo']->cCVV         = Text::unhtmlentities($info->cCVV ?? null);
        $_SESSION['ZahlungsInfo']->cKontoNr     = Text::unhtmlentities($info->cKontoNr ?? null);
        $_SESSION['ZahlungsInfo']->cBLZ         = Text::unhtmlentities($info->cBLZ ?? null);
        $_SESSION['ZahlungsInfo']->cIBAN        = Text::unhtmlentities($info->cIBAN ?? null);
        $_SESSION['ZahlungsInfo']->cBIC         = Text::unhtmlentities($info->cBIC ?? null);
        $_SESSION['ZahlungsInfo']->cInhaber     = Text::unhtmlentities($info->cInhaber ?? null);
        if (!$payAgain) {
            $this->cart->kZahlungsInfo = $_SESSION['ZahlungsInfo']->insertInDB();
            $this->cart->updateInDB();
        } else {
            $_SESSION['ZahlungsInfo']->insertInDB();
        }
        if (isset($info->cKontoNr) || isset($info->cIBAN)) {
            $this->db->delete('tkundenkontodaten', 'kKunde', $customerID);
            $this->saveCustomerAccountData($info);
        }

        return true;
    }

    /**
     * @former speicherKundenKontodaten()
     * @since 5.2.0
     */
    public function saveCustomerAccountData(object $paymentInfo): void
    {
        $cryptoService   = Shop::Container()->getCryptoService();
        $data            = new stdClass();
        $data->kKunde    = $this->cart->kKunde;
        $data->cBLZ      = $cryptoService->encryptXTEA($paymentInfo->cBLZ ?? '');
        $data->nKonto    = $cryptoService->encryptXTEA($paymentInfo->cKontoNr ?? '');
        $data->cInhaber  = $cryptoService->encryptXTEA($paymentInfo->cInhaber ?? '');
        $data->cBankName = $cryptoService->encryptXTEA($paymentInfo->cBankName ?? '');
        $data->cIBAN     = $cryptoService->encryptXTEA($paymentInfo->cIBAN ?? '');
        $data->cBIC      = $cryptoService->encryptXTEA($paymentInfo->cBIC ?? '');

        $this->db->insert('tkundenkontodaten', $data);
    }
}
