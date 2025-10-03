<?php

declare(strict_types=1);

namespace JTL\ServiceReport\Report;

use JTL\Shop;

class Versions implements ReportInterface
{
    /**
     * @return array<string, string>
     */
    public function getData(): array
    {
        return [
            'shop'     => \APPLICATION_VERSION,
            'template' => Shop::Container()->getTemplateService()->getActiveTemplate()->getVersion(),
            'php'      => \PHP_VERSION,
            'mysql'    => Shop::Container()->getDB()->getServerInfo()
        ];
    }
}
