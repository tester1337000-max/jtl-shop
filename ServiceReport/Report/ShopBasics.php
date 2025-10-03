<?php

declare(strict_types=1);

namespace JTL\ServiceReport\Report;

class ShopBasics implements ReportInterface
{
    /**
     * @return array<string, string|int>
     */
    public function getData(): array
    {
        return [
            'version'        => \APPLICATION_VERSION,
            'root'           => \PFAD_ROOT,
            'urlShop'        => \URL_SHOP,
            'host'           => $_SERVER['HTTP_HOST'],
            'shopLogLevel'   => \SHOP_LOG_LEVEL,
            'syncLogLevel'   => \SYNC_LOG_LEVEL,
            'adminLogLevel'  => \ADMIN_LOG_LEVEL,
            'smartyLogLevel' => \SMARTY_LOG_LEVEL,
        ];
    }
}
