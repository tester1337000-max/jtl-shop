<?php

declare(strict_types=1);

namespace JTL\ServiceReport\Report;

use JTL\Shop;

class DatabaseVariables implements ReportInterface
{
    /**
     * @return array<string, string>
     */
    public function getData(): array
    {
        $res = [];
        foreach (Shop::Container()->getDB()->getObjects('SHOW VARIABLES') as $item) {
            $res[$item->Variable_name] = $item->Value;
        }

        return $res;
    }
}
