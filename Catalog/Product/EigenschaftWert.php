<?php

declare(strict_types=1);

namespace JTL\Catalog\Product;

use JTL\DB\DbInterface;
use JTL\Helpers\GeneralObject;
use JTL\Shop;

/**
 * Class EigenschaftWert
 * @package JTL\Catalog\Product
 */
class EigenschaftWert
{
    public int $kEigenschaftWert = 0;

    public int $kEigenschaft = 0;

    /**
     * @var numeric-string|float|null
     */
    public string|null|float $fAufpreisNetto = null;

    /**
     * @var numeric-string|float|null
     */
    public string|null|float $fGewichtDiff = null;

    /**
     * @var numeric-string|float|null
     */
    public string|null|float $fLagerbestand = null;

    /**
     * @var numeric-string|float|null
     */
    public string|null|float $fPackeinheit = null;

    public string $cName = '';

    /**
     * @var numeric-string|float|null
     */
    public string|null|float $fAufpreis = null;

    public int $nSort = 0;

    public string $cArtNr = '';

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
        if ($id <= 0) {
            return $this;
        }
        $data = $this->db->select('teigenschaftwert', 'kEigenschaftWert', $id);
        if ($data !== null && $data->kEigenschaftWert > 0) {
            $this->kEigenschaft     = (int)$data->kEigenschaft;
            $this->kEigenschaftWert = (int)$data->kEigenschaftWert;
            $this->nSort            = (int)$data->nSort;
            $this->cName            = $data->cName;
            $this->fAufpreisNetto   = $data->fAufpreisNetto;
            $this->fGewichtDiff     = $data->fGewichtDiff;
            $this->cArtNr           = $data->cArtNr;
            $this->fLagerbestand    = $data->fLagerbestand;
            $this->fPackeinheit     = $data->fPackeinheit;
            if (empty($this->fPackeinheit)) {
                $this->fPackeinheit = 1;
            }
        }
        \executeHook(\HOOK_EIGENSCHAFTWERT_CLASS_LOADFROMDB);

        return $this;
    }

    public function insertInDB(): int
    {
        $obj = GeneralObject::copyMembers($this);
        unset($obj->fAufpreis);

        return $this->db->insert('teigenschaftwert', $obj);
    }

    public function updateInDB(): int
    {
        $obj = GeneralObject::copyMembers($this);
        unset($obj->fAufpreis);

        return $this->db->update('teigenschaftwert', 'kEigenschaftWert', $obj->kEigenschaftWert, $obj);
    }
}
