<?php

declare(strict_types=1);

namespace JTL\ServiceReport\Report;

use JTL\Shop;

use function Functional\map;
use function Functional\reindex;

class DatabaseStatistics implements ReportInterface
{
    /**
     * @return array<string, string>
     */
    public function getData(): array
    {
        return map(
            reindex(
                $this->getMySQLStats(),
                fn($data) => $data['key']
            ),
            fn($item) => $item['value']
        );
    }

    /**
     * @return array<int, array{key: string, value: string}>
     */
    private function getMySQLStats(): array
    {
        $db = Shop::Container()->getDB();

        $lines = \array_map(static function (string $v): array {
            [$key, $value] = \explode(':', $v, 2);

            return ['key' => \trim($key), 'value' => \trim($value)];
        }, \explode('  ', $db->getServerStats()));

        return \array_merge([['key' => 'Version', 'value' => $db->getServerInfo()]], $lines);
    }
}
