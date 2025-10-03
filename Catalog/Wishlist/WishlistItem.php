<?php

declare(strict_types=1);

namespace JTL\Catalog\Wishlist;

use JTL\Catalog\Product\Artikel;
use JTL\Shop;
use stdClass;

use function Functional\select;
use function Functional\some;

/**
 * Class WishlistItem
 * @package JTL\Catalog\Wishlist
 */
class WishlistItem
{
    /**
     * @var array<string, string>
     */
    protected static array $mapping = [
        'kWunschliste'                   => 'ishlistID',
        'kWunschlistePos'                => 'ID',
        'kArtikel'                       => 'ProductID',
        'fAnzahl'                        => 'Qty',
        'cArtikelName'                   => 'ProductName',
        'cKommentar'                     => 'Comment',
        'dHinzugefuegt'                  => 'DateAdded',
        'dHinzugefuegt_de'               => 'DateAddedLocalized',
        'CWunschlistePosEigenschaft_arr' => 'Properties',
        'Artikel'                        => 'Product',
        'cPreis'                         => 'Price',
        'cURL'                           => 'URL'
    ];

    public int $kWunschlistePos = 0;

    public int $kWunschliste = 0;

    public ?int $kArtikel = null;

    /**
     * @var numeric-string|int|float
     */
    public string|int|float $fAnzahl;

    public string $cArtikelName = '';

    public string $cKommentar = '';

    public string $dHinzugefuegt = '';

    public string $dHinzugefuegt_de = '';

    /**
     * @var WishlistItemProperty[]
     */
    public array $CWunschlistePosEigenschaft_arr = [];

    public ?Artikel $Artikel = null;

    public string $cPreis = '';

    public string $cURL = '';

    public function __wakeup(): void
    {
        if ($this->kArtikel === null) {
            return;
        }
        $this->Artikel = new Artikel();
        $this->Artikel->fuelleArtikel($this->kArtikel, Artikel::getDefaultOptions());
    }

    /**
     * @return string[]
     */
    public function __sleep(): array
    {
        return select(\array_keys(\get_object_vars($this)), fn(string $e): bool => $e !== 'Artikel');
    }

    /**
     * @param float|int|numeric-string $qty
     */
    public function __construct(int $productID, string $productName, float|int|string $qty, int $wihlistID)
    {
        $this->kArtikel     = $productID;
        $this->cArtikelName = $productName;
        $this->fAnzahl      = $qty;
        $this->kWunschliste = $wihlistID;
    }

    /**
     * @param array<mixed> $values
     */
    public function erstellePosEigenschaften(array $values): self
    {
        foreach ($values as $value) {
            $wlItemProp = new WishlistItemProperty(
                $value->kEigenschaft,
                !empty($value->kEigenschaftWert) ? $value->kEigenschaftWert : null,
                !empty($value->cFreifeldWert) ? $value->cFreifeldWert : null,
                !empty($value->cEigenschaftName) ? $value->cEigenschaftName : null,
                !empty($value->cEigenschaftWertName) ? $value->cEigenschaftWertName : null,
                $this->kWunschlistePos
            );
            $wlItemProp->schreibeDB();
            $this->CWunschlistePosEigenschaft_arr[] = $wlItemProp;
        }

        return $this;
    }

    public function schreibeDB(): self
    {
        $ins                = new stdClass();
        $ins->kWunschliste  = $this->kWunschliste;
        $ins->kArtikel      = $this->kArtikel;
        $ins->fAnzahl       = $this->fAnzahl;
        $ins->cArtikelName  = $this->cArtikelName;
        $ins->cKommentar    = $this->cKommentar;
        $ins->dHinzugefuegt = $this->dHinzugefuegt;

        $this->kWunschlistePos = Shop::Container()->getDB()->insert('twunschlistepos', $ins);

        return $this;
    }

    public function updateDB(): self
    {
        $upd                  = new stdClass();
        $upd->kWunschlistePos = $this->kWunschlistePos;
        $upd->kWunschliste    = $this->kWunschliste;
        $upd->kArtikel        = $this->kArtikel;
        $upd->fAnzahl         = $this->fAnzahl;
        $upd->cArtikelName    = $this->cArtikelName;
        $upd->cKommentar      = $this->cKommentar;
        $upd->dHinzugefuegt   = $this->dHinzugefuegt;

        Shop::Container()->getDB()->update('twunschlistepos', 'kWunschlistePos', $this->kWunschlistePos, $upd);

        return $this;
    }

    public function istEigenschaftEnthalten(int $propertyID, ?int $propertyValueID): bool
    {
        return some(
            $this->CWunschlistePosEigenschaft_arr,
            fn($e): bool => (int)$e->kEigenschaft === $propertyID && (int)$e->kEigenschaftWert === $propertyValueID
        );
    }

    public function getID(): int
    {
        return $this->kWunschlistePos;
    }

    public function setID(int $id): void
    {
        $this->kWunschlistePos = $id;
    }

    public function getWishlistID(): int
    {
        return $this->kWunschliste;
    }

    public function setWishlistID(int $wishlistID): void
    {
        $this->kWunschliste = $wishlistID;
    }

    public function getProductID(): int
    {
        return (int)$this->kArtikel;
    }

    public function setProductID(int $productID): void
    {
        $this->kArtikel = $productID;
    }

    /**
     * @return float|int|numeric-string
     */
    public function getQty(): float|int|string
    {
        return $this->fAnzahl;
    }

    /**
     * @param float|int|numeric-string $qty
     */
    public function setQty(float|int|string $qty): void
    {
        $this->fAnzahl = $qty;
    }

    public function getProductName(): string
    {
        return $this->cArtikelName;
    }

    public function setProductName(string $productName): void
    {
        $this->cArtikelName = $productName;
    }

    public function getComment(): string
    {
        return $this->cKommentar;
    }

    public function setComment(string $comment): void
    {
        $this->cKommentar = $comment;
    }

    public function getDateAdded(): string
    {
        return $this->dHinzugefuegt;
    }

    public function setDateAdded(string $date): void
    {
        $this->dHinzugefuegt = $date;
    }

    public function getDateAddedLocalized(): string
    {
        return $this->dHinzugefuegt_de;
    }

    public function setDateAddedLocalized(string $date): void
    {
        $this->dHinzugefuegt_de = $date;
    }

    /**
     * @return WishlistItemProperty[]
     */
    public function getProperties(): array
    {
        return $this->CWunschlistePosEigenschaft_arr;
    }

    /**
     * @param WishlistItemProperty[] $properties
     */
    public function setProperties(array $properties): void
    {
        $this->CWunschlistePosEigenschaft_arr = $properties;
    }

    public function addProperty(WishlistItemProperty $property): void
    {
        $this->CWunschlistePosEigenschaft_arr[] = $property;
    }

    public function getProduct(): ?Artikel
    {
        return $this->Artikel;
    }

    public function setProduct(Artikel $product): void
    {
        $this->Artikel = $product;
    }

    public function unsetProduct(): void
    {
        unset($this->Artikel);
    }

    public function getPrice(): string
    {
        return $this->cPreis;
    }

    public function setPrice(string $price): void
    {
        $this->cPreis = $price;
    }

    public function getURL(): string
    {
        return $this->cURL;
    }

    public function setURL(string $url): void
    {
        $this->cURL = $url;
    }
}
