<?php

declare(strict_types=1);

namespace JTL\Widgets;

use JTL\Backend\Permissions;
use JTL\Shop;

/**
 * Class Shopinfo
 * @package JTL\Widgets
 */
class Shopinfo extends AbstractWidget
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->oSmarty->assign('strFileVersion', \APPLICATION_VERSION)
            ->assign('strDBVersion', Shop::getShopDatabaseVersion())
            ->assign('strTplVersion', Shop::Container()->getTemplateService()->getActiveTemplate()->getVersion())
            ->assign('strUpdated', (new \DateTime($this->getLastMigrationDate()))->format('d.m.Y, H:i:m'))
            ->assign('strMinorVersion', \APPLICATION_BUILD_SHA === '#DEV#' ? 'DEV' : '');

        $this->setPermission(Permissions::DIAGNOSTIC_VIEW);
    }

    /**
     * @inheritdoc
     */
    public function getContent(): string
    {
        return $this->oSmarty->fetch('tpl_inc/widgets/shopinfo.tpl');
    }

    private function getLastMigrationDate(): string
    {
        return $this->getDB()->getSingleObject('SELECT MAX(dExecuted) AS date FROM tmigration')->date ?? '';
    }
}
