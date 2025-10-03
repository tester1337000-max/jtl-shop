<?php

declare(strict_types=1);

namespace JTL\ServiceReport\Report;

class Factory
{
    /**
     * @return ReportInterface[]
     */
    public function getReports(): array
    {
        return [
            new ApacheModules(),
            new Cache(),
            new DatabaseStatistics(),
            new DatabaseStatus(),
            new DatabaseTableStatus(),
            new DatabaseVariables(),
            new PHP(),
            new Server(),
            new ShopBasics(),
            new ShopConfig(),
            new ShopErrorLog(),
            new ShopStatus(),
            new ShopTemplate(),
            new Versions(),
            new Plugins(),
        ];
    }
}
