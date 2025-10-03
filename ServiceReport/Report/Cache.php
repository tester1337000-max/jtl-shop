<?php

declare(strict_types=1);

namespace JTL\ServiceReport\Report;

use JTL\Shop;

class Cache implements ReportInterface
{
    /**
     * @return array{options: array<string, int|bool|string>, stats: array<string, mixed>, groups: array<string, int>}
     */
    public function getData(): array
    {
        $options = Shop::Container()->getCache()->getOptions();
        unset($options['redis_pass']);
        $stats = Shop::Container()->getCache()->getStats();

        return [
            'options' => $options,
            'stats'   => $stats,
            'groups'  => $this->getGroups()
        ];
    }

    /**
     * @return array<string, int>
     */
    private function getGroups(): array
    {
        $cache  = Shop::Container()->getCache();
        $result = [];
        foreach ($cache->getCachingGroups() as $cachingGroup) {
            $result[$cachingGroup['name']] = \count($cache->getKeysByTag([\constant($cachingGroup['name'])]));
        }

        return $result;
    }
}
