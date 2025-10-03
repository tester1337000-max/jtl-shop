<?php

declare(strict_types=1);

namespace JTL\Widgets;

use JTL\Backend\Permissions;

/**
 * Class Top10Search
 * @package JTL\Widgets
 */
class Top10Search extends AbstractWidget
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->setPermission(Permissions::MODULE_LIVESEARCH_VIEW);

        $searchQueries = $this->getDB()->getObjects(
            'SELECT search.*, cIso FROM tsuchanfrage search
                    LEFT JOIN tsprache lang on search.kSprache = lang.kSprache 
                  WHERE DATE_SUB(NOW(), INTERVAL 7 DAY) < dZuletztGesucht
                  ORDER BY nAnzahlGesuche DESC LIMIT 10'
        );
        $this->getSmarty()->assign('searchQueries', $searchQueries);
    }

    /**
     * @inheritdoc
     */
    public function getContent(): string
    {
        return $this->getSmarty()->fetch('tpl_inc/widgets/widgetTop10Search.tpl');
    }
}
