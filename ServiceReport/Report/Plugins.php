<?php

declare(strict_types=1);

namespace JTL\ServiceReport\Report;

use JTL\Plugin\Admin\Listing;
use JTL\Plugin\Admin\ListingItem;
use JTL\Plugin\Admin\Validation\LegacyPluginValidator;
use JTL\Plugin\Admin\Validation\PluginValidator;
use JTL\Shop;
use JTL\XMLParser;
use stdClass;

class Plugins implements ReportInterface
{
    /**
     * @return array{enabled: stdClass[], disabled: stdClass[], erroneous: stdClass[], problematic: stdClass[]}
     */
    public function getData(): array
    {
        $db              = Shop::Container()->getDB();
        $cache           = Shop::Container()->getCache();
        $parser          = new XMLParser();
        $legacyValidator = new LegacyPluginValidator($db, $parser);
        $validator       = new PluginValidator($db, $parser);
        $listing         = new Listing($db, $cache, $legacyValidator, $validator);

        return [
            'enabled'     => $listing->getEnabled()->map($this->mapItem(...))->values()->all(),
            'disabled'    => $listing->getDisabled()->map($this->mapItem(...))->values()->all(),
            'erroneous'   => $listing->getErroneous()->map($this->mapItem(...))->values()->all(),
            'problematic' => $listing->getProblematic()->map($this->mapItem(...))->values()->all(),
        ];
    }

    public function mapItem(ListingItem $item): stdClass
    {
        return (object)[
            'name'              => $item->getName(),
            'errorCode'         => $item->getErrorCode(),
            'errorMsg'          => $item->getErrorMessage(),
            'author'            => $item->getAuthor(),
            'dir'               => $item->getDir(),
            'id'                => $item->getID(),
            'exsID'             => $item->getExsID(),
            'pluginID'          => $item->getPluginID(),
            'version'           => (string)$item->getVersion(),
            'minShopVersion'    => (string)$item->getMinShopVersion(),
            'maxShopVersion'    => (string)$item->getMaxShopVersion(),
            'isShop5compatible' => $item->isShop5Compatible(),
            'state'             => $item->getState(),
        ];
    }
}
