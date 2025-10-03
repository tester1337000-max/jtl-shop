<?php

declare(strict_types=1);

namespace JTL\Widgets;

use JTL\Backend\Permissions;
use JTL\Shop;

/**
 * Class Serverinfo
 * @package JTL\Widgets
 */
class Serverinfo extends AbstractWidget
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $parsed = \parse_url(Shop::getURL());
        $this->oSmarty->assign('phpOS', \PHP_OS)
            ->assign('phpVersion', \PHP_VERSION)
            ->assign('serverAddress', $_SERVER['SERVER_ADDR'] ?? '?')
            ->assign('serverHTTPHost', $_SERVER['HTTP_HOST'] ?? '?')
            ->assign('mySQLVersion', $this->oDB->getServerInfo())
            ->assign('mySQLStats', $this->oDB->getServerStats())
            ->assign('cShopHost', ($parsed['scheme'] ?? 'http') . '://' . ($parsed['host'] ?? '???'));

        $this->setPermission(Permissions::DIAGNOSTIC_VIEW);
    }

    /**
     * @inheritdoc
     */
    public function getContent(): string
    {
        return $this->oSmarty->fetch('tpl_inc/widgets/serverinfo.tpl');
    }
}
