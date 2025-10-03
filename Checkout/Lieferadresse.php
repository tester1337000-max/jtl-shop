<?php

declare(strict_types=1);

namespace JTL\Checkout;

use JTL\Customer\Customer;
use JTL\Customer\Registration\Form as RegistrationForm;
use JTL\Helpers\Text;
use JTL\Language\LanguageHelper;
use JTL\Session\Frontend;
use JTL\Shop;
use JTL\Staat;
use stdClass;

/**
 * Class Lieferadresse
 * @package JTL\Checkout
 */
class Lieferadresse extends Adresse
{
    public ?int $kLieferadresse = null;

    public ?int $kKunde = null;

    public ?string $cAnredeLocalized = null;

    public ?string $angezeigtesLand = null;

    public function __construct(int $id = 0)
    {
        if ($id > 0) {
            $this->loadFromDB($id);
        }
    }

    public function loadFromDB(int $kLieferadresse): self|int
    {
        $obj = Shop::Container()->getDB()->select('tlieferadresse', 'kLieferadresse', $kLieferadresse);
        if ($obj === null || $obj->kLieferadresse < 1) {
            return 0;
        }
        $this->kLieferadresse = (int)$obj->kLieferadresse;
        $this->kKunde         = (int)$obj->kKunde;
        $this->cAnrede        = $obj->cAnrede;
        $this->cVorname       = $obj->cVorname;
        $this->cNachname      = $obj->cNachname;
        $this->cTitel         = $obj->cTitel;
        $this->cFirma         = $obj->cFirma;
        $this->cZusatz        = $obj->cZusatz;
        $this->cStrasse       = $obj->cStrasse;
        $this->cHausnummer    = $obj->cHausnummer;
        $this->cAdressZusatz  = $obj->cAdressZusatz;
        $this->cPLZ           = $obj->cPLZ;
        $this->cOrt           = $obj->cOrt;
        $this->cBundesland    = $obj->cBundesland;
        $this->cLand          = $obj->cLand;
        $this->cTel           = $obj->cTel;
        $this->cMobil         = $obj->cMobil;
        $this->cFax           = $obj->cFax;
        $this->cMail          = $obj->cMail;

        $this->cAnredeLocalized = Customer::mapSalutation($this->cAnrede, 0, $this->kKunde);
        // Workaround for WAWI-39370
        $this->cLand           = self::checkISOCountryCode($this->cLand ?? '');
        $this->angezeigtesLand = LanguageHelper::getCountryCodeByCountryName($this->cLand);
        if ($this->kLieferadresse > 0) {
            $this->decrypt();
        }

        \executeHook(\HOOK_LIEFERADRESSE_CLASS_LOADFROMDB);

        return $this;
    }

    public function insertInDB(): int
    {
        $this->encrypt();
        $ins                = new stdClass();
        $ins->kKunde        = $this->kKunde;
        $ins->cAnrede       = $this->cAnrede;
        $ins->cVorname      = $this->cVorname;
        $ins->cNachname     = $this->cNachname;
        $ins->cTitel        = $this->cTitel;
        $ins->cFirma        = $this->cFirma;
        $ins->cZusatz       = $this->cZusatz;
        $ins->cStrasse      = $this->cStrasse;
        $ins->cHausnummer   = $this->cHausnummer;
        $ins->cAdressZusatz = $this->cAdressZusatz;
        $ins->cPLZ          = $this->cPLZ;
        $ins->cOrt          = $this->cOrt;
        $ins->cBundesland   = $this->cBundesland;
        $ins->cLand         = self::checkISOCountryCode($this->cLand ?? '');
        $ins->cTel          = $this->cTel;
        $ins->cMobil        = $this->cMobil;
        $ins->cFax          = $this->cFax;
        $ins->cMail         = $this->cMail;

        $this->kLieferadresse = Shop::Container()->getDB()->insert('tlieferadresse', $ins);
        $this->decrypt();
        // Anrede mappen
        $this->cAnredeLocalized = $this->mappeAnrede($this->cAnrede);

        return $this->kLieferadresse;
    }

    public function updateInDB(): int
    {
        $this->encrypt();
        $obj = $this->toObject();

        $obj->cLand = self::checkISOCountryCode($obj->cLand);
        unset($obj->angezeigtesLand, $obj->cAnredeLocalized);
        $res = Shop::Container()->getDB()->update('tlieferadresse', 'kLieferadresse', $obj->kLieferadresse, $obj);
        $this->decrypt();
        $this->cAnredeLocalized = $this->mappeAnrede($this->cAnrede);

        return $res;
    }

    /**
     * get shipping address
     *
     * @return array<string, mixed>
     */
    public function gibLieferadresseAssoc(): array
    {
        return $this->kLieferadresse > 0
            ? $this->toArray()
            : [];
    }

    /**
     * @param array<mixed> $post
     * @former getLieferdaten()
     * @since 5.2.0
     */
    public static function createFromPost(array $post): self
    {
        $post                             = Text::filterXSS($post);
        $shippingAddress                  = new self();
        $shippingAddress->cAnrede         = $post['anrede'] ?? null;
        $shippingAddress->cVorname        = $post['vorname'];
        $shippingAddress->cNachname       = $post['nachname'];
        $shippingAddress->cStrasse        = $post['strasse'];
        $shippingAddress->cHausnummer     = $post['hausnummer'];
        $shippingAddress->cPLZ            = $post['plz'];
        $shippingAddress->cOrt            = $post['ort'];
        $shippingAddress->cLand           = $post['land'];
        $shippingAddress->cMail           = $post['email'] ?? '';
        $shippingAddress->cTel            = $post['tel'] ?? null;
        $shippingAddress->cFax            = $post['fax'] ?? null;
        $shippingAddress->cFirma          = $post['firma'] ?? null;
        $shippingAddress->cZusatz         = $post['firmazusatz'] ?? null;
        $shippingAddress->cTitel          = $post['titel'] ?? null;
        $shippingAddress->cAdressZusatz   = $post['adresszusatz'] ?? null;
        $shippingAddress->cMobil          = $post['mobil'] ?? null;
        $shippingAddress->cBundesland     = $post['bundesland'] ?? null;
        $shippingAddress->angezeigtesLand = LanguageHelper::getCountryCodeByCountryName($shippingAddress->cLand);

        if (!empty($shippingAddress->cBundesland)) {
            $region = Staat::getRegionByIso($shippingAddress->cBundesland, $shippingAddress->cLand);
            if ($region !== null) {
                $shippingAddress->cBundesland = $region->cName;
            }
        }

        return $shippingAddress;
    }

    /**
     * @param array<mixed>|null $post
     * @return Lieferadresse
     * @former setzeLieferadresseAusRechnungsadresse()
     * @since 5.2.0
     */
    public static function createFromShippingAddress(?array $post = null): Lieferadresse
    {
        $customer = isset($post['land'])
            ? (new RegistrationForm())->getCustomerData($post, false)
            : Frontend::getCustomer();

        $shippingAddress                          = new self();
        $shippingAddress->kKunde                  = $customer->getID();
        $shippingAddress->cAnrede                 = $customer->cAnrede;
        $shippingAddress->cVorname                = $customer->cVorname;
        $shippingAddress->cNachname               = $customer->cNachname;
        $shippingAddress->cStrasse                = $customer->cStrasse;
        $shippingAddress->cHausnummer             = $customer->cHausnummer;
        $shippingAddress->cPLZ                    = $customer->cPLZ;
        $shippingAddress->cOrt                    = $customer->cOrt;
        $shippingAddress->cLand                   = $customer->cLand;
        $shippingAddress->cMail                   = $customer->cMail;
        $shippingAddress->cTel                    = $customer->cTel;
        $shippingAddress->cFax                    = $customer->cFax;
        $shippingAddress->cFirma                  = $customer->cFirma;
        $shippingAddress->cZusatz                 = $customer->cZusatz;
        $shippingAddress->cTitel                  = $customer->cTitel;
        $shippingAddress->cAdressZusatz           = $customer->cAdressZusatz;
        $shippingAddress->cMobil                  = $customer->cMobil;
        $shippingAddress->cBundesland             = $customer->cBundesland;
        $shippingAddress->angezeigtesLand         = LanguageHelper::getCountryCodeByCountryName(
            $shippingAddress->cLand ?? ''
        );
        $_SESSION['Lieferadresse']                = $shippingAddress;
        $_SESSION['preferredDeliveryCountryCode'] = $shippingAddress->cLand;

        return $shippingAddress;
    }

    /**
     * @since 5.5.0
     */
    public function isValidForCheckout(): bool
    {
        return empty($this->cLand) === false
            && empty($this->cPLZ) === false
            && empty($this->cOrt) === false
            && empty($this->cStrasse) === false
            && empty($this->cHausnummer) === false
            && empty($this->cNachname) === false;
    }
}
