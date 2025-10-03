<?php

declare(strict_types=1);

namespace JTL;

use JTL\Consent\Statistics\Services\ConsentStatisticsService;
use JTL\DB\DbInterface;
use JTL\DB\SqlObject;
use JTL\Helpers\Date;
use stdClass;

/**
 * Class Statistik
 * @package JTL
 */
class Statistik
{
    private int $interval = 0;

    private int $days = 0;

    private int $stampFrom = 0;

    private int $stampUntil = 0;

    /**
     * @var array<string, string>
     */
    private array $dateStartParts = [];

    /**
     * @var array<string, string>
     */
    private array $dateUntilParts = [];

    private DbInterface $db;

    public function __construct(int $stampFrom = 0, int $stampUntil = 0, string $dateFrom = '', string $dateUntil = '')
    {
        $this->db = Shop::Container()->getDB();
        if (\mb_strlen($dateFrom) > 0 && \mb_strlen($dateUntil) > 0) {
            $this->dateStartParts = Date::getDateParts($dateFrom);
            $this->dateUntilParts = Date::getDateParts($dateUntil);
        } elseif ($stampFrom > 0 && $stampUntil > 0) {
            $this->stampFrom  = $stampFrom;
            $this->stampUntil = $stampUntil;
        }
    }

    /**
     * @param int $interval - (1) = Stunden, (2) = Tage, (3) = Monate, (4) = Jahre
     * @return stdClass[]
     */
    public function holeBesucherStats(int $interval = 0): array
    {
        if (!$this->dataCanBeProcessed()) {
            return [];
        }
        $this->calculateDaysDiff();
        $this->calculateInterval();
        if ($interval > 0) {
            $this->interval = $interval;
        }
        $dateSQL = $this->getDateSQL('dZeit');
        $stats   = $this->db->getObjects(
            "SELECT * , COUNT(t.dZeit) AS nCount
                FROM (
                    SELECT dZeit, DATE_FORMAT(dZeit, '%d.%m.%Y') AS dTime,
                        DATE_FORMAT(dZeit, '%m') AS nMonth,
                        DATE_FORMAT(dZeit, '%H') AS nHour,
                        DATE_FORMAT(dZeit, '%d') AS nDay,
                        DATE_FORMAT(dZeit, '%Y') AS nYear
                    FROM tbesucherarchiv "
            . $dateSQL->getWhere() . "
                        AND kBesucherBot = 0
                    UNION ALL
                    SELECT dZeit, DATE_FORMAT( dZeit, '%d.%m.%Y' ) AS dTime,
                        DATE_FORMAT( dZeit, '%m' ) AS nMonth,
                        DATE_FORMAT( dZeit, '%H' ) AS nHour,
                        DATE_FORMAT( dZeit, '%d' ) AS nDay,
                        DATE_FORMAT( dZeit, '%Y' ) AS nYear
                    FROM tbesucher "
            . $dateSQL->getWhere() . '
                        AND kBesucherBot = 0
                ) AS t
                ' . $dateSQL->getGroupBy() . '
                ORDER BY dTime ASC',
            $dateSQL->getParams()
        );

        return $this->merge($stats);
    }

    /**
     * @return stdClass[]
     */
    public function holeKundenherkunftStats(): array
    {
        if (!$this->dataCanBeProcessed()) {
            return [];
        }
        $this->calculateDaysDiff();
        $this->calculateInterval();

        $dateSQL = $this->getDateSQL('dZeit');

        return $this->db->getObjects(
            "SELECT t.cReferer, SUM(t.nCount) AS nCount
                FROM (
                    SELECT IF(cReferer = '', :directEntry, cReferer) AS cReferer,
                    COUNT(dZeit) AS nCount
                    FROM tbesucher "
            . $dateSQL->getWhere() . "
                        AND kBesucherBot = 0
                    GROUP BY cReferer
                    UNION ALL
                    SELECT IF(cReferer = '', :directEntry, cReferer) AS cReferer,
                    COUNT(dZeit) AS nCount
                    FROM tbesucherarchiv "
            . $dateSQL->getWhere() . '
                        AND kBesucherBot = 0
                    GROUP BY cReferer
                ) AS t
                GROUP BY t.cReferer
                ORDER BY nCount DESC',
            \array_merge(['directEntry' => \__('directEntry')], $dateSQL->getParams())
        );
    }

    /**
     * @return stdClass[]
     */
    public function holeBotStats(int $limit = -1): array
    {
        if (!$this->dataCanBeProcessed()) {
            return [];
        }
        $this->calculateDaysDiff();
        $this->calculateInterval();

        $dateSQL = $this->getDateSQL('dZeit');

        return $this->db->getObjects(
            'SELECT tbesucherbot.cUserAgent, COUNT(tbesucherbot.kBesucherBot) AS nCount
                FROM
                    (
                        SELECT kBesucherBot
                        FROM tbesucherarchiv '
            . $dateSQL->getWhere() . ' AND kBesucherBot > 0
                        UNION ALL
                        SELECT kBesucherBot
                        FROM tbesucher '
            . $dateSQL->getWhere() . ' AND kBesucherBot > 0
                    ) AS t
                    JOIN tbesucherbot ON tbesucherbot.kBesucherBot = t.kBesucherBot
                GROUP BY tbesucherbot.cUserAgent
                ORDER BY nCount DESC ' . ($limit > -1 ? 'LIMIT ' . $limit : ''),
            $dateSQL->getParams()
        );
    }

    /**
     * @return stdClass[]
     */
    public function holeUmsatzStats(): array
    {
        if (!$this->dataCanBeProcessed()) {
            return [];
        }
        $this->calculateDaysDiff();
        $this->calculateInterval();

        $dateSQL = $this->getDateSQL('tbestellung.dErstellt');

        return $this->merge(
            $this->db->getObjects(
                "SELECT tbestellung.dErstellt AS dZeit, SUM(tbestellung.fGesamtsumme) AS nCount,
                    DATE_FORMAT(tbestellung.dErstellt, '%m') AS nMonth,
                    DATE_FORMAT(tbestellung.dErstellt, '%H') AS nHour,
                    DATE_FORMAT(tbestellung.dErstellt, '%d') AS nDay,
                    DATE_FORMAT(tbestellung.dErstellt, '%Y') AS nYear
                    FROM tbestellung "
                . $dateSQL->getWhere() . "
                        AND cStatus != '-1'
                    " . $dateSQL->getGroupBy() . '
                    ORDER BY tbestellung.dErstellt ASC',
                $dateSQL->getParams()
            )
        );
    }

    /**
     * @return stdClass[]
     */
    public function holeEinstiegsseiten(): array
    {
        if (!$this->dataCanBeProcessed()) {
            return [];
        }
        $this->calculateDaysDiff();
        $this->calculateInterval();

        $dateSQL = $this->getDateSQL('dZeit');

        return $this->db->getObjects(
            'SELECT t.cEinstiegsseite, COUNT(t.cEinstiegsseite) AS nCount
                FROM
                (
                    SELECT cEinstiegsseite
                    FROM tbesucher '
            . $dateSQL->getWhere() . '
                        AND kBesucherBot = 0
                    UNION ALL
                    SELECT cEinstiegsseite
                    FROM tbesucherarchiv '
            . $dateSQL->getWhere() . '
                        AND kBesucherBot = 0
                ) AS t
                GROUP BY t.cEinstiegsseite
                ORDER BY nCount DESC',
            $dateSQL->getParams()
        );
    }

    /**
     * @param string[]|null $eventNames
     * @return stdClass[]
     */
    public function getConsentStats(?array $eventNames = null): array
    {
        if (($this->stampFrom > 0 && $this->stampUntil > 0)) {
            $data = (new ConsentStatisticsService())->getConsentStats(
                eventDateFrom: \date('Y-m-d', $this->stampFrom),
                eventDateTo: \date('Y-m-d', $this->stampUntil),
                eventNames: $eventNames ?? []
            );

            return (array)$data;
        }

        return [];
    }

    private function calculateDaysDiff(): self
    {
        if (\count($this->dateStartParts) > 0 && \count($this->dateUntilParts) > 0) {
            $dateDiff = $this->db->getSingleObject(
                'SELECT DATEDIFF(:to, :from) AS nTage',
                ['from' => $this->dateStartParts['cDatum'], 'to' => $this->dateUntilParts['cDatum']]
            );
            if ($dateDiff !== null) {
                $this->days = (int)$dateDiff->nTage + 1;
            }
        } elseif ($this->stampFrom > 0 && $this->stampUntil > 0) {
            $this->days = (int)\floor(($this->stampUntil - $this->stampFrom) / 3600 / 24);
            if ($this->days <= 1) {
                $this->days = 1;
            }
        }

        return $this;
    }

    private function calculateInterval(): self
    {
        if ($this->days === 1) {
            $this->interval = 1;
        } elseif ($this->days <= 31) { // Tage
            $this->interval = 2;
        } elseif ($this->days <= 365) { // Monate
            $this->interval = 3;
        } else { // Jahre
            $this->interval = 4;
        }

        return $this;
    }

    private function getDateSQL(string $row): SqlObject
    {
        $date = new SqlObject();
        if (\count($this->dateStartParts) > 0 && \count($this->dateUntilParts) > 0) {
            $timeStart = '00:00:00';
            if (isset($this->dateStartParts['cZeit']) && \mb_strlen($this->dateStartParts['cZeit']) > 0) {
                $timeStart = $this->dateStartParts['cZeit'];
            }
            $timeEnd = '23:59:59';
            if (isset($this->dateUntilParts['cZeit']) && \mb_strlen($this->dateUntilParts['cZeit']) > 0) {
                $timeEnd = $this->dateUntilParts['cZeit'];
            }
            $date->setWhere(' WHERE ' . $row . ' BETWEEN :strt AND :nd ');
            $date->addParam(':strt', $this->dateStartParts['cDatum'] . ' ' . $timeStart);
            $date->addParam(':nd', $this->dateUntilParts['cDatum'] . ' ' . $timeEnd);
        } elseif ($this->stampFrom > 0 && $this->stampUntil > 0) {
            $date->setWhere(' WHERE ' . $row . ' BETWEEN :strt AND :nd');
            $date->addParam(':strt', \date('Y-m-d H:i:s', $this->stampFrom));
            $date->addParam(':nd', \date('Y-m-d H:i:s', $this->stampUntil));
        }

        if ($this->interval > 0) {
            switch ($this->interval) {
                case 1: // Stunden
                    $date->setGroupBy(' GROUP BY HOUR(' . $row . ')');
                    break;

                case 2: // Tage
                    $date->setGroupBy(' GROUP BY DAY(' . $row . '), YEAR(' . $row . '), MONTH(' . $row . ')');
                    break;

                case 3: // Monate
                    $date->setGroupBy(' GROUP BY MONTH(' . $row . '), YEAR(' . $row . ')');
                    break;

                case 4: // Jahre
                    $date->setGroupBy(' GROUP BY YEAR(' . $row . ')');
                    break;
            }
        }

        return $date;
    }

    /**
     * @return stdClass[]
     */
    private function vordefStats(): array
    {
        if (!$this->interval) {
            return [];
        }
        $stats = [];
        $day   = (int)\date('d', $this->stampFrom);
        $month = (int)\date('m', $this->stampFrom);
        $year  = (int)\date('Y', $this->stampFrom);

        switch ($this->interval) {
            case 1: // Stunden
                for ($i = 0; $i <= 23; $i++) {
                    $stat         = new stdClass();
                    $stat->dZeit  = \mktime($i, 0, 0, $month, $day, $year);
                    $stat->nCount = 0;
                    $stats[]      = $stat;
                }
                break;

            case 2: // Tage
                for ($i = 0; $i <= 30; $i++) {
                    $stat         = new stdClass();
                    $stat->dZeit  = \mktime(0, 0, 0, $month, $day + $i, $year);
                    $stat->nCount = 0;
                    $stats[]      = $stat;
                }
                break;

            case 3: // Monate
                for ($i = 0; $i <= 11; $i++) {
                    $stat         = new stdClass();
                    $nextYear     = $month + $i > 12;
                    $monthTMP     = $nextYear ? $month + $i - 12 : $month + $i;
                    $yearTMP      = $nextYear ? $year + 1 : $year;
                    $stat->dZeit  = \mktime(
                        0,
                        0,
                        0,
                        $monthTMP,
                        \min($day, \cal_days_in_month(\CAL_GREGORIAN, $monthTMP, $yearTMP)),
                        $yearTMP
                    );
                    $stat->nCount = 0;
                    $stats[]      = $stat;
                }
                break;

            case 4:    // Jahre
                if (\count($this->dateStartParts) > 0 && \count($this->dateUntilParts) > 0) {
                    $yearStart = (int)\date('Y', \strtotime($this->dateStartParts['cDatum']));
                    $yearEnd   = (int)\date('Y', \strtotime($this->dateUntilParts['cDatum']));
                } elseif ($this->stampFrom > 0 && $this->stampUntil > 0) {
                    $yearStart = (int)\date('Y', $this->stampFrom);
                    $yearEnd   = (int)\date('Y', $this->stampUntil);
                } else {
                    $yearStart = (int)\date('Y') - 1;
                    $yearEnd   = (int)\date('Y') + 10;
                }
                for ($i = $yearStart; $i <= $yearEnd; $i++) {
                    $stat         = new stdClass();
                    $stat->dZeit  = \mktime(0, 0, 0, 1, 1, $i);
                    $stat->nCount = 0;
                    $stats[]      = $stat;
                }
                break;
        }

        return $stats;
    }

    /**
     * @param stdClass[] $tmpData
     * @return stdClass[]
     */
    private function merge(array $tmpData): array
    {
        $stats     = $this->vordefStats();
        $dayFrom   = (int)\date('d', $this->stampFrom);
        $monthFrom = (int)\date('m', $this->stampFrom);
        $yearFrom  = (int)\date('Y', $this->stampFrom);
        $dayTo     = (int)\date('d', $this->stampUntil);
        $monthTo   = (int)\date('m', $this->stampUntil);
        $yearTo    = (int)\date('Y', $this->stampUntil);
        if ($this->stampFrom > 0) {
            switch ($this->interval) {
                case 1: // Stunden
                    $start = \mktime(0, 0, 0, $monthFrom, $dayFrom, $yearFrom);
                    $end   = \mktime(23, 59, 59, $monthTo, $dayTo, $yearTo);
                    break;
                case 2: // Tage
                    $start = \mktime(0, 0, 0, $monthFrom, $dayFrom, $yearFrom);
                    $end   = \mktime(23, 59, 59, $monthTo, $dayTo, $yearTo);
                    break;
                case 3: // Monate
                    $start = \mktime(0, 0, 0, $monthFrom, 1, $yearFrom);
                    $end   = \mktime(
                        23,
                        59,
                        59,
                        $monthTo,
                        \cal_days_in_month(\CAL_GREGORIAN, $monthTo, $yearTo),
                        $yearTo
                    );
                    break;
                case 4:    // Jahre
                    $start = \mktime(0, 0, 0, 1, 1, $yearFrom);
                    $end   = \mktime(23, 59, 59, 12, 31, $yearTo);
                    break;
                default:
                    $start = 0;
                    $end   = 0;
                    break;
            }
            foreach ($stats as $i => $item) {
                $time = (int)$item->dZeit;
                if ($time < $start || $time > $end) {
                    unset($stats[$i]);
                }
            }
            $stats = \array_values($stats);
        }
        if (\count($tmpData) === 0) {
            return [];
        }
        foreach ($stats as $item) {
            $found = false;
            foreach ($tmpData as $tmpItem) {
                $break = false;
                switch ($this->interval) {
                    case 1: // Stunden
                        if (\date('H', $item->dZeit) === $tmpItem->nHour) {
                            $item->nCount = (int)$tmpItem->nCount;
                            $item->dZeit  = $tmpItem->nHour;
                            $break        = true;
                        }
                        break;
                    case 2: // Tage
                        if (\date('d.m.', $item->dZeit) === $tmpItem->nDay . '.' . $tmpItem->nMonth . '.') {
                            $item->nCount = (int)$tmpItem->nCount;
                            $item->dZeit  = $tmpItem->nDay . '.' . $tmpItem->nMonth . '.';
                            $break        = true;
                        }
                        break;
                    case 3: // Monate
                        if (\date('m.Y', $item->dZeit) === $tmpItem->nMonth . '.' . $tmpItem->nYear) {
                            $item->nCount = (int)$tmpItem->nCount;
                            $item->dZeit  = $tmpItem->nMonth . '.' . $tmpItem->nYear;
                            $break        = true;
                        }
                        break;
                    case 4: // Jahre
                        if (\date('Y', $item->dZeit) === $tmpItem->nYear) {
                            $item->nCount = (int)$tmpItem->nCount;
                            $item->dZeit  = $tmpItem->nYear;
                            $break        = true;
                        }
                        break;
                }
                if ($break) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                switch ($this->interval) {
                    case 1: // Stunden
                        $item->dZeit = \date('H', (int)$item->dZeit);
                        break;
                    case 2: // Tage
                        $item->dZeit = \date('d.m.', (int)$item->dZeit);
                        break;
                    case 3: // Monate
                        $item->dZeit = \date('m.Y', (int)$item->dZeit);
                        break;
                    case 4: // Jahre
                        $item->dZeit = \date('Y', (int)$item->dZeit);
                        break;
                }
            }
        }

        return $stats;
    }

    public function setDatumVon(string $cDatumVon): self
    {
        $this->dateStartParts = Date::getDateParts($cDatumVon);

        return $this;
    }

    public function setDatumBis(string $cDatumBis): self
    {
        $this->dateUntilParts = Date::getDateParts($cDatumBis);

        return $this;
    }

    public function setDatumStampVon(int $nDatumVon): self
    {
        $this->stampFrom = $nDatumVon;

        return $this;
    }

    public function setDatumStampBis(int $nDatumBis): self
    {
        $this->stampUntil = $nDatumBis;

        return $this;
    }

    public function getAnzeigeIntervall(): int
    {
        if ($this->interval === 0) {
            if ($this->days === 0) {
                $this->calculateDaysDiff();
            }

            $this->calculateInterval();
        }

        return $this->interval;
    }

    public function getAnzahlTage(): int
    {
        if ($this->days === 0) {
            $this->calculateDaysDiff();
        }

        return $this->days;
    }

    private function dataCanBeProcessed(): bool
    {
        return ($this->stampFrom > 0 && $this->stampUntil > 0)
            || (\count($this->dateStartParts) > 0 && \count($this->dateUntilParts) > 0);
    }
}
