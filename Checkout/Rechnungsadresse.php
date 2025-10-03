<?php

declare(strict_types=1);

namespace JTL\Checkout;

use JTL\Customer\Customer;
use JTL\Language\LanguageHelper;
use JTL\Shop;
use stdClass;

/**
 * Class Rechnungsadresse
 * @package JTL\Checkout
 */
class Rechnungsadresse extends Adresse
{
    public ?int $kRechnungsadresse = null;

    public ?int $kKunde = null;

    public ?string $cUSTID = null;

    public ?string $cWWW = null;

    public ?string $cAnredeLocalized = null;

    public ?string $angezeigtesLand = null;

    public function __construct(int $id = 0)
    {
        if ($id > 0) {
            $this->loadFromDB($id);
        }
    }

    public function loadFromDB(int $id): int|self
    {
        $obj = Shop::Container()->getDB()->select('trechnungsadresse', 'kRechnungsadresse', $id);

        if ($obj === null || $obj->kRechnungsadresse < 1) {
            return 0;
        }
        $this->kKunde            = (int)$obj->kKunde;
        $this->kRechnungsadresse = (int)$obj->kRechnungsadresse;
        $this->cAnrede           = $obj->cAnrede;
        $this->cVorname          = $obj->cVorname;
        $this->cNachname         = $obj->cNachname;
        $this->cTitel            = $obj->cTitel;
        $this->cFirma            = $obj->cFirma;
        $this->cZusatz           = $obj->cZusatz;
        $this->cStrasse          = $obj->cStrasse;
        $this->cHausnummer       = $obj->cHausnummer;
        $this->cAdressZusatz     = $obj->cAdressZusatz;
        $this->cPLZ              = $obj->cPLZ;
        $this->cOrt              = $obj->cOrt;
        $this->cBundesland       = $obj->cBundesland;
        $this->cLand             = $obj->cLand;
        $this->cTel              = $obj->cTel;
        $this->cMobil            = $obj->cMobil;
        $this->cFax              = $obj->cFax;
        $this->cUSTID            = $obj->cUSTID;
        $this->cWWW              = $obj->cWWW;
        $this->cMail             = $obj->cMail;
        $this->cAnredeLocalized  = Customer::mapSalutation($this->cAnrede, 0, $this->kKunde);
        // Workaround for WAWI-39370
        $this->cLand           = self::checkISOCountryCode($this->cLand ?? '');
        $this->angezeigtesLand = LanguageHelper::getCountryCodeByCountryName($this->cLand);
        if ($this->kRechnungsadresse > 0) {
            $this->decrypt();
        }

        \executeHook(\HOOK_RECHNUNGSADRESSE_CLASS_LOADFROMDB);

        return $this;
    }

    public function insertInDB(): int
    {
        $this->encrypt();
        $ins                = new stdClass();
        $ins->kKunde        = $this->kKunde;
        $ins->cAnrede       = $this->cAnrede;
        $ins->cTitel        = $this->cTitel;
        $ins->cVorname      = $this->cVorname;
        $ins->cNachname     = $this->cNachname;
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
        $ins->cUSTID        = $this->cUSTID;
        $ins->cWWW          = $this->cWWW;
        $ins->cMail         = $this->cMail;

        $this->kRechnungsadresse = Shop::Container()->getDB()->insert('trechnungsadresse', $ins);
        $this->decrypt();
        // Anrede mappen
        $this->cAnredeLocalized = $this->mappeAnrede($this->cAnrede);

        return $this->kRechnungsadresse;
    }

    public function updateInDB(): int
    {
        $this->encrypt();
        $obj = $this->toObject();

        $obj->cLand = self::checkISOCountryCode($obj->cLand);

        unset($obj->angezeigtesLand, $obj->cAnredeLocalized);

        $res = Shop::Container()->getDB()->update(
            'trechnungsadresse',
            'kRechnungsadresse',
            $obj->kRechnungsadresse,
            $obj
        );
        $this->decrypt();
        // Anrede mappen
        $this->cAnredeLocalized = $this->mappeAnrede($this->cAnrede);

        return $res;
    }

    /**
     * @return array<string, mixed>
     */
    public function gibRechnungsadresseAssoc(): array
    {
        if ($this->kRechnungsadresse > 0) {
            // wawi needs these attributes in exactly this order
            return [
                'cAnrede'           => $this->cAnrede,
                'cTitel'            => $this->cTitel,
                'cVorname'          => $this->cVorname,
                'cNachname'         => $this->cNachname,
                'cFirma'            => $this->cFirma,
                'cStrasse'          => $this->cStrasse,
                'cAdressZusatz'     => $this->cAdressZusatz,
                'cPLZ'              => $this->cPLZ,
                'cOrt'              => $this->cOrt,
                'cBundesland'       => $this->cBundesland,
                'cLand'             => $this->cLand,
                'cTel'              => $this->cTel,
                'cMobil'            => $this->cMobil,
                'cFax'              => $this->cFax,
                'cUSTID'            => $this->cUSTID,
                'cWWW'              => $this->cWWW,
                'cMail'             => $this->cMail,
                'cZusatz'           => $this->cZusatz,
                'cAnredeLocalized'  => $this->cAnredeLocalized,
                'cHausnummer'       => $this->cHausnummer,
                // kXXX variables will be set as attribute nodes by syncinclude.php::buildAttributes
                'kRechnungsadresse' => $this->kRechnungsadresse,
                'kKunde'            => $this->kKunde,
            ];
        }

        return [];
    }
}
