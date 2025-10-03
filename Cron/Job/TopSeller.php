<?php

declare(strict_types=1);

namespace JTL\Cron\Job;

use JTL\Cron\Job;
use JTL\Cron\JobInterface;
use JTL\Cron\QueueEntry;
use JTL\Settings\Option\Globals;
use JTL\Settings\Settings;

/**
 * Class TopSeller
 * @package JTL\Cron\Job
 */
final class TopSeller extends Job
{
    /**
     * @inheritdoc
     */
    public function start(QueueEntry $queueEntry): JobInterface
    {
        parent::start($queueEntry);

        $maxDays  = Settings::intValue(Globals::BESTSELLER_DAYS);
        $minCount = Settings::intValue(Globals::BESTSELLER_MIN_QTY);
        $params   = ['attrTopSeller' => \FKT_ATTRIBUT_NO_TOPSELLER];
        if ($maxDays > 0) {
            $params['maxDays'] = $maxDays;
        }
        if ($minCount > 0) {
            $params['minCount'] = $minCount;
        }

        $this->db->query('TRUNCATE tbestseller');
        $this->db->queryPrepared(
            'INSERT INTO tbestseller (kArtikel, fAnzahl, isBestseller)
                SELECT
                    m.kArtikel,
                    IF(SUM(m.inTime) >= :minCount, SUM(m.inTime), SUM(m.nAnzahl)) AS fAnzahl,
                    IF(SUM(m.inTime) >= :minCount, 1, 0) AS isBestseller
                FROM (
                    SELECT twarenkorbpos.kArtikel, twarenkorbpos.nAnzahl,
                           IF(tbestellung.dErstellt > SUBDATE(CURDATE(), :maxDays), twarenkorbpos.nAnzahl, 0) AS inTime
                    FROM tbestellung
                    INNER JOIN twarenkorbpos ON twarenkorbpos.kWarenkorb = tbestellung.kWarenkorb
                    LEFT JOIN tartikelattribut ON twarenkorbpos.kArtikel = tartikelattribut.kArtikel
                        AND tartikelattribut.cName = :attrTopSeller
                    WHERE tbestellung.cStatus > 1
                      AND twarenkorbpos.kArtikel > 0
                      AND tartikelattribut.kArtikelAttribut IS NULL
                    UNION ALL
                    SELECT tartikel.kVaterArtikel, twarenkorbpos.nAnzahl,
                           IF(tbestellung.dErstellt > SUBDATE(CURDATE(), :maxDays), twarenkorbpos.nAnzahl, 0) AS inTime
                    FROM tbestellung
                    INNER JOIN twarenkorbpos ON twarenkorbpos.kWarenkorb = tbestellung.kWarenkorb
                    INNER JOIN tartikel ON twarenkorbpos.kArtikel = tartikel.kArtikel
                    LEFT JOIN tartikelattribut AS child_attr ON twarenkorbpos.kArtikel = child_attr.kArtikel
                        AND child_attr.cName = :attrTopSeller
                    LEFT JOIN tartikelattribut AS parent_attr ON tartikel.kVaterArtikel = parent_attr.kArtikel
                        AND parent_attr.cName = :attrTopSeller
                    WHERE tbestellung.cStatus > 1
                      AND twarenkorbpos.kArtikel > 0
                      AND tartikel.kVaterArtikel > 0
                      AND child_attr.kArtikelAttribut IS NULL
                      AND parent_attr.kArtikelAttribut IS NULL
                ) AS m
                GROUP BY m.kArtikel',
            $params
        );
        $this->setFinished(true);

        return $this;
    }
}
