<?php

declare(strict_types=1);

namespace JTL\Checkout;

use JTL\Cart\CartItem;
use JTL\Shop;
use stdClass;

/**
 * Class Lieferschein
 * @package JTL\Checkout
 */
class Lieferschein
{
    protected int $kLieferschein = 0;

    protected int $kInetBestellung = 0;

    protected string $cLieferscheinNr = '';

    protected string $cHinweis = '';

    protected int $nFulfillment = 0;

    protected int $nStatus = 0;

    protected string $dErstellt = '';

    protected bool $bEmailVerschickt = false;

    /**
     * @var Lieferscheinpos[]
     */
    public array $oLieferscheinPos_arr = [];

    /**
     * @var Versand[]
     */
    public array $oVersand_arr = [];

    /**
     * @var stdClass[]|CartItem[]
     */
    public array $oPosition_arr = [];

    public function __construct(int $id = 0, ?stdClass $data = null)
    {
        if ($id > 0) {
            $this->loadFromDB($id, $data);
        }
    }

    private function loadFromDB(int $id = 0, ?stdClass $data = null): self
    {
        $db   = Shop::Container()->getDB();
        $item = $db->select('tlieferschein', 'kLieferschein', $id);
        if ($item !== null && $item->kLieferschein > 0) {
            $item->kLieferschein    = (int)$item->kLieferschein;
            $item->kInetBestellung  = (int)$item->kInetBestellung;
            $item->nFulfillment     = (int)$item->nFulfillment;
            $item->nStatus          = (int)$item->nStatus;
            $item->bEmailVerschickt = (bool)$item->bEmailVerschickt;
            foreach (\array_keys(\get_object_vars($item)) as $member) {
                $setter = 'set' . \mb_substr($member, 1);
                if (\is_callable([$this, $setter])) {
                    $this->$setter($item->$member);
                } else {
                    $this->$member = $item->$member;
                }
            }

            $items = $db->selectAll(
                'tlieferscheinpos',
                'kLieferschein',
                $id,
                'kLieferscheinPos'
            );
            foreach ($items as $deliveryItem) {
                $lineItem = new Lieferscheinpos((int)$deliveryItem->kLieferscheinPos, $db);
                $infos    = $db->selectAll(
                    'tlieferscheinposinfo',
                    'kLieferscheinPos',
                    (int)$deliveryItem->kLieferscheinPos,
                    'kLieferscheinPosInfo'
                );
                foreach ($infos as $info) {
                    $lineItem->oLieferscheinPosInfo_arr[] = new Lieferscheinposinfo((int)$info->kLieferscheinPosInfo);
                }
                $this->oLieferscheinPos_arr[] = $lineItem;
            }

            $shippings = $db->selectAll(
                'tversand',
                'kLieferschein',
                $id,
                'kVersand'
            );
            foreach ($shippings as $shipping) {
                $this->oVersand_arr[] = new Versand((int)$shipping->kVersand, $data);
            }
        }

        return $this;
    }

    /**
     * @param bool $primary
     * @return ($primary is true ? int|false : bool)
     */
    public function save(bool $primary = true): bool|int
    {
        $ins                   = new stdClass();
        $ins->kInetBestellung  = $this->kInetBestellung;
        $ins->cLieferscheinNr  = $this->cLieferscheinNr;
        $ins->cHinweis         = $this->cHinweis;
        $ins->nFulfillment     = $this->nFulfillment;
        $ins->nStatus          = $this->nStatus;
        $ins->dErstellt        = $this->dErstellt;
        $ins->bEmailVerschickt = (int)$this->bEmailVerschickt;

        $key = Shop::Container()->getDB()->insert('tlieferschein', $ins);
        if ($key < 1) {
            return false;
        }

        return $primary ? $key : true;
    }

    public function update(): int
    {
        $upd                   = new stdClass();
        $upd->kInetBestellung  = $this->kInetBestellung;
        $upd->cLieferscheinNr  = $this->cLieferscheinNr;
        $upd->cHinweis         = $this->cHinweis;
        $upd->nFulfillment     = $this->nFulfillment;
        $upd->nStatus          = $this->nStatus;
        $upd->dErstellt        = $this->dErstellt;
        $upd->bEmailVerschickt = $this->bEmailVerschickt ? 1 : 0;

        return Shop::Container()->getDB()->update('tlieferschein', 'kLieferschein', $this->kLieferschein, $upd);
    }

    public function delete(): int
    {
        return Shop::Container()->getDB()->delete('tlieferschein', 'kLieferschein', $this->getLieferschein());
    }

    public function setLieferschein(int $kLieferschein): self
    {
        $this->kLieferschein = $kLieferschein;

        return $this;
    }

    public function setInetBestellung(int $kInetBestellung): self
    {
        $this->kInetBestellung = $kInetBestellung;

        return $this;
    }

    public function setLieferscheinNr(string $cLieferscheinNr): self
    {
        $this->cLieferscheinNr = $cLieferscheinNr;

        return $this;
    }

    public function setHinweis(string $cHinweis): self
    {
        $this->cHinweis = $cHinweis;

        return $this;
    }

    public function setFulfillment(int $nFulfillment): self
    {
        $this->nFulfillment = $nFulfillment;

        return $this;
    }

    public function setStatus(int $nStatus): self
    {
        $this->nStatus = $nStatus;

        return $this;
    }

    public function setErstellt(string $dErstellt): self
    {
        $this->dErstellt = $dErstellt;

        return $this;
    }

    public function setEmailVerschickt(bool $bEmailVerschickt): self
    {
        $this->bEmailVerschickt = $bEmailVerschickt;

        return $this;
    }

    public function getLieferschein(): int
    {
        return $this->kLieferschein;
    }

    public function getInetBestellung(): ?int
    {
        return $this->kInetBestellung;
    }

    public function getLieferscheinNr(): ?string
    {
        return $this->cLieferscheinNr;
    }

    public function getHinweis(): ?string
    {
        return $this->cHinweis;
    }

    public function getFulfillment(): ?int
    {
        return $this->nFulfillment;
    }

    public function getStatus(): ?int
    {
        return $this->nStatus;
    }

    public function getErstellt(): ?string
    {
        return $this->dErstellt;
    }

    public function getEmailVerschickt(): ?bool
    {
        return $this->bEmailVerschickt;
    }
}
