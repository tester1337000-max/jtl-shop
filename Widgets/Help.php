<?php

declare(strict_types=1);

namespace JTL\Widgets;

use JTL\Backend\Permissions;

/**
 * Class Help
 * @package JTL\Widgets
 */
class Help extends AbstractWidget
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
        return $this->oSmarty->fetch('tpl_inc/widgets/help.tpl');
    }
}
