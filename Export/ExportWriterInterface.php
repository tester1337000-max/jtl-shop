<?php

declare(strict_types=1);

namespace JTL\Export;

use Exception;
use JTL\Smarty\ExportSmarty;

/**
 * Interface ExportWriterInterface
 * @package JTL\Export
 */
interface ExportWriterInterface
{
    /**
     * @param Model                $model
     * @param array<string, mixed> $config
     * @param ExportSmarty|null    $smarty
     */
    public function __construct(Model $model, array $config, ?ExportSmarty $smarty = null);

    /**
     * @throws Exception
     */
    public function start(): void;

    /**
     * @return int
     */
    public function writeHeader(): int;

    /**
     * @return int
     */
    public function writeFooter(): int;

    /**
     * @param string $data
     * @return int
     */
    public function writeContent(string $data): int;

    /**
     * @return string
     */
    public function getNewLine(): string;

    /**
     * @return bool
     */
    public function close(): bool;

    /**
     * @return bool
     */
    public function finish(): bool;

    public function deleteOldExports(): void;

    public function deleteOldTempFile(): void;

    /**
     * @return ExportWriterInterface
     */
    public function split(): ExportWriterInterface;
}
