<?php

declare(strict_types=1);

namespace JTL\ServiceReport\Report;

use JTL\Shop;

class ShopErrorLog implements ReportInterface
{
    /**
     * @return array{errors: array<\stdClass>, warnings: array<\stdClass>}
     */
    public function getData(): array
    {
        return [
            'errors'   => Shop::Container()->getDB()->getObjects(
                'SELECT cKey AS channel, cLog AS msg, dErstellt AS date
                    FROM tjtllog
                    WHERE nLevel = 400
                    ORDER BY dErstellt DESC
                    LIMIT 100'
            ),
            'warnings' => Shop::Container()->getDB()->getObjects(
                'SELECT cKey AS channel, cLog AS msg, dErstellt AS date
                    FROM tjtllog
                    WHERE nLevel = 300
                    ORDER BY dErstellt DESC
                    LIMIT 100'
            )
        ];
    }
}
