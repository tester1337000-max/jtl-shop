<?php

declare(strict_types=1);

namespace JTL\Widgets;

use JTL\Backend\Permissions;
use JTL\Helpers\Date;
use JTL\Statistik;

/**
 * Class Bots
 * @package JTL\Widgets
 */
class Bots extends AbstractWidget
{
    /**
     * @var \stdClass[]
     */
    public array $bots;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->bots = $this->getBotsOfMonth((int)\date('Y'), (int)\date('m'));
        $this->setPermission(Permissions::STATS_CRAWLER_VIEW);
    }

    /**
     * @return array<int, \stdClass&object{cUserAgent: string, nCount: int}>
     */
    public function getBotsOfMonth(int $year, int $month, int $limit = 10): array
    {
        return (new Statistik(Date::getFirstDayOfMonth($month, $year) ?: 0, \time()))->holeBotStats($limit);
    }

    /**
     * @inheritdoc
     */
    public function getContent(): string
    {
        return $this->oSmarty->assign('oBots_arr', $this->bots)->fetch('tpl_inc/widgets/bots.tpl');
    }
}
