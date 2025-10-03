<?php

declare(strict_types=1);

namespace JTL\Widgets;

use JTL\Backend\Permissions;

/**
 * Class Top10Bestseller
 * @package JTL\Widgets
 */
class Top10Bestseller extends AbstractWidget
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->setPermission(Permissions::ORDER_VIEW);

        $bestsellers = $this->getDB()->getObjects(
            'SELECT tartikel.kArtikel, tbestseller.fAnzahl, tartikel.cName
                FROM tbestseller
                JOIN tartikel
                    ON tbestseller.kArtikel = tartikel.kArtikel AND tbestseller.isBestseller = 1
                ORDER BY tbestseller.fAnzahl DESC
                LIMIT 10'
        );

        $this->getSmarty()->assign('bestsellers', $bestsellers);
    }

    /**
     * @inheritdoc
     */
    public function getContent(): string
    {
        return $this->getSmarty()->fetch('tpl_inc/widgets/widgetTop10Bestseller.tpl');
    }
}
