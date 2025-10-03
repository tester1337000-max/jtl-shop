<?php

declare(strict_types=1);

namespace JTL\Checkout;

use JTL\Catalog\Product\Preise;
use JTL\MagicCompatibilityTrait;
use JTL\Shop;
use stdClass;

/**
 * Class ShippingSurcharge
 * @package JTL\Checkout
 */
class ShippingSurcharge
{
    use MagicCompatibilityTrait;

    /**
     * @var array<string, string>
     */
    protected static array $mapping = [
        'kVersandzuschlag' => 'ID',
        'cISO'             => 'ISO',
        'cName'            => 'Title',
        'fZuschlag'        => 'Surcharge',
        'kVersandart'      => 'ShippingMethod',
        'cPreisLocalized'  => 'PriceLocalized',
        'angezeigterName'  => 'Name'
    ];

    public ?int $ID = null;

    public ?string $ISO = null;

    public ?string $title = null;

    public float $surcharge = 0.0;

    public ?int $shippingMethod = null;

    /**
     * @var string[]|null
     */
    public ?array $ZIPCodes = null;

    /**
     * @var ShippingSurchargeArea[]
     */
    public array $ZIPAreas = [];

    /**
     * @var string[]
     */
    public array $names = [];

    public ?string $priceLocalized = null;

    public function __construct(int $id = 0)
    {
        if ($id > 0) {
            $this->setID($id)
                ->loadFromDB($id);
        }
    }

    public function loadFromDB(int $id): void
    {
        $db        = Shop::Container()->getDB();
        $surcharge = $db->getSingleObject(
            'SELECT * 
                FROM tversandzuschlag
                WHERE kVersandzuschlag = :id',
            ['id' => $id]
        );
        if ($surcharge === null) {
            return;
        }

        $this->setTitle($surcharge->cName)
            ->setISO($surcharge->cISO)
            ->setSurcharge((float)$surcharge->fZuschlag)
            ->setShippingMethod((int)$surcharge->kVersandart)
            ->setPriceLocalized();

        $zips = $db->getObjects(
            'SELECT vzp.cPLZ, vzp.cPLZAb, vzp.cPLZBis 
                FROM tversandzuschlag AS vz
                JOIN tversandzuschlagplz AS vzp USING(kVersandzuschlag) 
                WHERE vz.kVersandzuschlag = :id',
            ['id' => $id]
        );
        foreach ($zips as $zip) {
            if (!empty($zip->cPLZ)) {
                $this->setZIPCode($zip->cPLZ);
            } elseif (!empty($zip->cPLZAb) && !empty($zip->cPLZBis)) {
                $this->setZIPArea($zip->cPLZAb, $zip->cPLZBis);
            }
        }

        $names = $db->getObjects(
            'SELECT vzs.cName, s.kSprache 
                FROM tversandzuschlag AS vz
                JOIN tversandzuschlagsprache AS vzs USING(kVersandzuschlag) 
                JOIN tsprache AS s ON s.cISO = vzs.cISOSprache
                WHERE vz.kVersandzuschlag = :id',
            ['id' => $id]
        );
        foreach ($names as $name) {
            $this->setName($name->cName, (int)$name->kSprache);
        }
    }

    public function save(): void
    {
        $db                          = Shop::Container()->getDB();
        $surcharge                   = new stdClass();
        $surcharge->cName            = $this->getTitle();
        $surcharge->kVersandart      = $this->getShippingMethod();
        $surcharge->cIso             = $this->getISO();
        $surcharge->fZuschlag        = $this->getSurcharge();
        $surcharge->kVersandzuschlag = $this->getID();

        if (($newInsertID = $db->upsert('tversandzuschlag', $surcharge)) > 0) {
            $this->setID($newInsertID);
        }
        if ($this->getID() > 0) {
            foreach ($this->getNames() as $key => $name) {
                $surchargeLang                   = new stdClass();
                $surchargeLang->cName            = $name;
                $surchargeLang->cISOSprache      = Shop::Lang()->getIsoFromLangID($key)->cISO ?? '';
                $surchargeLang->kVersandzuschlag = $this->getID();
                $db->upsert('tversandzuschlagsprache', $surchargeLang);
            }
        }
    }

    public function hasZIPCode(?string $zip): bool
    {
        if ($zip === null) {
            return false;
        }
        $zip = \str_replace(' ', '', $zip);
        if (\in_array($zip, $this->getZIPCodes() ?? [], true)) {
            return true;
        }

        foreach ($this->getZIPAreas() ?? [] as $zipArea) {
            if ($zipArea->isInArea($zip)) {
                return true;
            }
        }

        return false;
    }

    public function areaOverlapsWithZIPCode(?string $zipFrom, ?string $zipTo): bool
    {
        if ($zipFrom === null || $zipTo === null) {
            return false;
        }
        $area = new ShippingSurchargeArea($zipFrom, $zipTo);
        foreach ($this->getZIPCodes() ?? [] as $zipTMP) {
            if ($area->isInArea($zipTMP)) {
                return true;
            }
        }

        return false;
    }

    public function getID(): ?int
    {
        return $this->ID;
    }

    public function setID(int $id): self
    {
        $this->ID = $id;

        return $this;
    }

    public function getISO(): string
    {
        return $this->ISO ?? '';
    }

    public function setISO(string $ISO): self
    {
        $this->ISO = $ISO;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title ?? '';
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getSurcharge(): float
    {
        return $this->surcharge;
    }

    public function setSurcharge(float $surcharge): self
    {
        $this->surcharge = $surcharge;

        return $this;
    }

    public function getShippingMethod(): int
    {
        return $this->shippingMethod ?? 0;
    }

    public function setShippingMethod(int $shippingMethod): self
    {
        $this->shippingMethod = $shippingMethod;

        return $this;
    }

    /**
     * @return string[]|null
     */
    public function getZIPCodes(): ?array
    {
        return $this->ZIPCodes;
    }

    /**
     * @param string[] $ZIPCodes
     */
    public function setZIPCodes(array $ZIPCodes): self
    {
        $this->ZIPCodes = $ZIPCodes;

        return $this;
    }

    public function setZIPCode(string $ZIPCode): self
    {
        $this->ZIPCodes[] = \str_replace(' ', '', $ZIPCode);

        return $this;
    }

    /**
     * @return ShippingSurchargeArea[]|null
     */
    public function getZIPAreas(): ?array
    {
        return $this->ZIPAreas;
    }

    /**
     * @param ShippingSurchargeArea[] $ZIPAreas
     */
    public function setZIPAreas(array $ZIPAreas): self
    {
        $this->ZIPAreas = $ZIPAreas;

        return $this;
    }

    public function setZIPArea(string $ZIPFrom, string $ZIPTo): self
    {
        $this->ZIPAreas[] = new ShippingSurchargeArea($ZIPFrom, $ZIPTo);

        return $this;
    }

    public function getName(?int $idx = null): string
    {
        return $this->names[$idx ?? Shop::getLanguageID()] ?? '';
    }

    /**
     * @return string[]
     */
    public function getNames(): array
    {
        return $this->names;
    }

    public function setName(string $name, ?int $idx = null): void
    {
        $this->names[$idx ?? Shop::getLanguageID()] = $name;
    }

    /**
     * @param string[] $names
     */
    public function setNames(array $names): void
    {
        $this->names = $names;
    }

    public function getPriceLocalized(): string
    {
        return $this->priceLocalized ?? '';
    }

    public function setPriceLocalized(?string $priceLocalized = null): self
    {
        $this->priceLocalized = Preise::getLocalizedPriceString($priceLocalized ?? $this->getSurcharge());

        return $this;
    }
}
