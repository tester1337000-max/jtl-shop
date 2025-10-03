<?php

declare(strict_types=1);

namespace JTL\Checkout;

use JTL\Shop;
use stdClass;

/**
 * Class Lieferscheinposinfo
 * @package JTL\Checkout
 */
class Lieferscheinposinfo
{
    protected int $kLieferscheinPosInfo = 0;

    protected int $kLieferscheinPos = 0;

    protected string $cSeriennummer = '';

    protected string $cChargeNr = '';

    protected string $dMHD = '';

    public function __construct(int $id = 0)
    {
        if ($id > 0) {
            $this->loadFromDB($id);
        }
    }

    private function loadFromDB(int $id = 0): self
    {
        $item = Shop::Container()->getDB()->select('tlieferscheinposinfo', 'kLieferscheinPosInfo', $id);
        if ($item !== null && $item->kLieferscheinPosInfo > 0) {
            $this->kLieferscheinPos     = (int)$item->kLieferscheinPos;
            $this->kLieferscheinPosInfo = (int)$item->kLieferscheinPosInfo;
            $this->cSeriennummer        = $item->cSeriennummer;
            $this->cChargeNr            = $item->cChargeNr;
            $this->dMHD                 = $item->dMHD ?? '';
        }

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
        unset($ins->kLieferscheinPosInfo);

        $key = Shop::Container()->getDB()->insert('tlieferscheinposinfo', $ins);
        if ($key < 1) {
            return false;
        }

        return $primary ? $key : true;
    }

    public function update(): int
    {
        $upd                   = new stdClass();
        $upd->kLieferscheinPos = $this->getLieferscheinPos();
        $upd->cSeriennummer    = $this->getSeriennummer();
        $upd->cChargeNr        = $this->getChargeNr();
        $upd->dMHD             = $this->getMHD();

        return Shop::Container()->getDB()->update(
            'tlieferscheinposinfo',
            'kLieferscheinPosInfo',
            $this->getLieferscheinPosInfo(),
            $upd
        );
    }

    public function delete(): int
    {
        return Shop::Container()->getDB()->delete(
            'tlieferscheinposinfo',
            'kLieferscheinPosInfo',
            $this->getLieferscheinPosInfo()
        );
    }

    public function setLieferscheinPosInfo(int $kLieferscheinPosInfo): self
    {
        $this->kLieferscheinPosInfo = $kLieferscheinPosInfo;

        return $this;
    }

    public function setLieferscheinPos(int $kLieferscheinPos): self
    {
        $this->kLieferscheinPos = $kLieferscheinPos;

        return $this;
    }

    public function setSeriennummer(string $cSeriennummer): self
    {
        $this->cSeriennummer = $cSeriennummer;

        return $this;
    }

    public function setChargeNr(string $cChargeNr): self
    {
        $this->cChargeNr = $cChargeNr;

        return $this;
    }

    public function setMHD(string $dMHD): self
    {
        $this->dMHD = $dMHD;

        return $this;
    }

    public function getLieferscheinPosInfo(): int
    {
        return $this->kLieferscheinPosInfo;
    }

    public function getLieferscheinPos(): int
    {
        return $this->kLieferscheinPos;
    }

    public function getSeriennummer(): string
    {
        return $this->cSeriennummer;
    }

    public function getChargeNr(): string
    {
        return $this->cChargeNr;
    }

    public function getMHD(): string
    {
        return $this->dMHD;
    }
}
