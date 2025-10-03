<?php

/** @noinspection PhpDeprecationInspection */

declare(strict_types=1);

namespace JTL\Export;

use DateTime;
use Exception;
use InvalidArgumentException;
use JTL\Cron\QueueEntry;
use JTL\DB\DbInterface;
use JTL\Smarty\ExportSmarty;
use Psr\Log\LoggerInterface;

/**
 * Interface ExporterInterface
 * @package JTL\Export
 * @deprecated since 5.3.0
 * @noinspection PhpDeprecationInspection
 */
interface ExporterInterface
{
    /**
     * @param int $exportID
     */
    public function init(int $exportID): void;

    /**
     * @return int
     */
    public function getExportProductCount(): int;

    /**
     * compatibility with jtl_google_shopping only
     * @param DateTime|string $lastCreated
     * @return ExporterInterface
     */
    public function setZuletztErstellt(DateTime|string $lastCreated): ExporterInterface;

    /**
     *  compatibility with jtl_google_shopping only
     * @return int
     * @throws Exception
     */
    public function update(): int;

    /**
     * @param int        $exportID
     * @param QueueEntry $queueEntry
     * @param bool       $isAsync
     * @param bool       $back
     * @param bool       $isCron
     * @param int|null   $max
     * @return bool
     * @throws InvalidArgumentException
     */
    public function startExport(
        int $exportID,
        QueueEntry $queueEntry,
        bool $isAsync = false,
        bool $back = false,
        bool $isCron = false,
        ?int $max = null
    ): bool;

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
}
