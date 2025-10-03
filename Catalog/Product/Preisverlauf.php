<?php

declare(strict_types=1);

namespace JTL\Catalog\Product;

use DateTime;
use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Helpers\GeneralObject;
use JTL\Helpers\Tax;
use JTL\Session\Frontend;
use JTL\Shop;

/**
 * Class Preisverlauf
 * @package JTL\Catalog\Product
 */
class Preisverlauf
{
    public ?int $kPreisverlauf = null;

    public ?int $kArtikel = null;

    public ?int $kKundengruppe = null;

    public ?string $dDate = null;

    public ?string $fVKNetto = null;

    private DbInterface $db;

    private JTLCacheInterface $cache;

    public function __construct(int $id = 0, ?DbInterface $db = null, ?JTLCacheInterface $cache = null)
    {
        $this->db    = $db ?? Shop::Container()->getDB();
        $this->cache = $cache ?? Shop::Container()->getCache();
        if ($id > 0) {
            $this->loadFromDB($id);
        }
    }

    /**
     * @return \stdClass[]
     */
    public function gibPreisverlauf(int $productID, int $customerGroupID, int $month): array
    {
        $cacheID = 'gpv_' . $productID . '_' . $customerGroupID . '_' . $month;
        if (($data = $this->cache->get($cacheID)) === false) {
            $data     = $this->db->getObjects(
                'SELECT tpreisverlauf.fVKNetto, tartikel.fMwst, UNIX_TIMESTAMP(tpreisverlauf.dDate) AS timestamp
                    FROM tpreisverlauf 
                    JOIN tartikel
                        ON tartikel.kArtikel = tpreisverlauf.kArtikel
                    WHERE tpreisverlauf.kArtikel = :pid
                        AND tpreisverlauf.kKundengruppe = :cgid
                        AND DATE_SUB(NOW(), INTERVAL :mnth MONTH) < tpreisverlauf.dDate
                    ORDER BY tpreisverlauf.dDate DESC',
                ['pid' => $productID, 'cgid' => $customerGroupID, 'mnth' => $month]
            );
            $currency = Frontend::getCurrency();
            $dt       = new DateTime();
            $merchant = Frontend::getCustomerGroup()->isMerchant();
            foreach ($data as $pv) {
                if (!isset($pv->timestamp)) {
                    continue;
                }
                $dt->setTimestamp((int)$pv->timestamp);
                $pv->date     = $dt->format('d.m.Y');
                $pv->fPreis   = $merchant
                    ? \round($pv->fVKNetto * $currency->getConversionFactor(), 2)
                    : Tax::getGross($pv->fVKNetto * $currency->getConversionFactor(), $pv->fMwst);
                $pv->currency = $currency->getCode();
            }
            $this->cache->set(
                $cacheID,
                $data,
                [\CACHING_GROUP_ARTICLE, \CACHING_GROUP_ARTICLE . '_' . $productID]
            );
        }

        return $data;
    }

    public function loadFromDB(int $id): self
    {
        $item = $this->db->select('tpreisverlauf', 'kPreisverlauf', $id);
        if ($item === null) {
            return $this;
        }
        $this->kPreisverlauf = (int)$item->kPreisverlauf;
        $this->kArtikel      = (int)$item->kArtikel;
        $this->kKundengruppe = (int)$item->kKundengruppe;
        $this->fVKNetto      = $item->fVKNetto;
        $this->dDate         = $item->dDate;

        return $this;
    }

    public function insertInDB(): int
    {
        $ins = GeneralObject::copyMembers($this);
        unset($ins->kPreisverlauf);
        $this->kPreisverlauf = $this->db->insert('tpreisverlauf', $ins);

        return $this->kPreisverlauf;
    }

    public function updateInDB(): int
    {
        $upd = GeneralObject::copyMembers($this);

        return $this->db->update('tpreisverlauf', 'kPreisverlauf', $upd->kPreisverlauf, $upd);
    }
}
