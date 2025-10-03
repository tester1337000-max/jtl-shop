<?php

declare(strict_types=1);

namespace JTL;

use JTL\Cache\JTLCacheInterface;
use JTL\Country\Country;
use JTL\DB\DbInterface;
use stdClass;

/**
 * Class Firma
 * @package JTL
 */
class Firma
{
    public ?string $cName = null;

    public ?string $cUnternehmer = null;

    public ?string $cStrasse = null;

    public ?string $cHausnummer = null;

    public ?string $cPLZ = null;

    public ?string $cOrt = null;

    public ?string $cLand = null;

    public ?string $cTel = null;

    public ?string $cFax = null;

    public ?string $cEMail = null;

    public ?string $cWWW = null;

    public ?string $cKontoinhaber = null;

    public ?string $cBLZ = null;

    public ?string $cKontoNr = null;

    public ?string $cBank = null;

    public ?string $cUSTID = null;

    public ?string $cSteuerNr = null;

    public ?string $cIBAN = null;

    public ?string $cBIC = null;

    public ?Country $country = null;

    private DbInterface $db;

    private JTLCacheInterface $cache;

    public function __construct(bool $load = true, ?DbInterface $db = null, ?JTLCacheInterface $cache = null)
    {
        $this->db    = $db ?? Shop::Container()->getDB();
        $this->cache = $cache ?? Shop::Container()->getCache();
        if ($load) {
            $this->loadFromDB();
        }
    }

    public function loadFromDB(): self
    {
        $cached = false;
        if (($company = $this->cache->get('jtl_company')) !== false) {
            $cached = true;
            foreach (\get_object_vars($company) as $k => $v) {
                $this->$k = $v;
            }
        } else {
            $countryHelper = Shop::Container()->getCountryService();
            $obj           = $this->db->getSingleObject('SELECT * FROM tfirma LIMIT 1');
            if ($obj !== null) {
                foreach (\get_object_vars($obj) as $k => $v) {
                    $this->$k = $v;
                }
                $iso = $this->cLand !== null ? $countryHelper->getIsoByCountryName($this->cLand) : null;
                if ($iso !== null) {
                    $this->country = $countryHelper->getCountry($iso);
                    $obj->country  = $this->country;
                }
                $this->cache->set('jtl_company', $obj, [\CACHING_GROUP_CORE]);
            }
        }
        \executeHook(\HOOK_FIRMA_CLASS_LOADFROMDB, ['instance' => $this, 'cached' => $cached]);

        return $this;
    }

    public function updateInDB(): int
    {
        $obj                = new stdClass();
        $obj->cName         = $this->cName;
        $obj->cUnternehmer  = $this->cUnternehmer;
        $obj->cStrasse      = $this->cStrasse;
        $obj->cHausnummer   = $this->cHausnummer;
        $obj->cPLZ          = $this->cPLZ;
        $obj->cOrt          = $this->cOrt;
        $obj->cLand         = $this->cLand;
        $obj->cTel          = $this->cTel;
        $obj->cFax          = $this->cFax;
        $obj->cEMail        = $this->cEMail;
        $obj->cWWW          = $this->cWWW;
        $obj->cKontoinhaber = $this->cKontoinhaber;
        $obj->cBLZ          = $this->cBLZ;
        $obj->cKontoNr      = $this->cKontoNr;
        $obj->cBank         = $this->cBank;
        $obj->cUSTID        = $this->cUSTID;
        $obj->cSteuerNr     = $this->cSteuerNr;
        $obj->cIBAN         = $this->cIBAN;
        $obj->cBIC          = $this->cBIC;

        return $this->db->update('tfirma', '1', '1', $obj);
    }
}
