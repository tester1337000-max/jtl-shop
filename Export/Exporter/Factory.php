<?php

declare(strict_types=1);

namespace JTL\Export\Exporter;

use Exception;
use InvalidArgumentException;
use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Export\ExportWriterInterface;
use JTL\Export\Model;
use Psr\Log\LoggerInterface;

/**
 * Class ExporterFactory
 * @package JTL\Export\Exporter
 */
readonly class Factory
{
    public function __construct(
        private DbInterface $db,
        private LoggerInterface $logger,
        private JTLCacheInterface $cache,
        private ?ExportWriterInterface $writer = null
    ) {
    }

    public function getExporter(
        int $exportID,
        bool $isAsync = false,
        bool $isCron = false
    ): ExporterInterface {
        try {
            $model = Model::load(['id' => $exportID], $this->db, Model::ON_NOTEXISTS_FAIL);
        } catch (Exception) {
            throw new InvalidArgumentException('Cannot find export with id ' . $exportID);
        }
        $isPlugin = $model->getPluginID() > 0 && \str_contains($model->getContent(), \PLUGIN_EXPORTFORMAT_CONTENTFILE);
        if ($isPlugin) {
            $exporter = new PluginExporter($this->db, $this->logger, $this->cache, $this->writer);
        } elseif ($isAsync) {
            $exporter = new AsyncExporter($this->db, $this->logger, $this->cache, $this->writer);
        } elseif ($isCron) {
            $exporter = new CronExporter($this->db, $this->logger, $this->cache, $this->writer);
        } else {
            $exporter = new SyncExporter($this->db, $this->logger, $this->cache, $this->writer);
        }
        $exporter->initialize($exportID, $model, $isAsync, $isCron);
        \executeHook(\HOOK_EXPORT_FACTORY_GET_EXPORTER, [
            'exportID' => $exportID,
            'exporter' => &$exporter,
            'model'    => $model
        ]);

        return $exporter;
    }
}
