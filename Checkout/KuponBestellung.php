<?php

declare(strict_types=1);

namespace JTL\Checkout;

use JTL\Shop;
use stdClass;

/**
 * Class KuponBestellung
 * @package JTL\Checkout
 */
class KuponBestellung
{
    public ?int $kKupon = null;

    public ?int $kBestellung = null;

    public ?int $kKunde = null;

    public ?string $cBestellNr = null;

    public string|null|float $fGesamtsummeBrutto = null;

    public string|null|float $fKuponwertBrutto = null;

    public ?string $cKuponTyp = null;

    public ?string $dErstellt = null;

    public function __construct(int $couponID = 0, int $orderID = 0)
    {
        if ($couponID > 0 && $orderID > 0) {
            $this->loadFromDB($couponID, $orderID);
        }
    }

    private function loadFromDB(int $couponID = 0, int $orderID = 0): self
    {
        $item = Shop::Container()->getDB()->select(
            'tkuponbestellung',
            'kKupon',
            $couponID,
            'kBestellung',
            $orderID
        );
        if ($item === null || $item->kKupon <= 0) {
            return $this;
        }
        $this->kKupon             = (int)$item->kKupon;
        $this->kBestellung        = (int)$item->kBestellung;
        $this->kKunde             = (int)$item->kKunde;
        $this->cBestellNr         = $item->cBestellNr;
        $this->fGesamtsummeBrutto = $item->fGesamtsummeBrutto;
        $this->fKuponwertBrutto   = $item->fKuponwertBrutto;
        $this->cKuponTyp          = $item->cKuponTyp;
        $this->dErstellt          = $item->dErstellt;

        return $this;
    }

    /**
     * @param bool $primary
     * @return ($primary is true ? int|false : bool)
     */
    public function save(bool $primary = true): bool|int
    {
        $ins = new stdClass();
        foreach (\array_keys(\get_object_vars($this)) as $member) {
            $ins->$member = $this->$member;
        }
        $key = Shop::Container()->getDB()->insert('tkuponbestellung', $ins);
        if ($key < 1) {
            return false;
        }

        return $primary ? $key : true;
    }

    public function update(): int
    {
        $_upd                      = new stdClass();
        $_upd->kKupon              = $this->kKupon;
        $_upd->kBestellung         = $this->kBestellung;
        $_upd->kKunde              = $this->kKunde;
        $_upd->cBestellNr          = $this->cBestellNr;
        $_upd->fGesammtsummeBrutto = $this->fGesamtsummeBrutto;
        $_upd->fKuponwertBrutto    = $this->fKuponwertBrutto;
        $_upd->cKuponTyp           = $this->cKuponTyp;
        $_upd->dErstellt           = $this->dErstellt;

        return Shop::Container()->getDB()->update(
            'tkuponbestellung',
            ['kKupon', 'kBestellung'],
            [$this->kKupon, $this->kBestellung],
            $_upd
        );
    }

    public function delete(): int
    {
        return Shop::Container()->getDB()->delete(
            'tkupon',
            ['kKupon', 'kBestellung'],
            [$this->kKupon, $this->kBestellung]
        );
    }

    public function setKupon(int $kKupon): self
    {
        $this->kKupon = $kKupon;

        return $this;
    }

    public function setBestellung(int $orderID): self
    {
        $this->kBestellung = $orderID;

        return $this;
    }

    public function setKunden(int $customerID): self
    {
        $this->kKunde = $customerID;

        return $this;
    }

    public function setBestellNr(string $cBestellNr): self
    {
        $this->cBestellNr = $cBestellNr;

        return $this;
    }

    public function setGesamtsummeBrutto(float|string $fGesamtsummeBrutto): self
    {
        $this->fGesamtsummeBrutto = (float)$fGesamtsummeBrutto;

        return $this;
    }

    public function setKuponwertBrutto(float|string $fKuponwertBrutto): self
    {
        $this->fKuponwertBrutto = (float)$fKuponwertBrutto;

        return $this;
    }

    public function setKuponTyp(string $cKuponTyp): self
    {
        $this->cKuponTyp = $cKuponTyp;

        return $this;
    }

    public function setErstellt(string $dErstellt): self
    {
        $this->dErstellt = $dErstellt;

        return $this;
    }

    public function getKupon(): ?int
    {
        return $this->kKupon;
    }

    public function getBestellung(): ?int
    {
        return $this->kBestellung;
    }

    public function getKunde(): ?int
    {
        return $this->kKunde;
    }

    public function getBestellNr(): ?string
    {
        return $this->cBestellNr;
    }

    public function getGesamtsummeBrutto(): float|string|null
    {
        return $this->fGesamtsummeBrutto;
    }

    public function getKuponwertBrutto(): float|string|null
    {
        return $this->fKuponwertBrutto;
    }

    public function getKuponTyp(): ?string
    {
        return $this->cKuponTyp;
    }

    public function getErstellt(): ?string
    {
        return $this->dErstellt;
    }

    /**
     * Gets used coupons from orders
     * @return array<mixed>[]
     */
    public static function getOrdersWithUsedCoupons(string $start, string $end, int $couponID = 0): array
    {
        return Shop::Container()->getDB()->getArrays(
            'SELECT kbs.*, wkp.cName, kp.kKupon
                FROM tkuponbestellung AS kbs
                LEFT JOIN tbestellung AS bs 
                   ON kbs.kBestellung = bs.kBestellung
                LEFT JOIN twarenkorbpos AS wkp 
                    ON bs.kWarenkorb = wkp.kWarenkorb
                LEFT JOIN tkupon AS kp 
                    ON kbs.kKupon = kp.kKupon
                WHERE kbs.dErstellt BETWEEN :strt AND :nd
                    AND bs.cStatus != :stt
                    AND (wkp.nPosTyp = 3 OR wkp.nPosTyp = 7) ' .
            ($couponID > 0 ? ' AND kp.kKupon = ' . $couponID : '') . '
                ORDER BY kbs.dErstellt DESC',
            ['strt' => $start, 'nd' => $end, 'stt' => \BESTELLUNG_STATUS_STORNO]
        );
    }
}
