<?php

declare(strict_types=1);

namespace JTL\Widgets;

use JTL\Backend\Permissions;

/**
 * Class Patch
 * @package JTL\Widgets
 */
class Patch extends AbstractWidget
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->setPermission(Permissions::DIAGNOSTIC_VIEW);
    }

    /**
     * @inheritdoc
     */
    public function getContent(): string
    {
        return $this->oSmarty->assign('version', $this->getDBVersion())->fetch('tpl_inc/widgets/patch.tpl');
    }

    private function getDBVersion(): string
    {
        return $this->getDB()->getSingleObject('SELECT nVersion FROM tversion')->nVersion ?? '0.0.0';
    }
}
