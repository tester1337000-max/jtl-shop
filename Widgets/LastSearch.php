<?php

declare(strict_types=1);

namespace JTL\Widgets;

use JTL\Backend\Permissions;

/**
 * Class LastSearch
 * @package JTL\Widgets
 */
class LastSearch extends AbstractWidget
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->setPermission(Permissions::MODULE_LIVESEARCH_VIEW);

        $lastQueries = $this->getDB()->getObjects(
            'SELECT search.*, cIso FROM tsuchanfrage search
                    LEFT JOIN tsprache lang on search.kSprache = lang.kSprache
                ORDER BY dZuletztGesucht DESC 
                LIMIT 10'
        );
        $this->getSmarty()->assign('lastQueries', $lastQueries);
    }

    /**
     * @inheritdoc
     */
    public function getContent(): string
    {
        return $this->getSmarty()->fetch('tpl_inc/widgets/widgetLastSearch.tpl');
    }
}
