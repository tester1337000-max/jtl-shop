<?php

declare(strict_types=1);

namespace JTL\Extensions\Config;

use JTL\Nice;
use JTL\Session\Frontend;
use JTL\Shop;

/**
 * Class ItemPrice
 * @package JTL\Extensions\Config
 */
class ItemPrice
{
    public const PRICE_TYPE_PERCENTAGE = 1;

    public const PRICE_TYPE_SUM = 0;

    protected ?int $kKonfigitem = null;

    protected int $kKundengruppe = 0;

    protected int $kSteuerklasse = 0;

    protected ?float $fPreis = null;

    protected ?int $nTyp = null;

    public function __construct(int $configItemID = 0, int $customerGroupID = 0)
    {
        if ($configItemID > 0 && $customerGroupID > 0) {
            $this->loadFromDB($configItemID, $customerGroupID);
        }
    }

    public static function checkLicense(): bool
    {
        return Nice::getInstance()->checkErweiterung(\SHOP_ERWEITERUNG_KONFIGURATOR);
    }

    private function loadFromDB(int $configItemID = 0, int $customerGroupID = 0): void
    {
        $item = Shop::Container()->getDB()->select(
            'tkonfigitempreis',
            'kKonfigitem',
            $configItemID,
            'kKundengruppe',
            $customerGroupID
        );

        if ($item !== null && $item->kKonfigitem > 0 && $item->kKundengruppe > 0) {
            $this->kKonfigitem   = (int)$item->kKonfigitem;
            $this->kKundengruppe = (int)$item->kKundengruppe;
            $this->kSteuerklasse = (int)$item->kSteuerklasse;
            $this->nTyp          = (int)$item->nTyp;
            $this->fPreis        = (float)$item->fPreis;
        }
    }

    public function setKonfigitem(int $kKonfigitem): self
    {
        $this->kKonfigitem = $kKonfigitem;

        return $this;
    }

    public function setKundengruppe(int $customerGroupID): self
    {
        $this->kKundengruppe = $customerGroupID;

        return $this;
    }

    public function setSteuerklasse(int $kSteuerklasse): self
    {
        $this->kSteuerklasse = $kSteuerklasse;

        return $this;
    }

    public function setPreis(float|string|int $fPreis): self
    {
        $this->fPreis = (float)$fPreis;

        return $this;
    }

    public function getKonfigitem(): int
    {
        return $this->kKonfigitem ?? 0;
    }

    public function getKundengruppe(): int
    {
        return $this->kKundengruppe;
    }

    public function getSteuerklasse(): int
    {
        return $this->kSteuerklasse;
    }

    public function getPreis(bool $convertCurrency = false): ?float
    {
        $price = $this->fPreis;
        if ($convertCurrency && $price > 0) {
            $price *= Frontend::getCurrency()->getConversionFactor();
        }

        return $price;
    }

    public function getTyp(): ?int
    {
        return $this->nTyp;
    }
}
