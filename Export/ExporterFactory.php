<?php

/** @noinspection PhpDeprecationInspection */

declare(strict_types=1);

namespace JTL\Export;

use Exception;
use InvalidArgumentException;
use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use Psr\Log\LoggerInterface;

/**
 * Class ExporterFactory
 * @package JTL\Export
 * @deprecated since 5.3.0
 */
readonly class ExporterFactory
{
    public function __construct(
        private DbInterface $db,
        private LoggerInterface $logger,
        private JTLCacheInterface $cache,
        private ?ExportWriterInterface $writer = null
    ) {
    }

    public function getExporter(int $exportID): ExporterInterface
    {
        $exporter = new FormatExporter($this->db, $this->logger, $this->cache, $this->writer);
        try {
            $model = Model::load(['id' => $exportID], $this->db, Model::ON_NOTEXISTS_FAIL);
        } catch (Exception) {
            throw new InvalidArgumentException('Cannot find export with id ' . $exportID);
        }

        \executeHook(\HOOK_EXPORT_FACTORY_GET_EXPORTER, [
            'exportID' => $exportID,
            'exporter' => &$exporter,
            'model'    => $model
        ]);
        $exporter->setDB($this->db);
        $exporter->setCache($this->cache);
        $exporter->setLogger($this->logger);
        $exporter->setWriter($this->writer);
        $exporter->setModel($model);

        return $exporter;
    }
}
