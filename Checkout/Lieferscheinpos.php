<?php

declare(strict_types=1);

namespace JTL\Checkout;

use JTL\DB\DbInterface;
use JTL\Shop;
use stdClass;

/**
 * Class Lieferscheinpos
 * @package JTL\Checkout
 */
class Lieferscheinpos
{
    protected ?int $kLieferscheinPos = null;

    protected ?int $kLieferschein = null;

    protected ?int $kBestellPos = null;

    protected ?int $kWarenlager = null;

    protected ?float $fAnzahl = null;

    /**
     * @var Lieferscheinposinfo[]
     */
    public array $oLieferscheinPosInfo_arr = [];

    /**
     * @var object|null
     */
    public ?object $oPosition = null;

    private DbInterface $db;

    public function __construct(int $id = 0, ?DbInterface $db = null)
    {
        $this->db = $db ?? Shop::Container()->getDB();
        if ($id > 0) {
            $this->loadFromDB($id);
        }
    }

    private function loadFromDB(int $id = 0): self
    {
        $item = $this->db->select('tlieferscheinpos', 'kLieferscheinPos', $id);
        if ($item !== null && $item->kLieferscheinPos > 0) {
            $this->kLieferscheinPos = (int)$item->kLieferscheinPos;
            $this->kLieferschein    = (int)$item->kLieferschein;
            $this->kBestellPos      = (int)$item->kBestellPos;
            $this->kWarenlager      = (int)$item->kWarenlager;
            $this->fAnzahl          = (float)$item->fAnzahl;
        }

        return $this;
    }

    public function save(bool $primary = true): bool|int
    {
        $ins                = new stdClass();
        $ins->kLieferschein = $this->getLieferschein();
        $ins->kBestellPos   = $this->getBestellPos();
        $ins->kWarenlager   = $this->getWarenlager();
        $ins->fAnzahl       = $this->getAnzahl();

        $id = $this->db->insert('tlieferscheinpos', $ins);
        if ($id < 1) {
            return false;
        }

        return $primary ? $id : true;
    }

    public function update(): int
    {
        $upd                = new stdClass();
        $upd->kLieferschein = $this->getLieferschein();
        $upd->kBestellPos   = $this->getBestellPos();
        $upd->kWarenlager   = $this->getWarenlager();
        $upd->fAnzahl       = $this->getAnzahl();

        return $this->db->update(
            'tlieferscheinpos',
            'kLieferscheinPos',
            $this->getLieferscheinPos(),
            $upd
        );
    }

    public function delete(): int
    {
        return $this->db->delete('tlieferscheinpos', 'kLieferscheinPos', $this->getLieferscheinPos());
    }

    public function setLieferscheinPos(int $kLieferscheinPos): self
    {
        $this->kLieferscheinPos = $kLieferscheinPos;

        return $this;
    }

    public function setLieferschein(int $kLieferschein): self
    {
        $this->kLieferschein = $kLieferschein;

        return $this;
    }

    public function setBestellPos(int $kBestellPos): self
    {
        $this->kBestellPos = $kBestellPos;

        return $this;
    }

    public function setWarenlager(int $kWarenlager): self
    {
        $this->kWarenlager = $kWarenlager;

        return $this;
    }

    public function setAnzahl(float|int|string $fAnzahl): self
    {
        $this->fAnzahl = (float)$fAnzahl;

        return $this;
    }

    public function getLieferscheinPos(): int
    {
        return (int)$this->kLieferscheinPos;
    }

    public function getLieferschein(): int
    {
        return (int)$this->kLieferschein;
    }

    public function getBestellPos(): int
    {
        return (int)$this->kBestellPos;
    }

    public function getWarenlager(): int
    {
        return (int)$this->kWarenlager;
    }

    public function getAnzahl(): ?float
    {
        return $this->fAnzahl;
    }
}
