<?php

declare(strict_types=1);

namespace JTL\Checkout;

use JTL\Shop;
use stdClass;

/**
 * Class Nummern
 * @package JTL\Checkout
 */
class Nummern
{
    protected ?int $nNummer = null;

    protected ?int $nArt = null;

    protected ?string $dAktualisiert = null;

    public function __construct(int $id = 0)
    {
        if ($id > 0) {
            $this->loadFromDB($id);
        }
    }

    private function loadFromDB(int $id = 0): self
    {
        $item = Shop::Container()->getDB()->select('tnummern', 'nArt', $id);
        if ($item !== null && $item->nArt > 0) {
            $this->nNummer       = (int)$item->nNummer;
            $this->nArt          = (int)$item->nArt;
            $this->dAktualisiert = $item->dAktualisiert;
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
        $key = Shop::Container()->getDB()->insert('tnummern', $ins);
        if ($key < 1) {
            return false;
        }

        return $primary ? $key : true;
    }

    public function update(bool $bDate = true): int
    {
        if ($bDate) {
            $this->setAktualisiert('NOW()');
        }
        $upd                = new stdClass();
        $upd->nNummer       = $this->nNummer;
        $upd->dAktualisiert = $this->dAktualisiert;

        return Shop::Container()->getDB()->update('tnummern', 'nArt', (int)$this->nArt, $upd);
    }

    public function delete(): int
    {
        return Shop::Container()->getDB()->delete('tnummern', 'nArt', (int)$this->nArt);
    }

    public function setNummer(int $nNummer): self
    {
        $this->nNummer = $nNummer;

        return $this;
    }

    public function setArt(int $nArt): self
    {
        $this->nArt = $nArt;

        return $this;
    }

    public function setAktualisiert(string $dAktualisiert): self
    {
        $this->dAktualisiert = \mb_convert_case($dAktualisiert, \MB_CASE_UPPER) === 'NOW()'
            ? \date('Y-m-d H:i:s')
            : $dAktualisiert;

        return $this;
    }

    public function getNummer(): ?int
    {
        return $this->nNummer;
    }

    public function getArt(): ?int
    {
        return $this->nArt;
    }

    public function getAktualisiert(): ?string
    {
        return $this->dAktualisiert;
    }
}
