<?php

declare(strict_types=1);

namespace JTL\Mail\Hydrator;

use DateTime;
use JTL\Catalog\Product\Preise;
use JTL\CheckBox;
use JTL\Checkout\Kupon;
use JTL\Checkout\Lieferschein;
use JTL\Checkout\Versand;
use JTL\Customer\CustomerGroup;
use JTL\DB\DbInterface;
use JTL\Helpers\Date;
use JTL\Language\LanguageHelper;
use JTL\Language\LanguageModel;
use JTL\Shipping\Services\ShippingService;
use JTL\Shop;
use JTL\Shopsetting;
use JTL\Smarty\JTLSmarty;
use stdClass;

/**
 * Class TestHydrator
 * @package JTL\Mail\Hydrator
 */
class TestHydrator extends DefaultsHydrator
{
    private ShippingService $shippingService;
    private string $currentYear;

    public function __construct(
        JTLSmarty $smarty,
        DbInterface $db,
        Shopsetting $settings,
        ?ShippingService $shippingService = null,
    ) {
        parent::__construct($smarty, $db, $settings);
        $this->shippingService = $shippingService ?? Shop::Container()->getShippingService();
        $this->currentYear     = \date('Y');
    }

    /**
     * @inheritdoc
     */
    public function hydrate(?object $data, LanguageModel $language): void
    {
        parent::hydrate($data, $language);
        $languageCode = $language->getCode();
        Shop::Lang()->setzeSprache($languageCode);
        $langID        = $language->getId();
        $msg           = $this->getMessage($languageCode);
        $customerBonus = $this->getBonus();
        $customerGroup = (new CustomerGroup(0, $this->db))->loadDefaultGroup();
        $order         = $this->getOrder($langID);
        $customer      = $this->getCustomer($langID, $customerGroup->getID(), $languageCode);
        $checkbox      = $this->getCheckbox();
        $argwrb        = $this->db->select(
            'ttext',
            ['kKundengruppe', 'kSprache'],
            [$customer->kKundengruppe, $langID]
        );

        $this->smarty->assign('oKunde', $customer)
            ->assign('oMailObjekt', $this->getStatusMail($languageCode))
            ->assign('Verfuegbarkeit_arr', ['cArtikelName_arr' => [], 'cHinweis' => ''])
            ->assign('BestandskundenBoni', (object)['fGuthaben' => Preise::getLocalizedPriceString(1.23)])
            ->assign('cAnzeigeOrt', 'Example')
            ->assign('oSprache', $language)
            ->assign('oCheckBox', $checkbox)
            ->assign('Kunde', $customer)
            ->assign('Kundengruppe', $customerGroup)
            ->assign('cAnredeLocalized', Shop::Lang()->get('salutationM'))
            ->assign('Bestellung', $order)
            ->assign('Neues_Passwort', $languageCode === 'ger' ? 'geheim007' : 'secret007')
            ->assign(
                'passwordResetLink',
                Shop::Container()->getLinkService()->getStaticRoute('pass.php')
                . '?fpwh=ca68b243f0c1e7e57162055f248218fd'
            )
            ->assign('Gutschein', $this->getGift($languageCode))
            ->assign('interval', 720)
            ->assign('intervalLoc', $languageCode === 'ger' ? 'Monatliche Status-Email' : 'Monthly status mail')
            ->assign('AGB', $argwrb)
            ->assign('WRB', $argwrb)
            ->assign('DSE', $argwrb)
            ->assign('URL_SHOP', Shop::getURL() . '/')
            ->assign('Kupon', $this->getCoupon($languageCode))
            ->assign('Optin', $this->getOptin())
            ->assign('couponTypes', Kupon::getCouponTypes())
            ->assign('Nachricht', $msg)
            ->assign('Artikel', $this->getProduct($languageCode))
            ->assign('Wunschliste', $this->getWishlist($languageCode))
            ->assign('VonKunde', $customer)
            ->assign('Benachrichtigung', $this->getAvailabilityMessage($languageCode))
            ->assign('NewsletterEmpfaenger', $this->getNewsletterRecipient($langID))
            ->assign('oBewertungGuthabenBonus', $customerBonus);
    }

    private function getStatusMail(string $currentLangISO = 'ger'): stdClass
    {
        $mail                                           = new stdClass();
        $mail->mail                                     = new stdClass();
        $mail->oAnzahlArtikelProKundengruppe            = 1;
        $mail->nAnzahlNeukunden                         = 21;
        $mail->nAnzahlNeukundenGekauft                  = 33;
        $mail->nAnzahlBestellungen                      = 17;
        $mail->nAnzahlBestellungenNeukunden             = 13;
        $mail->nAnzahlBesucher                          = 759;
        $mail->nAnzahlBesucherSuchmaschine              = 165;
        $mail->nAnzahlBewertungen                       = 99;
        $mail->nAnzahlBewertungenNichtFreigeschaltet    = 15;
        $mail->nAnzahlVersendeterBestellungen           = 15;
        $mail->oAnzahlGezahltesGuthaben                 = -1;
        $mail->nAnzahlGeworbenerKunden                  = 11;
        $mail->nAnzahlErfolgreichGeworbenerKunden       = 0;
        $mail->nAnzahlVersendeterWunschlisten           = 0;
        $mail->nAnzahlNewskommentare                    = 21;
        $mail->nAnzahlNewskommentareNichtFreigeschaltet = 11;
        $mail->nAnzahlProduktanfrageArtikel             = 1;
        $mail->nAnzahlProduktanfrageVerfuegbarkeit      = 2;
        $mail->nAnzahlVergleiche                        = 3;
        $mail->nAnzahlGenutzteKupons                    = 4;
        $mail->nAnzahlZahlungseingaengeVonBestellungen  = 5;
        $mail->nAnzahlNewsletterAbmeldungen             = 6;
        $mail->nAnzahlNewsletterAnmeldungen             = 6;
        $mail->dVon                                     = '01.01.' . $this->currentYear;
        $mail->dBis                                     = '31.01.' . $this->currentYear;
        $mail->oLogEntry_arr                            = [];
        $mail->cIntervall                               = $currentLangISO === 'ger'
            ? 'Monatliche Status-Email'
            : 'Monthly status mail';

        return $mail;
    }

    private function getCheckbox(): CheckBox
    {
        $id = $this->db->getSingleInt('SELECT kCheckbox FROM tcheckbox LIMIT 1', 'kCheckbox');

        return new CheckBox($id, $this->db);
    }

    private function getAvailabilityMessage(string $currentLangISO = 'ger'): stdClass
    {
        $msg            = new stdClass();
        $msg->cVorname  = $currentLangISO === 'ger' ? 'Max' : 'John';
        $msg->cNachname = $currentLangISO === 'ger' ? 'Mustermann' : 'Doe';

        return $msg;
    }

    private function getGift(string $currentLangISO = 'ger'): stdClass
    {
        $gift                 = new stdClass();
        $gift->fWert          = 5.00;
        $gift->cLocalizedWert = '5,00 EUR';
        $gift->cGrund         = $currentLangISO === 'ger' ? 'Geburtstag' : 'Birthday';
        $gift->kGutschein     = 33;
        $gift->kKunde         = 1;

        return $gift;
    }

    private function getMessage(string $currentLangISO = 'ger'): stdClass
    {
        $msg                   = new stdClass();
        $msg->cNachricht       = 'Lorem ipsum dolor sit amet.';
        $msg->cAnrede          = 'm';
        $msg->cAnredeLocalized = Shop::Lang()->get('salutationM');
        $msg->cVorname         = $currentLangISO === 'ger' ? 'Max' : 'John';
        $msg->cNachname        = $currentLangISO === 'ger' ? 'Mustermann' : 'Doe';
        $msg->cFirma           = $currentLangISO === 'ger' ? 'Musterfirma' : 'DoeCompany';
        $msg->cMail            = 'info@example.com';
        $msg->cFax             = '34782034';
        $msg->cTel             = '34782035';
        $msg->cMobil           = '34782036';
        $msg->cBetreff         = $currentLangISO === 'ger' ? 'Allgemeine Anfrage' : 'General inquiry';

        return $msg;
    }

    private function getWishlist(string $currentLangISO = 'ger'): stdClass
    {
        $wishlist                      = new stdClass();
        $wishlist->kWunschlsite        = 5;
        $wishlist->kKunde              = 1480;
        $wishlist->cName               = $currentLangISO === 'ger' ? 'Wunschzettel' : 'Wishlist';
        $wishlist->nStandard           = 1;
        $wishlist->nOeffentlich        = 0;
        $wishlist->cURLID              = '5686f6vv6c86v65nv6m8';
        $wishlist->dErstellt           = '2019-01-01 01:01:01';
        $wishlist->CWunschlistePos_arr = [];

        $item                                 = new stdClass();
        $item->kWunschlistePos                = 3;
        $item->kWunschliste                   = 5;
        $item->kArtikel                       = 261;
        $item->cArtikelName                   = 'Hansu Televsion';
        $item->fAnzahl                        = 2;
        $item->cKommentar                     = 'Television';
        $item->dHinzugefuegt                  = '2019-07-12 13:55:11';
        $item->Artikel                        = new stdClass();
        $item->Artikel->cName                 = $currentLangISO === 'ger'
            ? 'LAN Festplatte IPDrive'
            : 'LAN hard drive IPDrive';
        $item->Artikel->cEinheit              = $currentLangISO === 'ger' ? 'Stk.' : 'Pcs.';
        $item->Artikel->fPreis                = 368.1069;
        $item->Artikel->fMwSt                 = 19;
        $item->Artikel->nAnzahl               = 1;
        $item->Artikel->cURL                  = $currentLangISO === 'ger'
            ? 'LAN-Festplatte-IPDrive'
            : 'LAN-hard-drive-IPDrive';
        $item->Artikel->Bilder                = [];
        $item->Artikel->Bilder[0]             = new stdClass();
        $item->Artikel->Bilder[0]->cPfadKlein = \BILD_KEIN_ARTIKELBILD_VORHANDEN;
        $item->CWunschlistePosEigenschaft_arr = [];

        $wishlist->CWunschlistePos_arr[] = $item;

        $item                                 = new stdClass();
        $item->kWunschlistePos                = 4;
        $item->kWunschliste                   = 5;
        $item->kArtikel                       = 262;
        $item->cArtikelName                   = 'Hansu Phone';
        $item->fAnzahl                        = 1;
        $item->cKommentar                     = 'Phone';
        $item->dHinzugefuegt                  = '2019-07-12 13:55:18';
        $item->Artikel                        = new stdClass();
        $item->Artikel->cName                 = 'USB Connector';
        $item->Artikel->cEinheit              = $currentLangISO === 'ger' ? 'Stk.' : 'Pcs.';
        $item->Artikel->fPreis                = 89.90;
        $item->Artikel->fMwSt                 = 19;
        $item->Artikel->nAnzahl               = 1;
        $item->Artikel->cURL                  = 'USB-Connector';
        $item->Artikel->Bilder                = [];
        $item->Artikel->Bilder[0]             = new stdClass();
        $item->Artikel->Bilder[0]->cPfadKlein = \BILD_KEIN_ARTIKELBILD_VORHANDEN;
        $item->CWunschlistePosEigenschaft_arr = [];

        $attr                                   = new stdClass();
        $attr->kWunschlistePosEigenschaft       = 2;
        $attr->kWunschlistePos                  = 4;
        $attr->kEigenschaft                     = 2;
        $attr->kEigenschaftWert                 = 3;
        $attr->cFreifeldWert                    = '';
        $attr->cEigenschaftName                 = $currentLangISO === 'ger' ? 'Farbe' : 'Colour';
        $attr->cEigenschaftWertName             = $currentLangISO === 'ger' ? 'rot' : 'red';
        $item->CWunschlistePosEigenschaft_arr[] = $attr;

        $wishlist->CWunschlistePos_arr[] = $item;

        return $wishlist;
    }

    private function getCoupon(string $currentLangISO = 'ger'): stdClass
    {
        $now                           = (new DateTime())->format('Y-m-d H:i:s');
        $until                         = (new DateTime())->modify('+28 days')->format('Y-m-d H:i:s');
        $coupon                        = new stdClass();
        $coupon->cName                 = $currentLangISO === 'ger' ? 'Kuponname' : 'Name of the coupon';
        $coupon->Hersteller            = [];
        $coupon->fWert                 = 5;
        $coupon->cWertTyp              = $currentLangISO === 'ger' ? 'festpreis' : 'Fix price';
        $coupon->dGueltigAb            = $now;
        $coupon->cGueltigAbLong        = $now;
        $coupon->GueltigAb             = $now;
        $coupon->dGueltigBis           = $until;
        $coupon->cGueltigBisLong       = $until;
        $coupon->GueltigBis            = $until;
        $coupon->cCode                 = $currentLangISO === 'ger' ? 'geheimcode' : 'secretcode';
        $coupon->nVerwendungen         = 100;
        $coupon->nVerwendungenProKunde = 2;
        $coupon->AngezeigterName       = $currentLangISO === 'ger'
            ? 'lokalisierter Name des Kupons'
            : 'Localized name of the coupon';
        $coupon->cKuponTyp             = Kupon::TYPE_STANDARD;
        $coupon->cLocalizedWert        = '5 EUR';
        $coupon->cLocalizedMBW         = '100,00 EUR';
        $coupon->fMindestbestellwert   = 100;
        $coupon->Artikel               = [];
        $coupon->Artikel[0]            = new stdClass();
        $coupon->Artikel[1]            = new stdClass();
        $coupon->Artikel[0]->cName     = $currentLangISO === 'ger' ? 'Artikel eins' : 'Product one';
        $coupon->Artikel[0]->cURL      = 'http://example.com/artikel1';
        $coupon->Artikel[0]->cURLFull  = 'http://example.com/artikel1';
        $coupon->Artikel[1]->cName     = $currentLangISO === 'ger' ? 'Artikel zwei' : 'Product two';
        $coupon->Artikel[1]->cURL      = 'http://example.com/artikel2';
        $coupon->Artikel[1]->cURLFull  = 'http://example.com/artikel2';
        $coupon->Kategorien            = [];
        $coupon->Kategorien[0]         = new stdClass();
        $coupon->Kategorien[1]         = new stdClass();
        $coupon->Kategorien[0]->cName  = $currentLangISO === 'ger' ? 'Kategorie eins' : 'Category one';
        $coupon->Kategorien[0]->cURL   = 'http://example.com/kat1';
        $coupon->Kategorien[1]->cName  = $currentLangISO === 'ger' ? 'Kategorie zwei' : 'Category two';
        $coupon->Kategorien[1]->cURL   = 'http://example.com/kat2';

        return $coupon;
    }

    private function getCustomer(int $langID, int $customerGroupID, string $currentLangISO = 'ger'): stdClass
    {
        $customer                    = new stdClass();
        $customer->fRabatt           = 0.00;
        $customer->fGuthaben         = 0.00;
        $customer->cAnrede           = 'm';
        $customer->Anrede            = $currentLangISO === 'ger' ? 'Herr' : 'Mr.';
        $customer->cAnredeLocalized  = Shop::Lang()->get('salutationM');
        $customer->cTitel            = 'Dr.';
        $customer->cVorname          = $currentLangISO === 'ger' ? 'Max' : 'John';
        $customer->cNachname         = $currentLangISO === 'ger' ? 'Mustermann' : 'Doe';
        $customer->cFirma            = $currentLangISO === 'ger' ? 'Musterfirma' : 'DoeCompany';
        $customer->cZusatz           = $currentLangISO === 'ger' ? 'Musterfirma-Zusatz' : 'DoeCompany additional';
        $customer->cStrasse          = $currentLangISO === 'ger' ? 'Musterstrasse' : 'Doe street';
        $customer->cHausnummer       = '123';
        $customer->cPLZ              = '12345';
        $customer->cOrt              = $currentLangISO === 'ger' ? 'Musterstadt' : 'Doe city';
        $customer->cLand             = $currentLangISO === 'ger' ? 'Musterland ISO' : 'Country ISO';
        $customer->cTel              = '12345678';
        $customer->cFax              = '98765432';
        $customer->cMail             = $this->settings['emails']['email_master_absender'];
        $customer->cUSTID            = 'ust234';
        $customer->cBundesland       = $currentLangISO === 'ger' ? 'NRW' : 'Doe state';
        $customer->cAdressZusatz     = $currentLangISO === 'ger' ? 'Linker Hof' : 'Address additional';
        $customer->cMobil            = '01772322234';
        $customer->dGeburtstag       = '1981-10-10';
        $customer->cWWW              = 'http://example.com';
        $customer->kKundengruppe     = $customerGroupID;
        $customer->kSprache          = $langID;
        $customer->cPasswortKlartext = $currentLangISO === 'ger' ? 'superGeheim' : 'TopSecret';
        $customer->angezeigtesLand   = $currentLangISO === 'ger' ? 'Musterland' : 'Country';

        return $customer;
    }

    private function getOrder(int $languageID): stdClass
    {
        $languageHelper = Shop::Lang();
        $mailLangISO    = Shop::Lang()->getIsoFromLangID($languageID)->cISO ?? 'ger';
        $currentLangISO = $languageHelper->getIso();
        if ($mailLangISO !== $currentLangISO) {
            $languageHelper->setzeSprache($mailLangISO);
            $languageHelper->autoload();
            $this->shippingService->setLanguageHelper($languageHelper);
        }

        $order             = $this->getOrderBaseData($languageHelper->getLanguageID(), $mailLangISO);
        $order->Positionen = [];
        foreach ($this->getOrderItems($mailLangISO) as $item) {
            $order->Positionen[] = $item;
        }
        $order->Steuerpositionen                     = [];
        $order->Steuerpositionen[0]                  = new stdClass();
        $order->Steuerpositionen[0]->cName           = $mailLangISO === 'ger' ? 'inkl. 19% USt.' : 'incl. 19% vat';
        $order->Steuerpositionen[0]->fUst            = 19;
        $order->Steuerpositionen[0]->fBetrag         = 98.04;
        $order->Steuerpositionen[0]->cPreisLocalized = '98,04 EUR';

        $order->Waehrung = $this->getOrderCurrency();

        $order->Zahlungsart           = new stdClass();
        $order->Zahlungsart->cName    = $mailLangISO === 'ger' ? 'Rechnung' : 'Invoice Payment';
        $order->Zahlungsart->cModulId = 'za_rechnung_jtl';

        $order->Zahlungsinfo  = $this->getOrderPaymentInfo($mailLangISO);
        $order->Lieferadresse = $this->getOrderDeliveryAddress($languageHelper);

        $order->oRechnungsadresse = $order->Lieferadresse;

        $deliveryNote = new Lieferschein();
        $deliveryNote->setEmailVerschickt(false);
        $deliveryNote->oVersand_arr = [];
        $shipping                   = new Versand();
        $shipping->setLogistikURL(
            'https://nolp.dhl.de/nextt-online-public/'
            . 'report_popup.jsp?lang=de&zip=#PLZ#&idc=#IdentCode#'
        );
        $shipping->setIdentCode('123456');
        $deliveryNote->oVersand_arr[] = $shipping;
        $deliveryNote->oPosition_arr  = [];
        foreach ($this->getOrderItems($mailLangISO) as $item) {
            $deliveryNote->oPosition_arr[] = $item;
        }
        $order->oLieferschein_arr   = [];
        $order->oLieferschein_arr[] = $deliveryNote;

        $order->oEstimatedDelivery->localized = $this->shippingService->getDeliverytimeEstimationText(
            $order->oEstimatedDelivery->longestMin,
            $order->oEstimatedDelivery->longestMax
        );

        $start = Date::dateAddWeekday(
            $order->dErstellt,
            $order->oEstimatedDelivery->longestMin
        )->format('d.m.Y');
        $end   = Date::dateAddWeekday(
            $order->dErstellt,
            $order->oEstimatedDelivery->longestMax
        )->format('d.m.Y');

        $order->cEstimatedDeliveryEx = $start . ' - ' . $end;
        if ($mailLangISO !== $currentLangISO) {
            $shopLangHelper = Shop::Lang();
            $shopLangHelper->setzeSprache($currentLangISO);
            $shopLangHelper->autoload();
            $this->shippingService->setLanguageHelper($shopLangHelper);
        }

        return $order;
    }

    private function getOrderBaseData(int $languageID, string $currentLangISO = 'ger'): stdClass
    {
        $order                         = new stdClass();
        $order->kWaehrung              = $languageID;
        $order->kSprache               = 1;
        $order->fGuthaben              = '5.0000';
        $order->fGesamtsumme           = '433.00';
        $order->cBestellNr             = 'Prefix-3432-Suffix';
        $order->cVersandInfo           = $currentLangISO === 'ger'
            ? 'Optionale Information zum Versand'
            : 'Optional shipping information';
        $order->cTracking              = 'Track232837';
        $order->cKommentar             = $currentLangISO === 'ger'
            ? 'Kundenkommentar zur Bestellung'
            : 'Customer comment on the order';
        $order->cVersandartName        = $currentLangISO === 'ger'
            ? 'DHL bis 10kg'
            : 'DHL until 10kg';
        $order->cZahlungsartName       = $currentLangISO === 'ger'
            ? 'Nachnahme'
            : 'Cash on delivery';
        $order->cStatus                = 1;
        $order->dVersandDatum          = $this->currentYear . '-10-21';
        $order->dErstellt              = $this->currentYear . '-10-12 09:28:38';
        $order->dBezahltDatum          = $this->currentYear . '-10-20';
        $order->cLogistiker            = 'DHL';
        $order->cTrackingURL           = 'https://dhl.de/linkzudhl.php';
        $order->dVersanddatum_de       = '21.10.' . $this->currentYear;
        $order->dBezahldatum_de        = '20.10.' . $this->currentYear;
        $order->dErstelldatum_de       = '12.10.' . $this->currentYear;
        $order->dVersanddatum_en       = '21st October ' . $this->currentYear;
        $order->dBezahldatum_en        = '20th October ' . $this->currentYear;
        $order->dErstelldatum_en       = '12th October ' . $this->currentYear;
        $order->cBestellwertLocalized  = '511,00 EUR';
        $order->GuthabenNutzen         = 1;
        $order->GutscheinLocalized     = '5,00 EUR';
        $order->fWarensumme            = 433.004004;
        $order->fVersand               = 0;
        $order->nZahlungsTyp           = 0;
        $order->fWaehrungsFaktor       = 1;
        $order->WarensummeLocalized[0] = '511,00 EUR';
        $order->WarensummeLocalized[1] = '429,41 EUR';
        $order->oEstimatedDelivery     = (object)[
            'localized'  => '',
            'longestMin' => 3,
            'longestMax' => 6,
        ];
        $order->cEstimatedDelivery     = &$order->oEstimatedDelivery->localized;

        return $order;
    }

    private function getOrderCurrency(): stdClass
    {
        $currency                       = new stdClass();
        $currency->cISO                 = 'EUR';
        $currency->cName                = 'EUR';
        $currency->cNameHTML            = '&euro;';
        $currency->fFaktor              = 1;
        $currency->cStandard            = 'Y';
        $currency->cVorBetrag           = 'N';
        $currency->cTrennzeichenCent    = ',';
        $currency->cTrennzeichenTausend = '.';

        return $currency;
    }

    private function getOrderPaymentInfo(string $currentLangISO = ''): stdClass
    {
        $info               = new stdClass();
        $info->cBankName    = $currentLangISO === 'ger' ? 'Bankname' : 'bank name';
        $info->cBLZ         = '3443234';
        $info->cKontoNr     = 'Kto12345';
        $info->cIBAN        = 'IB239293';
        $info->cBIC         = 'BIC3478';
        $info->cKartenNr    = 'KNR4834';
        $info->cGueltigkeit = '20.10.' . $this->currentYear;
        $info->cCVV         = '1234';
        $info->cKartenTyp   = 'VISA';
        $info->cInhaber     = $currentLangISO === 'ger' ? 'Max Mustermann' : 'John Doe';

        return $info;
    }

    private function getOrderDeliveryAddress(LanguageHelper|null $languageHelper = null): stdClass
    {
        $languageHelper            ??= Shop::Lang();
        $currentLangISO            = $languageHelper->getIso();
        $address                   = new stdClass();
        $address->kLieferadresse   = 1;
        $address->cAnrede          = 'm';
        $address->cAnredeLocalized = $languageHelper->get('salutationM');
        $address->cVorname         = $currentLangISO === 'ger' ? 'Max' : 'John';
        $address->cNachname        = $currentLangISO === 'ger' ? 'Mustermann' : 'Doe';
        $address->cStrasse         = $currentLangISO === 'ger' ? 'Musterlieferstr.' : 'Doe street';
        $address->cHausnummer      = '77';
        $address->cAdressZusatz    = $currentLangISO === 'ger' ? '2. Etage' : '2. floor';
        $address->cPLZ             = '12345';
        $address->cOrt             = $currentLangISO === 'ger' ? 'Musterlieferstadt' : 'Delivery city';
        $address->cBundesland      = $currentLangISO === 'ger' ? 'Lieferbundesland' : 'Delivery state';
        $address->cLand            = $currentLangISO === 'ger' ? 'Lieferland ISO' : 'Delivery country code';
        $address->cTel             = '112345678';
        $address->cMobil           = '123456789';
        $address->cFax             = '12345678909';
        $address->cMail            = $currentLangISO === 'ger' ? 'max.musterman@example.com' : 'john.doe@example.com';
        $address->angezeigtesLand  = $currentLangISO === 'ger' ? 'Lieferland' : 'Delivery country';

        return $address;
    }

    /**
     * @return stdClass[]
     */
    private function getOrderItems(string $currentLangISO = 'ger'): array
    {
        $items                          = [];
        $item                           = new stdClass();
        $item->kArtikel                 = 1;
        $item->cName                    = $currentLangISO === 'ger'
            ? 'LAN Festplatte IPDrive'
            : 'LAN hard drive IPDrive';
        $item->cArtNr                   = 'AF8374';
        $item->cEinheit                 = $currentLangISO === 'ger' ? 'Stk.' : 'Pcs';
        $item->cLieferstatus            = $currentLangISO === 'ger' ? '3-4 Tage' : '3-4 days';
        $item->fPreisEinzelNetto        = 111.2069;
        $item->fPreis                   = 368.1069;
        $item->fMwSt                    = 19;
        $item->nAnzahl                  = 2;
        $item->nPosTyp                  = 1;
        $item->cHinweis                 = $currentLangISO === 'ger'
            ? 'Hinweistext zum Artikel'
            : 'Hint text for the item';
        $item->cGesamtpreisLocalized[0] = '278,00 EUR';
        $item->cGesamtpreisLocalized[1] = '239,66 EUR';
        $item->cEinzelpreisLocalized[0] = '139,00 EUR';
        $item->cEinzelpreisLocalized[1] = '119,83 EUR';

        $item->WarenkorbPosEigenschaftArr                           = [];
        $item->WarenkorbPosEigenschaftArr[0]                        = new stdClass();
        $item->WarenkorbPosEigenschaftArr[0]->cEigenschaftName      = $currentLangISO === 'ger'
            ? 'Kapazität'
            : 'Capacity';
        $item->WarenkorbPosEigenschaftArr[0]->cEigenschaftWertName  = '4000GB';
        $item->WarenkorbPosEigenschaftArr[0]->fAufpreis             = 128.45;
        $item->WarenkorbPosEigenschaftArr[0]->cAufpreisLocalized[0] = '149,00 EUR';
        $item->WarenkorbPosEigenschaftArr[0]->cAufpreisLocalized[1] = '128,45 EUR';

        $nextYear                  = \date('Y', \strtotime('+1 year'));
        $item->nAusgeliefert       = 1;
        $item->nAusgeliefertGesamt = 1;
        $item->nOffenGesamt        = 1;
        $item->dMHD                = $nextYear . '-01-01';
        $item->dMHD_de             = '01.01.' . $nextYear;
        $item->cChargeNr           = 'A2100698.b12';
        $item->cSeriennummer       = '465798132756';

        $items[] = $item;

        $item                           = new stdClass();
        $item->kArtikel                 = 2;
        $item->cName                    = $currentLangISO === 'ger' ? 'Klappstuhl' : 'Folding chair';
        $item->cArtNr                   = 'KS332';
        $item->cEinheit                 = $currentLangISO === 'ger' ? 'Stk.' : 'Pcs';
        $item->cLieferstatus            = $currentLangISO === 'ger' ? '1 Woche' : '1 week';
        $item->fPreisEinzelNetto        = 100;
        $item->fPreis                   = 200;
        $item->fMwSt                    = 19;
        $item->nAnzahl                  = 1;
        $item->nPosTyp                  = 2;
        $item->cHinweis                 = $currentLangISO === 'ger' ? 'Hinweistext zum Artikel' : 'Product hint text';
        $item->cGesamtpreisLocalized[0] = '238,00 EUR';
        $item->cGesamtpreisLocalized[1] = '200,00 EUR';
        $item->cEinzelpreisLocalized[0] = '238,00 EUR';
        $item->cEinzelpreisLocalized[1] = '200,00 EUR';

        $item->nAusgeliefert       = 1;
        $item->nAusgeliefertGesamt = 1;
        $item->nOffenGesamt        = 0;

        $items[] = $item;

        return $items;
    }

    private function getNewsletterRecipient(int $languageID): stdClass
    {
        $recipient                     = new stdClass();
        $recipient->kSprache           = $languageID;
        $recipient->kKunde             = null;
        $recipient->nAktiv             = 0;
        $recipient->cAnrede            = 'w';
        $recipient->cVorname           = 'Erika';
        $recipient->cNachname          = 'Mustermann';
        $recipient->cEmail             = 'test@example.com';
        $recipient->cOptCode           = 'acc4cedb690aed6161d6034417925b97f2';
        $recipient->cLoeschCode        = 'dc1338521613c3cfeb1988261029fe3058';
        $recipient->dEingetragen       = 'NOW()';
        $recipient->dLetzterNewsletter = '_DBNULL_';
        $recipient->cLoeschURL         = Shop::getURL() . '/?'
            . \QUERY_PARAM_OPTIN_CODE . '=' . $recipient->cLoeschCode;
        $recipient->cFreischaltURL     = Shop::getURL() . '/?'
            . \QUERY_PARAM_OPTIN_CODE . '=' . $recipient->cOptCode;

        return $recipient;
    }

    private function getProduct(string $currentLangISO = 'ger'): stdClass
    {
        $product                    = new stdClass();
        $product->cName             = $currentLangISO === 'ger' ? 'LAN Festplatte IPDrive' : 'LAN hard drive IPDrive';
        $product->cArtNr            = 'AF8374';
        $product->cEinheit          = $currentLangISO === 'ger' ? 'Stk.' : 'Pcs.';
        $product->cLieferstatus     = $currentLangISO === 'ger' ? '3-4 Tage' : '3-4 days';
        $product->fPreisEinzelNetto = 111.2069;
        $product->fPreis            = 368.1069;
        $product->fMwSt             = 19;
        $product->nAnzahl           = 1;
        $product->cURL              = $currentLangISO === 'ger' ? 'LAN-Festplatte-IPDrive' : 'LAN-hard-drive-IPDrive';

        return $product;
    }

    private function getBonus(): stdClass
    {
        $bonus                          = new stdClass();
        $bonus->kKunde                  = 1379;
        $bonus->fGuthaben               = '2,00 &euro';
        $bonus->nBonuspunkte            = 0;
        $bonus->dErhalten               = 'NOW()';
        $bonus->fGuthabenBonusLocalized = Preise::getLocalizedPriceString(2.00);

        return $bonus;
    }

    private function getOptin(): stdClass
    {
        $optin                  = new stdClass();
        $optin->activationURL   = 'http://example.com/testproduct?' . \QUERY_PARAM_OPTIN_CODE . '=ac123456789';
        $optin->deactivationURL = 'http://example.com/testproduct?' . \QUERY_PARAM_OPTIN_CODE . '=dc123456789';

        return $optin;
    }
}
