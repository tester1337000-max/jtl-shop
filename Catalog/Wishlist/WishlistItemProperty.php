<?php

declare(strict_types=1);

namespace JTL\Catalog\Wishlist;

use JTL\Helpers\GeneralObject;
use JTL\Shop;

/**
 * Class WishlistItemProperty
 * @package JTL\Catalog\Wishlist
 */
class WishlistItemProperty
{
    /**
     * @var array<string, string>
     */
    protected static array $mapping = [
        'kWunschlistePosEigenschaft' => 'ID',
        'kWunschlistePos'            => 'ItemID',
        'kEigenschaft'               => 'PropertyID',
        'kEigenschaftWert'           => 'PropertyValueID',
        'cFreifeldWert'              => 'FreeTextValue',
        'cEigenschaftName'           => 'PropertyName',
        'cEigenschaftWertName'       => 'PropertyValueName'
    ];

    public int $kWunschlistePosEigenschaft = 0;

    public int $kWunschlistePos;

    public int $kEigenschaft;

    public ?int $kEigenschaftWert;

    public ?string $cFreifeldWert;

    public ?string $cEigenschaftName;

    public ?string $cEigenschaftWertName;

    public function __construct(
        int $propertyID,
        ?int $propertyValueID,
        ?string $freeText,
        ?string $propertyName,
        ?string $propertyValueName,
        int $wishlistItemID
    ) {
        $this->kEigenschaft         = $propertyID;
        $this->kEigenschaftWert     = $propertyValueID;
        $this->kWunschlistePos      = $wishlistItemID;
        $this->cFreifeldWert        = $freeText;
        $this->cEigenschaftName     = $propertyName;
        $this->cEigenschaftWertName = $propertyValueName;
    }

    public function schreibeDB(): self
    {
        $this->kWunschlistePosEigenschaft = Shop::Container()->getDB()->insert(
            'twunschlisteposeigenschaft',
            GeneralObject::copyMembers($this)
        );

        return $this;
    }

    public function getID(): int
    {
        return $this->kWunschlistePosEigenschaft;
    }

    public function setID(int $id): void
    {
        $this->kWunschlistePosEigenschaft = $id;
    }

    public function getItemID(): int
    {
        return $this->kWunschlistePos;
    }

    public function setItemID(int $itemID): void
    {
        $this->kWunschlistePos = $itemID;
    }

    public function getPropertyID(): int
    {
        return $this->kEigenschaft;
    }

    public function setPropertyID(int $propertyID): void
    {
        $this->kEigenschaft = $propertyID;
    }

    public function getPropertyValueID(): ?int
    {
        return $this->kEigenschaftWert;
    }

    public function setPropertyValueID(?int $propertyValueID): void
    {
        $this->kEigenschaftWert = $propertyValueID;
    }

    public function getFreeTextValue(): ?string
    {
        return $this->cFreifeldWert;
    }

    public function setFreeTextValue(string $value): void
    {
        $this->cFreifeldWert = $value;
    }

    public function getPropertyName(): ?string
    {
        return $this->cEigenschaftName;
    }

    public function setPropertyName(string $name): void
    {
        $this->cEigenschaftName = $name;
    }

    public function getPropertyValueName(): ?string
    {
        return $this->cEigenschaftWertName;
    }

    public function setPropertyValueName(string $name): void
    {
        $this->cEigenschaftWertName = $name;
    }
}
