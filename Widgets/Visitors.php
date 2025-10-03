<?php

declare(strict_types=1);

namespace JTL\Widgets;

use JTL\Backend\Permissions;
use JTL\Backend\Stats;
use JTL\Helpers\Date;
use JTL\Linechart;
use JTL\Statistik;

/**
 * Class Visitors
 * @package JTL\Widgets
 */
class Visitors extends AbstractWidget
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->setPermission(Permissions::STATS_VISITOR_VIEW);
    }

    /**
     * @return \stdClass[]
     */
    public function getVisitorsOfCurrentMonth(): array
    {
        return (new Statistik(Date::getFirstDayOfMonth() ?: -1, \time()))->holeBesucherStats(2);
    }

    /**
     * @return \stdClass[]
     */
    public function getVisitorsOfLastMonth(): array
    {
        $month = \date('m') - 1;
        $year  = (int)\date('Y');
        if ($month <= 0) {
            $month = 12;
            $year  = (int)(\date('Y') - 1);
        }
        $stats = new Statistik(
            Date::getFirstDayOfMonth($month, $year) ?: 0,
            Date::getLastDayOfMonth($month, $year) ?: 0
        );

        return $stats->holeBesucherStats(2);
    }

    public function getJSON(): Linechart
    {
        $currentMonth = $this->getVisitorsOfCurrentMonth();
        $lastMonth    = $this->getVisitorsOfLastMonth();
        foreach ($currentMonth as $item) {
            $item->dZeit = \mb_substr($item->dZeit, 0, 2);
        }
        foreach ($lastMonth as $item) {
            $item->dZeit = \mb_substr($item->dZeit, 0, 2);
        }

        $series = [
            'Letzter Monat' => $lastMonth,
            'Dieser Monat'  => $currentMonth
        ];

        return Stats::prepareLineChartStatsMulti($series, Stats::getAxisNames(\STATS_ADMIN_TYPE_BESUCHER), 2);
    }

    /**
     * @inheritdoc
     */
    public function getContent(): string
    {
        return $this->oSmarty->assign('linechart', $this->getJSON())
            ->fetch('tpl_inc/widgets/visitors.tpl');
    }
}
