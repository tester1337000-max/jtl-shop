<?php

declare(strict_types=1);

namespace JTL\Widgets;

use JTL\Backend\Permissions;
use JTL\Catalog\Product\Preise;
use stdClass;

/**
 * Class ShopStats
 * @package JTL\Widgets
 */
class ShopStats extends AbstractWidget
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $now       = \mktime(0, 0, 0);
        $yesterday = new stdClass();
        $today     = new stdClass();
        $this->setPermission(Permissions::STATS_EXCHANGE_VIEW);

        $yesterday->nStampVon = $now - 86400;
        $yesterday->nStampBis = $now;
        $today->nStampVon     = $now;
        $today->nStampBis     = $now + 86400;

        $this->initSales($yesterday, $today)
            ->initVisitors($yesterday, $today)
            ->initNewCustomers($yesterday, $today)
            ->initOrderCount($yesterday, $today)
            ->initVisitorsPerOrder($yesterday, $today)
            ->getSmarty()->assign('oStatYesterday', $yesterday)
            ->assign('oStatToday', $today);
    }

    /**
     * @inheritdoc
     */
    public function getContent(): string
    {
        return $this->getSmarty()->fetch('tpl_inc/widgets/widgetShopStats.tpl');
    }

    /**
     * @param stdClass $yesterday
     * @param stdClass $today
     * @return $this
     */
    private function initSales(stdClass $yesterday, stdClass $today): self
    {
        $sales = $this->getDB()->getObjects(
            "SELECT tbestellung.dErstellt AS dZeit, SUM(tbestellung.fGesamtsumme) AS nCount,
                DATE_FORMAT(tbestellung.dErstellt, '%m') AS nMonth, DATE_FORMAT(tbestellung.dErstellt, '%H') AS nHour, 
                DATE_FORMAT(tbestellung.dErstellt, '%d') AS nDay,
                DATE_FORMAT(tbestellung.dErstellt, '%Y') AS nYear
                FROM tbestellung
                WHERE tbestellung.dErstellt BETWEEN FROM_UNIXTIME(:from) AND FROM_UNIXTIME(:until)
                  AND tbestellung.cStatus > -1
                GROUP BY DAY(tbestellung.dErstellt), YEAR(tbestellung.dErstellt), MONTH(tbestellung.dErstellt)
                ORDER BY tbestellung.dErstellt ASC",
            ['from' => $yesterday->nStampVon, 'until' => $yesterday->nStampBis]
        );

        $yesterday->fUmsatz = 0.0;
        foreach ($sales as $sale) {
            $yesterday->fUmsatz += $sale->nCount;
        }
        $yesterday->fUmsatz = Preise::getLocalizedPriceString($yesterday->fUmsatz);

        $sales          = $this->getDB()->getObjects(
            "SELECT tbestellung.dErstellt AS dZeit, SUM(tbestellung.fGesamtsumme) AS nCount,
                DATE_FORMAT(tbestellung.dErstellt, '%m') AS nMonth, DATE_FORMAT(tbestellung.dErstellt, '%H') AS nHour, 
                DATE_FORMAT(tbestellung.dErstellt, '%d') AS nDay,
                DATE_FORMAT(tbestellung.dErstellt, '%Y') AS nYear
                FROM tbestellung
                WHERE tbestellung.dErstellt BETWEEN FROM_UNIXTIME(:from) AND FROM_UNIXTIME(:until)
                  AND tbestellung.cStatus > -1
                GROUP BY DAY(tbestellung.dErstellt), YEAR(tbestellung.dErstellt), MONTH(tbestellung.dErstellt) 
                ORDER BY tbestellung.dErstellt ASC",
            ['from' => $today->nStampVon, 'until' => $today->nStampBis]
        );
        $today->fUmsatz = 0.0;
        foreach ($sales as $sale) {
            $today->fUmsatz += $sale->nCount;
        }
        $today->fUmsatz = Preise::getLocalizedPriceString($today->fUmsatz);

        return $this;
    }

    /**
     * @param stdClass $yesterday
     * @param stdClass $today
     * @return $this
     */
    private function initVisitors(stdClass $yesterday, stdClass $today): self
    {
        $visitors = $this->getDB()->getObjects(
            "SELECT * , SUM(t.nCount) AS nCount
                FROM (
                SELECT dZeit, DATE_FORMAT( dZeit, '%d.%m.%Y' ) AS dTime, DATE_FORMAT( dZeit, '%m' ) AS nMonth, 
                DATE_FORMAT( dZeit, '%H' ) AS nHour, DATE_FORMAT( dZeit, '%d' ) AS nDay, 
                DATE_FORMAT( dZeit, '%Y' ) AS nYear, COUNT( dZeit ) AS nCount
                FROM tbesucherarchiv
                WHERE dZeit BETWEEN FROM_UNIXTIME(:from) 
                    AND FROM_UNIXTIME(:until)
                    AND kBesucherBot = 0
                GROUP BY DAY(dZeit), YEAR(dZeit), MONTH(dZeit)
                UNION SELECT dZeit, DATE_FORMAT( dZeit, '%d.%m.%Y' ) AS dTime, DATE_FORMAT( dZeit, '%m' ) AS nMonth, 
                    DATE_FORMAT( dZeit, '%H' ) AS nHour, 
                    DATE_FORMAT( dZeit, '%d' ) AS nDay, 
                    DATE_FORMAT( dZeit, '%Y' ) AS nYear, COUNT( dZeit ) AS nCount
                FROM tbesucher
                WHERE dZeit BETWEEN FROM_UNIXTIME(:from) AND FROM_UNIXTIME(:until)
                    AND kBesucherBot = 0
                GROUP BY DAY(dZeit), YEAR(dZeit), MONTH(dZeit)
                ) AS t
                GROUP BY DAY(dZeit), YEAR(dZeit), MONTH(dZeit)
                ORDER BY dTime ASC",
            ['from' => $yesterday->nStampVon, 'until' => $yesterday->nStampBis]
        );

        $yesterday->nBesucher = 0;
        foreach ($visitors as $visitor) {
            $yesterday->nBesucher += (int)$visitor->nCount;
        }
        $visitors = $this->getDB()->getObjects(
            "SELECT * , SUM(t.nCount) AS nCount
                FROM (
                SELECT dZeit, DATE_FORMAT( dZeit, '%d.%m.%Y' ) AS dTime, DATE_FORMAT( dZeit, '%m' ) AS nMonth, 
                DATE_FORMAT( dZeit, '%H' ) AS nHour, DATE_FORMAT( dZeit, '%d' ) AS nDay, 
                DATE_FORMAT( dZeit, '%Y' ) AS nYear, COUNT( dZeit ) AS nCount
                FROM tbesucherarchiv
                WHERE dZeit BETWEEN FROM_UNIXTIME(:from) 
                    AND FROM_UNIXTIME(:until)
                    AND kBesucherBot = 0
                GROUP BY DAY(dZeit), YEAR(dZeit), MONTH(dZeit)
                UNION SELECT dZeit, DATE_FORMAT( dZeit, '%d.%m.%Y' ) AS dTime, DATE_FORMAT( dZeit, '%m' ) AS nMonth, 
                    DATE_FORMAT( dZeit, '%H' ) AS nHour, DATE_FORMAT( dZeit, '%d' ) AS nDay, 
                    DATE_FORMAT( dZeit, '%Y' ) AS nYear, COUNT( dZeit ) AS nCount
                FROM tbesucher
                WHERE dZeit BETWEEN FROM_UNIXTIME(:from) AND FROM_UNIXTIME(:until)
                    AND kBesucherBot = 0
                GROUP BY DAY(dZeit), YEAR(dZeit), MONTH(dZeit)
                ) AS t
                GROUP BY DAY(dZeit), YEAR(dZeit), MONTH(dZeit)
                ORDER BY dTime ASC",
            ['from' => $today->nStampVon, 'until' => $today->nStampBis]
        );

        $today->nBesucher = 0;
        foreach ($visitors as $visitor) {
            $today->nBesucher += (int)$visitor->nCount;
        }

        return $this;
    }

    /**
     * @param stdClass $yesterday
     * @param stdClass $today
     * @return $this
     */
    private function initNewCustomers(stdClass $yesterday, stdClass $today): self
    {
        $yesterday->nNeuKunden = $this->getDB()->getSingleInt(
            'SELECT COUNT(*) AS cnt
                FROM tkunde
                WHERE dErstellt = :dt
                    AND nRegistriert = 1',
            'cnt',
            ['dt' => \date('Y-m-d', $yesterday->nStampVon)]
        );
        $today->nNeuKunden     = $this->getDB()->getSingleInt(
            'SELECT COUNT(*) AS cnt
                FROM tkunde
                WHERE dErstellt = :dt
                    AND nRegistriert = 1',
            'cnt',
            ['dt' => \date('Y-m-d', $today->nStampVon)]
        );

        return $this;
    }

    /**
     * @param stdClass $yesterday
     * @param stdClass $today
     * @return $this
     */
    private function initOrderCount(stdClass $yesterday, stdClass $today): self
    {
        $yesterday->nAnzahlBestellung = $this->getDB()->getSingleInt(
            'SELECT COUNT(*) AS cnt
                FROM tbestellung
                WHERE dErstellt BETWEEN FROM_UNIXTIME(:from) AND FROM_UNIXTIME(:until)  AND tbestellung.cStatus > -1',
            'cnt',
            ['from' => $yesterday->nStampVon, 'until' => $yesterday->nStampBis]
        );
        $today->nAnzahlBestellung     = $this->getDB()->getSingleInt(
            'SELECT COUNT(*) AS cnt
                FROM tbestellung
                WHERE dErstellt BETWEEN FROM_UNIXTIME(:from) AND FROM_UNIXTIME(:until) AND tbestellung.cStatus > -1',
            'cnt',
            ['from' => $today->nStampVon, 'until' => $today->nStampBis]
        );

        return $this;
    }

    /**
     * @param stdClass $yesterday
     * @param stdClass $today
     * @return $this
     */
    private function initVisitorsPerOrder(stdClass $yesterday, stdClass $today): self
    {
        $yesterday->nBesucherProBestellung = 0;
        if ($yesterday->nBesucher > 0 && $yesterday->nAnzahlBestellung > 0) {
            $yesterday->nBesucherProBestellung = \number_format(
                $yesterday->nBesucher / $yesterday->nAnzahlBestellung,
                2,
                ',',
                '.'
            );
        }
        $today->nBesucherProBestellung = 0;
        if ($today->nBesucher > 0 && $today->nAnzahlBestellung > 0) {
            $today->nBesucherProBestellung = \number_format(
                $today->nBesucher / $today->nAnzahlBestellung,
                2,
                ',',
                '.'
            );
        }

        return $this;
    }
}
