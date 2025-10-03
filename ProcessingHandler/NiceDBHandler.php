<?php

declare(strict_types=1);

namespace JTL\ProcessingHandler;

use JTL\DB\DbInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Class NiceDBHandler
 * @package JTL\ProcessingHandler
 */
class NiceDBHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly DbInterface $db,
        int|string|Level $level = Level::Debug,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $context = isset($record->context[0]) && \is_numeric($record->context[0])
            ? (int)$record->context[0]
            : 0;
        if (!$this->db->isConnected()) {
            $this->db->reInit();
        }

        $this->db->insert(
            'tjtllog',
            (object)[
                'cKey'      => $record->context['channel'] ?? $record->channel,
                'nLevel'    => $record->level->value,
                'cLog'      => $record->formatted,
                'kKey'      => $context,
                'dErstellt' => $record->datetime->format('Y-m-d H:i:s'),
            ]
        );
    }
}
