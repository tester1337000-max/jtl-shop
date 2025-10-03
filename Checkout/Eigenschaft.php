<?php

declare(strict_types=1);

namespace JTL\Checkout;

use JTL\DB\DbInterface;
use JTL\Helpers\GeneralObject;
use JTL\Shop;

/**
 * Class Eigenschaft
 * @package JTL\Checkout
 */
class Eigenschaft
{
    public ?int $kEigenschaft = null;

    public ?int $kArtikel = null;

    public ?string $cName = null;

    /**
     * @var 'Y'|'N'
     */
    public string $cWaehlbar = 'N';

    public ?string $cTyp;

    public int $nSort = 0;

    private DbInterface $db;

    public function __construct(int $id = 0, ?DbInterface $db = null)
    {
        $this->db = $db ?? Shop::Container()->getDB();
        if ($id > 0) {
            $this->loadFromDB($id);
        }
    }

    public function loadFromDB(int $id): self
    {
        $obj = $this->db->select('teigenschaft', 'kEigenschaft', $id);
        if ($obj !== null) {
            $this->kEigenschaft = (int)$obj->kEigenschaft;
            $this->kArtikel     = (int)$obj->kArtikel;
            $this->cName        = $obj->cName;
            $this->cWaehlbar    = $obj->cWaehlbar;
            $this->cTyp         = $obj->cTyp;
            $this->nSort        = (int)$obj->nSort;
        }
        \executeHook(\HOOK_EIGENSCHAFT_CLASS_LOADFROMDB);

        return $this;
    }

    public function insertInDB(): int
    {
        return $this->db->insert('teigenschaft', GeneralObject::copyMembers($this));
    }

    public function updateInDB(): int
    {
        $obj = GeneralObject::copyMembers($this);

        return $this->db->update('teigenschaft', 'kEigenschaft', $obj->kEigenschaft, $obj);
    }
}
