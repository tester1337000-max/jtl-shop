<?php

declare(strict_types=1);

namespace JTL\Export\Exporter;

use JTL\Cron\QueueEntry;
use JTL\DB\DbInterface;
use JTL\Export\ExportException;
use JTL\Export\ExportWriterInterface;
use JTL\Export\Model;
use JTL\Smarty\ExportSmarty;
use Psr\Log\LoggerInterface;

/**
 * Interface ExporterInterface
 * @package JTL\Export\Exporter
 */
interface ExporterInterface
{
    /**
     * @param int   $exportID
     * @param Model $model
     * @param bool  $isAsync
     * @param bool  $isCron
     * @return mixed
     */
    public function initialize(int $exportID, Model $model, bool $isAsync, bool $isCron);

    /**
     * @param QueueEntry $queueEntry
     * @param int|null   $max
     * @return mixed
     * @throws ExportException
     */
    public function start(QueueEntry $queueEntry, ?int $max = null);

    /**
     * @return ExportSmarty
     */
    public function getSmarty(): ExportSmarty;

    /**
     * @param ExportSmarty $smarty
     */
    public function setSmarty(ExportSmarty $smarty): void;

    /**
     * @return Model
     */
    public function getModel(): Model;

    /**
     * @param Model $model
     */
    public function setModel(Model $model): void;

    /**
     * @return DbInterface
     */
    public function getDB(): DbInterface;

    /**
     * @param DbInterface $db
     */
    public function setDB(DbInterface $db): void;

    /**
     * @return ExportWriterInterface
     */
    public function getWriter(): ExportWriterInterface;

    /**
     * @param ExportWriterInterface|null $writer
     */
    public function setWriter(?ExportWriterInterface $writer): void;

    /**
     * @return float|null
     */
    public function getStartedAt(): ?float;

    /**
     * @param float $startedAt
     */
    public function setStartedAt(float $startedAt): void;

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void;

    /**
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface;

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array;

    /**
     * @param array<string, mixed> $config
     */
    public function setConfig(array $config): void;

    /**
     * @return QueueEntry|null
     */
    public function getQueue(): ?QueueEntry;

    /**
     * @param QueueEntry $queue
     */
    public function setQueue(QueueEntry $queue): void;

    /**
     * @return bool
     */
    public function isFinished(): bool;

    /**
     * @return int
     */
    public function getTotalCount(): int;
}
