<?php

declare(strict_types=1);

namespace JTL\Widgets;

use JTL\Backend\Permissions;

/**
 * Class News
 * @package JTL\Widgets
 */
class News extends AbstractWidget
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->setPermission(Permissions::DASHBOARD_VIEW);
    }

    /**
     * @inheritdoc
     */
    public function getContent(): string
    {
        return $this->oSmarty->fetch('tpl_inc/widgets/news.tpl');
    }
}
