<?php

declare(strict_types=1);

namespace JTL\FreeGift\Repositories;

use JTL\Abstracts\AbstractDBRepository;
use JTL\DataObjects\DomainObjectInterface;
use stdClass;

/**
 * Class FreeGiftsRepository
 * @package JTL\FreeGift\Repositories
 * @since 5.4.0
 * @description This is a layer between the FreeGift Service and the database.
 */
class FreeGiftRepository extends AbstractDBRepository
{
    /**
     * @var array<string, string>
     */
    private static array $mapping = [
        'kGratisGeschenk' => 'id',
        'kArtikel'        => 'productID',
        'kWarenkorb'      => 'basketID',
        'nAnzahl'         => 'quantity'
    ];

    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tgratisgeschenk';
    }

    /**
     * @inheritdoc
     */
    public function getKeyName(): string
    {
        return $this->mapColumn('id');
    }

    public function mapColumn(string $identifier): ?string
    {
        return self::$mapping[$identifier] ?? \array_flip(self::$mapping)[$identifier] ?? null;
    }

    /**
     * @return (object{productID: int, productValue: float}&stdClass)|null
     */
    public function getByProductID(int $id, int $customerGroupID = 0): ?stdClass
    {
        return $this->db->getCollection(
            'SELECT tartikel.kArtikel AS productID, tartikelattribut.cWert AS productValue
                FROM tartikel
                JOIN tartikelattribut
                    ON tartikelattribut.kArtikel = tartikel.kArtikel
                LEFT JOIN tartikelsichtbarkeit 
                    ON tartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                    AND tartikelsichtbarkeit.kKundengruppe = :cgid
                WHERE tartikel.kArtikel = :id
                AND tartikelsichtbarkeit.kArtikel IS NULL
                AND tartikel.nIstVater = 0
                AND tartikelattribut.cName = :attr
                AND (tartikel.fLagerbestand > 0
                    OR tartikel.cLagerBeachten = :no
                    OR tartikel.cLagerKleinerNull = :yes)',
            [
                'id'   => $id,
                'attr' => \FKT_ATTRIBUT_GRATISGESCHENK,
                'no'   => 'N',
                'yes'  => 'Y',
                'cgid' => $customerGroupID,
            ],
        )->map(static function (stdClass $item): stdClass {
            return (object)[
                'productID'    => (int)$item->productID,
                'productValue' => (float)$item->productValue
            ];
        })->first();
    }

    /**
     * @return array<object{productID: int, productValue: float}&stdClass>
     */
    public function getNextAvailable(float $basketValue, int $customerGroupID = 0): array
    {
        return $this->db->getCollection(
            'SELECT tartikel.kArtikel AS productID, tartikelattribut.cWert AS productValue
                FROM tartikel
                JOIN tartikelattribut
                    ON tartikelattribut.kArtikel = tartikel.kArtikel
                           AND tartikelattribut.cWert >= ' . $basketValue . '
                LEFT JOIN tartikelsichtbarkeit 
                    ON tartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                    AND tartikelsichtbarkeit.kKundengruppe = :cgid
                WHERE tartikelsichtbarkeit.kArtikel IS NULL
                AND tartikel.nIstVater = 0
                AND tartikelattribut.cName = :attr
                AND (tartikel.fLagerbestand > 0
                    OR tartikel.cLagerBeachten = :no
                    OR tartikel.cLagerKleinerNull = :yes)
                ORDER BY tartikelattribut.cWert * 1',
            [
                'attr' => \FKT_ATTRIBUT_GRATISGESCHENK,
                'no'   => 'N',
                'yes'  => 'Y',
                'cgid' => $customerGroupID,
            ],
        )->map(static function (stdClass $item): stdClass {
            return (object)[
                'productID'    => (int)$item->productID,
                'productValue' => (float)$item->productValue
            ];
        })->all();
    }

    /**
     * @return array<object{productID: int, productValue: float}&stdClass>
     */
    public function getFreeGiftProducts(
        string $limit = '',
        string $sortBy = 'ORDER BY CAST(tartikelattribut.cWert AS DECIMAL)',
        string $sortDirection = 'DESC',
        int $customerGroupID = 0,
    ): array {
        return $this->db->getCollection(
            'SELECT tartikel.kArtikel AS productID, tartikelattribut.cWert AS productValue
                FROM tartikel
                JOIN tartikelattribut
                    ON tartikelattribut.kArtikel = tartikel.kArtikel
                LEFT JOIN tartikelsichtbarkeit 
                    ON tartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                    AND tartikelsichtbarkeit.kKundengruppe = :cgid
                LEFT JOIN teigenschaft
                    ON teigenschaft.kArtikel = tartikel.kArtikel
                WHERE teigenschaft.kArtikel IS NULL
                AND tartikelsichtbarkeit.kArtikel IS NULL
                AND tartikel.nIstVater = 0
                AND tartikelattribut.cName = :attr
                AND (tartikel.fLagerbestand > 0
                    OR tartikel.cLagerBeachten = :no
                    OR tartikel.cLagerKleinerNull = :yes)'
            . ' ' . $sortBy . ' ' . $sortDirection . ' ' . $limit,
            [
                'attr' => \FKT_ATTRIBUT_GRATISGESCHENK,
                'no'   => 'N',
                'yes'  => 'Y',
                'cgid' => $customerGroupID,
            ]
        )->map(static function (stdClass $item): stdClass {
            return (object)[
                'productID'    => (int)$item->productID,
                'productValue' => (float)$item->productValue
            ];
        })->all();
    }

    /**
     * @return array<object{productID: int, quantity: int, lastOrdered: string, avgOrderValue: float}&stdClass>
     */
    public function getCommonFreeGifts(string $limitSQL): array
    {
        return $this->db->getCollection(
            'SELECT tgratisgeschenk.kArtikel AS productID, COUNT(*) AS quantity, 
                MAX(tbestellung.dErstellt) AS lastOrdered, AVG(tbestellung.fGesamtsumme) AS avgOrderValue
                FROM tgratisgeschenk
                INNER JOIN tbestellung
                    ON tbestellung.kWarenkorb = tgratisgeschenk.kWarenkorb
                INNER JOIN tartikel
                    ON tartikel.kArtikel = tgratisgeschenk.kArtikel
                        AND tartikel.nIstVater = 0
                LEFT JOIN teigenschaft
                    ON teigenschaft.kArtikel = tartikel.kArtikel
                WHERE teigenschaft.kArtikel IS NULL
                GROUP BY tgratisgeschenk.kArtikel
                ORDER BY quantity DESC, lastOrdered DESC ' . $limitSQL
        )->map(static function (stdClass $item): stdClass {
            return (object)[
                'productID'     => (int)$item->productID,
                'quantity'      => (int)$item->quantity,
                'lastOrdered'   => $item->lastOrdered,
                'avgOrderValue' => (float)$item->avgOrderValue
            ];
        })->all();
    }

    public function getCommonFreeGiftsCount(): int
    {
        return $this->db->getSingleInt(
            'SELECT COUNT(DISTINCT(tgratisgeschenk.kArtikel)) AS cnt
                FROM tgratisgeschenk
                INNER JOIN tbestellung
                    ON tbestellung.kWarenkorb = tgratisgeschenk.kWarenkorb
                INNER JOIN tartikel
                    ON tartikel.kArtikel = tgratisgeschenk.kArtikel
                           AND tartikel.nIstVater = 0
                LEFT JOIN teigenschaft
                    ON teigenschaft.kArtikel = tartikel.kArtikel
                WHERE teigenschaft.kArtikel IS NULL',
            'cnt',
        );
    }

    /**
     * @return int[]
     */
    public function getActiveFreeGiftIDs(string $limitSQL): array
    {
        return $this->db->getInts(
            'SELECT tartikelattribut.kArtikel AS productID
                FROM tartikelattribut
                INNER JOIN tartikel
                    ON tartikel.kArtikel = tartikelattribut.kArtikel
                           AND tartikel.nIstVater = 0
                LEFT JOIN teigenschaft
                    ON teigenschaft.kArtikel = tartikel.kArtikel
                WHERE teigenschaft.kArtikel IS NULL
                AND tartikelattribut.cName = :atr
                ORDER BY CAST(cWert AS SIGNED) DESC ' . $limitSQL,
            'productID',
            ['atr' => \FKT_ATTRIBUT_GRATISGESCHENK]
        );
    }

    public function getActiveFreeGiftsCount(): int
    {
        return $this->db->getSingleInt(
            'SELECT COUNT(*) AS cnt
                FROM tartikelattribut
                INNER JOIN tartikel
                    ON tartikel.kArtikel = tartikelattribut.kArtikel
                           AND tartikel.nIstVater = 0
                LEFT JOIN teigenschaft
                    ON teigenschaft.kArtikel = tartikel.kArtikel
                WHERE teigenschaft.kArtikel IS NULL
                AND tartikelattribut.cName = :nm',
            'cnt',
            ['nm' => \FKT_ATTRIBUT_GRATISGESCHENK]
        );
    }

    /**
     * @return array<object{productID: int, quantity: int, orderCreated: string, totalOrderValue: float}&stdClass>
     */
    public function getRecentFreeGifts(string $limitSQL): array
    {
        return $this->db->getCollection(
            'SELECT tgratisgeschenk.kArtikel AS productID, tgratisgeschenk.nAnzahl AS quantity,
                tbestellung.dErstellt AS orderCreated, tbestellung.fGesamtsumme AS totalOrderValue
                FROM tgratisgeschenk
                INNER JOIN tbestellung
                      ON tbestellung.kWarenkorb = tgratisgeschenk.kWarenkorb
                INNER JOIN tartikel
                    ON tartikel.kArtikel = tgratisgeschenk.kArtikel
                           AND tartikel.nIstVater = 0
                LEFT JOIN teigenschaft
                    ON teigenschaft.kArtikel = tartikel.kArtikel
                WHERE teigenschaft.kArtikel IS NULL
                ORDER BY orderCreated DESC ' . $limitSQL
        )->map(static function (stdClass $item): stdClass {
            return (object)[
                'productID'       => (int)$item->productID,
                'quantity'        => (int)$item->quantity,
                'orderCreated'    => $item->orderCreated,
                'totalOrderValue' => (float)$item->totalOrderValue
            ];
        })->all();
    }

    public function getRecentFreeGiftsCount(): int
    {
        return $this->db->getSingleInt(
            'SELECT COUNT(*) AS cnt
                FROM twarenkorbpos
                INNER JOIN tgratisgeschenk
                    ON tgratisgeschenk.kWarenkorb = twarenkorbpos.kWarenkorb
                INNER JOIN tartikel
                    ON tartikel.kArtikel = tgratisgeschenk.kArtikel
                           AND tartikel.nIstVater = 0
                LEFT JOIN teigenschaft
                    ON teigenschaft.kArtikel = tartikel.kArtikel
                WHERE teigenschaft.kArtikel IS NULL
                AND nPosTyp = :tp
                LIMIT 100',
            'cnt',
            ['tp' => \C_WARENKORBPOS_TYP_GRATISGESCHENK]
        );
    }

    /**
     * @inheritdoc
     */
    public function insert(DomainObjectInterface $domainObject): int
    {
        if (isset($domainObject->modifiedKeys) && \count($domainObject->modifiedKeys) > 0) {
            throw new \InvalidArgumentException(
                'DomainObject has been modified. The last modified keys are '
                . \print_r($domainObject->modifiedKeys, true)
                . '. The DomainObject looks like this: '
                . \print_r($domainObject->toArray(true), true)
            );
        }
        // Map old column names with new ones
        $array = $domainObject->toArray();
        $obj   = new stdClass();
        foreach (\array_keys($array) as $key) {
            $newKey       = $this->mapColumn($key);
            $obj->$newKey = $array[$key] ?? '_DBNULL_';
        }

        return $this->db->insertRow($this->getTableName(), $obj);
    }
}
