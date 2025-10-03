<?php

declare(strict_types=1);

namespace JTL\Debug\DataCollector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use JTL\Profiler;

/**
 * Class JTLCache
 * @package JTL\Debug\DataCollector
 */
class JTLCache extends DataCollector implements Renderable
{
    /**
     * @return array<string, string>
     */
    public function collect(): array
    {
        $vars           = Profiler::getCurrentCacheProfile();
        $getHitCount    = \count($vars['get']['success'] ?? []);
        $getMissCount   = \count($vars['get']['failure'] ?? []);
        $setHitCount    = \count($vars['set']['success'] ?? []);
        $setMissCount   = \count($vars['set']['failure'] ?? []);
        $flushHitCount  = \count($vars['flush']['success'] ?? []);
        $flushMissCount = \count($vars['flush']['failure'] ?? []);

        return [
            'Get (Hits)'        => \sprintf('%d (%d)', $getHitCount + $getMissCount, $getHitCount),
            'Set (Successes)'   => \sprintf('%d (%d)', $setHitCount + $setMissCount, $setHitCount),
            'Flush (Successes)' => \sprintf('%d (%d)', $flushHitCount + $flushMissCount, $flushHitCount),
            'Misses/Failures'   => $this->getDataFormatter()->formatVar(
                \array_merge(
                    $vars['get']['failure'] ?? [],
                    $vars['set']['failure'] ?? [],
                    $vars['flush']['failure'] ?? []
                )
            )
        ];
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'cache';
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getWidgets(): array
    {
        return [
            'cache' => [
                'icon'    => 'cogs',
                'widget'  => 'PhpDebugBar.Widgets.VariableListWidget',
                'map'     => 'cache',
                'default' => '{}'
            ]
        ];
    }
}
