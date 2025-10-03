<?php

declare(strict_types=1);

namespace JTL\Helpers;

use JTL\DB\DbInterface;

/**
 * Class XSelling
 * @package JTL\Helpers
 */
class XSelling
{
    private DbInterface $db;

    private static ?string $xSellingTable = null;

    public function __construct(DbInterface $db)
    {
        $this->db = $db;
    }

    private function getXSellTable(): string
    {
        if (self::$xSellingTable === null) {
            self::$xSellingTable = $this->db->getSingleInt(
                'SELECT 1 AS hasData FROM cross_selling_view LIMIT 1',
                'hasData'
            ) > 0 ? 'cross_selling_view' : 'txsellkauf';
        }

        return self::$xSellingTable;
    }

    /**
     * @param int[] $productIds
     * @param int   $limit
     * @return int[]
     */
    public function getXSellingCart(array $productIds, int $limit): array
    {
        $xsellTable = $this->getXSellTable();
        $productIDs = \implode(',', $productIds);

        return $this->db->getInts(
            'SELECT DISTINCT kXSellArtikel
                FROM ' . $xsellTable . '
                WHERE kArtikel IN (' . $productIDs . ')
                    AND kXSellArtikel NOT IN (' . $productIDs . ')
                ORDER BY nAnzahl DESC
                LIMIT :limit',
            'kXSellArtikel',
            ['limit' => $limit]
        );
    }

    /**
     * @return int[]
     */
    private function getXSellingPurchaseParent(int $productID, bool $showParent, int $limit): array
    {
        $xsellTable = $this->getXSellTable();
        $selectSQL  = $xsellTable . '.kXSellArtikel';
        $filterSQL  = 'tartikel.kVaterArtikel';

        if ($showParent) {
            $selectSQL = 'IF(tartikel.kVaterArtikel = 0, '
                . $xsellTable . '.kXSellArtikel, tartikel.kVaterArtikel)';
            $filterSQL = 'IF(tartikel.kVaterArtikel = 0, '
                . $xsellTable . '.kXSellArtikel, tartikel.kVaterArtikel)';
        }

        return $this->db->getInts(
            'SELECT ' . $productID . ' AS kArtikel, ' . $selectSQL . ' AS kXSellArtikel,
                    SUM(' . $xsellTable . '.nAnzahl) nAnzahl
                    FROM ' . $xsellTable . '
                    JOIN tartikel ON tartikel.kArtikel = ' . $xsellTable . '.kXSellArtikel
                    WHERE (' . $xsellTable . '.kArtikel IN (
                            SELECT tartikel.kArtikel
                            FROM tartikel
                            WHERE tartikel.kVaterArtikel = :pid
                        ) OR ' . $xsellTable . '.kArtikel = :pid)
                        AND ' . $filterSQL . ' != :pid
                    GROUP BY 1, 2
                    ORDER BY SUM(' . $xsellTable . '.nAnzahl) DESC
                    LIMIT :lmt',
            'kXSellArtikel',
            ['pid' => $productID, 'lmt' => $limit]
        );
    }

    /**
     * @return int[]
     */
    private function getXSellingPurchaseWithParent(int $productID, int $limit): array
    {
        $xsellTable = $this->getXSellTable();

        return $this->db->getInts(
            'SELECT ' . $xsellTable . '.kArtikel,
                    IF(tartikel.kVaterArtikel = 0, ' . $xsellTable
            . '.kXSellArtikel, tartikel.kVaterArtikel) AS kXSellArtikel,
                    SUM(' . $xsellTable . '.nAnzahl) AS nAnzahl
                    FROM ' . $xsellTable . '
                    JOIN tartikel
                        ON tartikel.kArtikel = ' . $xsellTable . '.kXSellArtikel
                    WHERE ' . $xsellTable . '.kArtikel = :pid
                        AND (tartikel.kVaterArtikel != (
                            SELECT tartikel.kVaterArtikel
                            FROM tartikel
                            WHERE tartikel.kArtikel = :pid
                        ) OR tartikel.kVaterArtikel = 0)
                    GROUP BY 1, 2
                    ORDER BY SUM(' . $xsellTable . '.nAnzahl) DESC
                    LIMIT :lmt',
            'kXSellArtikel',
            ['pid' => $productID, 'lmt' => $limit]
        );
    }

    /**
     * @return int[]
     */
    public function getXSellingPurchase(int $productID, bool $isParent, bool $showParent, int $limit): array
    {
        $xsellTable = $this->getXSellTable();
        if ($isParent === true) {
            return $this->getXSellingPurchaseParent($productID, $showParent, $limit);
        }
        if ($showParent) {
            return $this->getXSellingPurchaseWithParent($productID, $limit);
        }

        return $this->db->getInts(
            'SELECT kXSellArtikel
                FROM ' . $xsellTable . '
                WHERE kArtikel = :pid
                ORDER BY nAnzahl DESC
                LIMIT :lmt',
            'kXSellArtikel',
            ['pid' => $productID, 'lmt' => $limit],
        );
    }
}
