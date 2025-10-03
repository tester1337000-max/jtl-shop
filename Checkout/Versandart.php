<?php

declare(strict_types=1);

namespace JTL\Checkout;

use Illuminate\Support\Collection;
use JTL\Country\Country;
use JTL\Helpers\GeneralObject;
use JTL\MagicCompatibilityTrait;
use JTL\Shop;
use stdClass;

/**
 * Class Versandart
 * @package JTL\Checkout
 */
class Versandart
{
    use MagicCompatibilityTrait;

    public ?int $kVersandart = null;

    public ?int $kVersandberechnung = null;

    public ?string $cVersandklassen = null;

    public ?string $cName = null;

    public ?string $cLaender = null;

    public ?string $cAnzeigen = null;

    public ?string $cKundengruppen;

    public ?string $cBild = null;

    public ?string $cNurAbhaengigeVersandart = null;

    public int $nSort = 0;

    public ?string $fPreis = null;

    public float $minValidValue = 0.0;

    public ?string $fVersandkostenfreiAbX = null;

    public ?string $fDeckelung = null;

    /**
     * @var array<string, stdClass>
     */
    public array $oVersandartSprache_arr = [];

    /**
     * @var array<object{kVersandartStaffel: int, kVersandart: int, fBis: string, fPreis: string}&stdClass>
     */
    public array $oVersandartStaffel_arr = [];

    /**
     * @var 'N'|'Y'
     */
    public string $cSendConfirmationMail = 'N';

    /**
     * @var 'N'|'Y'
     */
    public string $cIgnoreShippingProposal = 'N';

    public int $nMinLiefertage = 0;

    public int $nMaxLiefertage = 0;

    public ?string $eSteuer = null;

    public ?Country $country = null;

    /**
     * @var string[]
     */
    public array $cPriceLocalized = [];

    /**
     * @var Collection<int, ShippingSurcharge>|null
     */
    public ?Collection $shippingSurcharges = null;

    /**
     * @var array<string, string>
     */
    public static array $mapping = [
        'cCountryCode' => 'CountryCode'
    ];

    public function __construct(int $id = 0)
    {
        if ($id > 0) {
            $this->load($id);
        }
    }

    private function load(int $id): void
    {
        $cache   = Shop::Container()->getCache();
        $cacheID = 'shippingmethod_' . $id;
        if (($method = $cache->get($cacheID)) === false) {
            $this->loadFromDB($id);
            $cache->set($cacheID, $this, [\CACHING_GROUP_OPTION]);
        } else {
            foreach (\get_object_vars($method) as $idx => $value) {
                $this->$idx = $value;
            }
        }
    }

    public function loadFromDB(int $id): int
    {
        $db  = Shop::Container()->getDB();
        $obj = $db->select('tversandart', 'kVersandart', $id);
        if ($obj === null || !$obj->kVersandart) {
            return 0;
        }
        $this->kVersandart              = (int)$obj->kVersandart;
        $this->nSort                    = (int)$obj->nSort;
        $this->kVersandberechnung       = (int)$obj->kVersandberechnung;
        $this->nMinLiefertage           = (int)$obj->nMinLiefertage;
        $this->nMaxLiefertage           = (int)$obj->nMaxLiefertage;
        $this->cVersandklassen          = $obj->cVersandklassen;
        $this->cName                    = $obj->cName;
        $this->cLaender                 = $obj->cLaender;
        $this->cAnzeigen                = $obj->cAnzeigen;
        $this->cKundengruppen           = $obj->cKundengruppen;
        $this->cBild                    = $obj->cBild;
        $this->eSteuer                  = $obj->eSteuer;
        $this->fPreis                   = $obj->fPreis;
        $this->fVersandkostenfreiAbX    = $obj->fVersandkostenfreiAbX;
        $this->fDeckelung               = $obj->fDeckelung;
        $this->cNurAbhaengigeVersandart = $obj->cNurAbhaengigeVersandart;
        $this->cSendConfirmationMail    = $obj->cSendConfirmationMail;
        $this->cIgnoreShippingProposal  = $obj->cIgnoreShippingProposal;

        $localized = $db->selectAll(
            'tversandartsprache',
            'kVersandart',
            $this->kVersandart
        );
        foreach ($localized as $translation) {
            $translation->kVersandart = (int)$translation->kVersandart;

            $this->oVersandartSprache_arr[$translation->cISOSprache] = $translation;
        }
        $this->oVersandartStaffel_arr = $db->selectAll(
            'tversandartstaffel',
            'kVersandart',
            $this->kVersandart
        );
        foreach ($this->oVersandartStaffel_arr as $item) {
            $item->kVersandartStaffel = (int)$item->kVersandartStaffel;
            $item->kVersandart        = (int)$item->kVersandart;
        }

        $this->loadShippingSurcharges();

        return 1;
    }

    public function insertInDB(): int
    {
        $obj = GeneralObject::copyMembers($this);
        unset(
            $obj->oVersandartSprache_arr,
            $obj->oVersandartStaffel_arr,
            $obj->nMinLiefertage,
            $obj->nMaxLiefertage
        );
        $this->kVersandart = Shop::Container()->getDB()->insert('tversandart', $obj);

        return $this->kVersandart;
    }

    public function updateInDB(): int
    {
        $obj = GeneralObject::copyMembers($this);
        unset(
            $obj->oVersandartSprache_arr,
            $obj->oVersandartStaffel_arr,
            $obj->nMinLiefertage,
            $obj->nMaxLiefertage
        );

        return Shop::Container()->getDB()->update('tversandart', 'kVersandart', $obj->kVersandart, $obj);
    }

    public static function deleteInDB(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }
        $db = Shop::Container()->getDB();
        $db->delete('tversandart', 'kVersandart', $id);
        $db->delete('tversandartsprache', 'kVersandart', $id);
        $db->delete('tversandartzahlungsart', 'kVersandart', $id);
        $db->delete('tversandartstaffel', 'kVersandart', $id);
        $db->queryPrepared(
            'DELETE tversandzuschlag, tversandzuschlagplz, tversandzuschlagsprache
                FROM tversandzuschlag
                LEFT JOIN tversandzuschlagplz 
                    ON tversandzuschlagplz.kVersandzuschlag = tversandzuschlag.kVersandzuschlag
                LEFT JOIN tversandzuschlagsprache 
                    ON tversandzuschlagsprache.kVersandzuschlag = tversandzuschlag.kVersandzuschlag
                WHERE tversandzuschlag.kVersandart = :fid',
            ['fid' => $id]
        );

        return true;
    }

    public static function cloneShipping(int $id): bool
    {
        $sections = [
            'tversandartsprache'     => 'kVersandart',
            'tversandartstaffel'     => 'kVersandartStaffel',
            'tversandartzahlungsart' => 'kVersandartZahlungsart',
            'tversandzuschlag'       => 'kVersandzuschlag'
        ];

        $method = Shop::Container()->getDB()->select('tversandart', 'kVersandart', $id);
        if ($method !== null && $method->kVersandart > 0) {
            unset($method->kVersandart);
            $kVersandartNew = Shop::Container()->getDB()->insert('tversandart', $method);
            if ($kVersandartNew > 0) {
                foreach ($sections as $name => $key) {
                    $items = self::getShippingSection($name, 'kVersandart', $id);
                    self::cloneShippingSection($items, $name, 'kVersandart', $kVersandartNew, $key);
                }

                return true;
            }
        }

        return false;
    }

    /**
     * @return stdClass[]
     */
    private static function getShippingSection(string $table, string $key, int $value): array
    {
        if ($value > 0 && \mb_strlen($table) > 0 && \mb_strlen($key) > 0) {
            return Shop::Container()->getDB()->selectAll($table, $key, $value);
        }

        return [];
    }

    /**
     * @param stdClass[]  $objects
     * @param string      $table
     * @param string      $key
     * @param int         $value
     * @param null|string $unsetKey
     */
    private static function cloneShippingSection(
        array $objects,
        string $table,
        string $key,
        int $value,
        ?string $unsetKey = null
    ): void {
        if ($value <= 0 || \count($objects) === 0 || \mb_strlen($key) === 0) {
            return;
        }
        $db = Shop::Container()->getDB();
        foreach ($objects as $item) {
            $primary = $item->$unsetKey;
            if ($unsetKey !== null) {
                unset($item->$unsetKey);
            }
            $item->$key = $value;
            if ($table === 'tversandartzahlungsart' && empty($item->fAufpreis)) {
                $item->fAufpreis = 0;
            }
            $id = $db->insert($table, $item);
            if ($id > 0 && $table === 'tversandzuschlag') {
                self::cloneShippingSectionSpecial((int)$primary, $id);
            }
        }
    }

    private static function cloneShippingSectionSpecial(int $oldKey, int $newKey): void
    {
        if ($oldKey <= 0 || $newKey <= 0) {
            return;
        }
        $sections = [
            'tversandzuschlagplz'     => 'kVersandzuschlagPlz',
            'tversandzuschlagsprache' => 'kVersandzuschlag'
        ];
        foreach ($sections as $section => $subKey) {
            $subSections = self::getShippingSection($section, 'kVersandzuschlag', $oldKey);

            self::cloneShippingSection($subSections, $section, 'kVersandzuschlag', $newKey, $subKey);
        }
    }

    /**
     * load zip surcharges for shipping method
     */
    public function loadShippingSurcharges(): void
    {
        $this->setShippingSurcharges(
            Shop::Container()->getDB()->getCollection(
                'SELECT kVersandzuschlag AS id
                    FROM tversandzuschlag
                    WHERE kVersandart = :kVersandart
                    ORDER BY kVersandzuschlag DESC',
                ['kVersandart' => $this->kVersandart]
            )->map(static fn(stdClass $surcharge): ShippingSurcharge => new ShippingSurcharge((int)$surcharge->id))
        );
    }

    /**
     * @return Collection<int, ShippingSurcharge>
     */
    public function getShippingSurchargesForCountry(string $iso): Collection
    {
        return $this->getShippingSurcharges()->filter(static fn(ShippingSurcharge $sc): bool => $sc->getISO() === $iso);
    }

    public function getShippingSurchargeForZip(string $zip, string $iso): ?ShippingSurcharge
    {
        return $this->getShippingSurchargesForCountry($iso)
            ->first(static fn(ShippingSurcharge $surcharge): bool => $surcharge->hasZIPCode($zip));
    }

    /**
     * @return Collection<int, ShippingSurcharge>
     */
    public function getShippingSurcharges(): Collection
    {
        return $this->shippingSurcharges ?? new Collection();
    }

    /**
     * @param Collection<int, ShippingSurcharge> $shippingSurcharges
     */
    private function setShippingSurcharges(Collection $shippingSurcharges): self
    {
        $this->shippingSurcharges = $shippingSurcharges;

        return $this;
    }

    public function getCountryCode(): string
    {
        return $this->country !== null ? $this->country->getISO() : '';
    }

    public function setCountryCode(string $countryCode): void
    {
        $this->country = Shop::Container()->getCountryService()->getCountry($countryCode);
    }
}
