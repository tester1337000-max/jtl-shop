<?php

/** @noinspection PhpDeprecationInspection */

declare(strict_types=1);

namespace JTL\Export;

use DateTime;
use JTL\Cache\JTLCacheInterface;
use JTL\Cron\QueueEntry;
use JTL\DB\DbInterface;
use JTL\Router\Route;
use JTL\Session\Backend;
use JTL\Shop;
use JTL\Smarty\ExportSmarty;
use Psr\Log\LoggerInterface;

/**
 * Class AbstractExporter
 * @package JTL\Export
 * @deprecated since 5.3.0
 */
abstract class AbstractExporter implements ExporterInterface
{
    /**
     * @var array<string, mixed>
     */
    protected array $config = [];

    protected ExportSmarty $smarty;

    protected ?QueueEntry $queue;

    protected ?Model $model;

    protected float $startedAt;

    public function __construct(
        protected DbInterface $db,
        protected LoggerInterface $logger,
        protected JTLCacheInterface $cache,
        protected ?ExportWriterInterface $writer = null
    ) {
    }

    /**
     * @return class-string<ExportWriterInterface>
     */
    public function getFileWriterClass(): string
    {
        return FileWriter::class;
    }

    public function syncReturn(AsyncCallback $cb): void
    {
        \header(
            'Location: ' . Shop::getAdminURL() . '/' . Route::EXPORT
            . '?action=exported&token=' . Backend::get('jtl_token')
            . '&kExportformat=' . $this->getModel()->getId()
            . '&max=' . $cb->getProductCount()
            . '&hasError=' . (int)($cb->getError() !== '' && $cb->getError() !== null)
        );
    }

    public function syncContinue(AsyncCallback $cb): void
    {
        \header(
            'Location: ' . Shop::getAdminURL() . '/' . Route::EXPORT_START
            . '?e=' . $this->getQueue()->jobQueueID
            . '&back=admin&token=' . Backend::get('jtl_token')
            . '&max=' . $cb->getProductCount()
        );
    }

    public function getExportSQL(bool $countOnly = false): string
    {
        $join  = '';
        $limit = '';
        $where = match ($this->getModel()->getVarcombOption()) {
            2       => ' AND kVaterArtikel = 0',
            3       => ' AND (tartikel.nIstVater != 1 OR tartikel.kEigenschaftKombi > 0)',
            default => '',
        };
        if ($this->config['exportformate_lager_ueber_null'] === 'Y') {
            $where .= " AND (NOT (tartikel.fLagerbestand <= 0 AND tartikel.cLagerBeachten = 'Y'))";
        } elseif ($this->config['exportformate_lager_ueber_null'] === 'O') {
            $where .= " AND (NOT (tartikel.fLagerbestand <= 0 AND tartikel.cLagerBeachten = 'Y') 
                            OR tartikel.cLagerKleinerNull = 'Y')";
        }

        if ($this->config['exportformate_preis_ueber_null'] === 'Y') {
            $join .= ' JOIN tpreis ON tpreis.kArtikel = tartikel.kArtikel
                          AND tpreis.kKundengruppe = ' . $this->getModel()->getCustomerGroupID() . '
                       JOIN tpreisdetail ON tpreisdetail.kPreis = tpreis.kPreis
                          AND tpreisdetail.nAnzahlAb = 0
                          AND tpreisdetail.fVKNetto > 0';
        }

        if ($this->config['exportformate_beschreibung'] === 'Y') {
            $where .= " AND tartikel.cBeschreibung != ''";
        }

        $condition = 'AND (tartikel.dErscheinungsdatum IS NULL OR NOT (DATE(tartikel.dErscheinungsdatum) > CURDATE()))';
        $conf      = Shop::getSettings([\CONF_GLOBAL]);
        if (($conf['global']['global_erscheinende_kaeuflich'] ?? 'N') === 'Y') {
            $condition = "AND (
                tartikel.dErscheinungsdatum IS NULL 
                OR NOT (DATE(tartikel.dErscheinungsdatum) > CURDATE())
                OR (
                    DATE(tartikel.dErscheinungsdatum) > CURDATE()
                    AND (tartikel.cLagerBeachten = 'N' 
                        OR tartikel.fLagerbestand > 0 OR tartikel.cLagerKleinerNull = 'Y')
                )
            )";
        }

        if ($countOnly === true) {
            $select = 'COUNT(*) AS nAnzahl';
        } else {
            $select = 'tartikel.kArtikel';
            $limit  = ' ORDER BY tartikel.kArtikel';
            if ($this->queue !== null) {
                $limit     .= ' LIMIT ' . $this->queue->taskLimit;
                $condition .= ' AND tartikel.kArtikel > ' . $this->queue->lastProductID;
            }
        }

        return 'SELECT ' . $select . "
            FROM tartikel
            LEFT JOIN tartikelattribut ON tartikelattribut.kArtikel = tartikel.kArtikel
                AND tartikelattribut.cName = '" . \FKT_ATTRIBUT_KEINE_PREISSUCHMASCHINEN . "'
            " . $join . '
            LEFT JOIN tartikelsichtbarkeit ON tartikelsichtbarkeit.kArtikel = tartikel.kArtikel
                AND tartikelsichtbarkeit.kKundengruppe = ' . $this->getModel()->getCustomerGroupID() . '
            WHERE tartikelattribut.kArtikelAttribut IS NULL' . $where . '
                AND tartikelsichtbarkeit.kArtikel IS NULL ' . $condition . $limit;
    }

    /**
     * @inheritdoc
     */
    public function init(int $exportID): void
    {
        $this->startedAt = \microtime(true);
        $this->initConfig($exportID);
    }

    protected function initConfig(int $exportID): void
    {
        $confObj = $this->db->selectAll(
            'texportformateinstellungen',
            'kExportformat',
            $exportID
        );
        foreach ($confObj as $conf) {
            $this->config[$conf->cName] = $conf->cWert;
        }
        $this->config['exportformate_lager_ueber_null'] = $this->config['exportformate_lager_ueber_null'] ?? 'N';
        $this->config['exportformate_preis_ueber_null'] = $this->config['exportformate_preis_ueber_null'] ?? 'N';
        $this->config['exportformate_beschreibung']     = $this->config['exportformate_beschreibung'] ?? 'N';
        $this->config['exportformate_quot']             = $this->config['exportformate_quot'] ?? 'N';
        $this->config['exportformate_equot']            = $this->config['exportformate_equot'] ?? 'N';
        $this->config['exportformate_semikolon']        = $this->config['exportformate_semikolon'] ?? 'N';
        $this->config['exportformate_line_ending']      = $this->config['exportformate_line_ending'] ?? 'LF';
    }

    /**
     * @inheritdoc
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @inheritdoc
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * @inheritdoc
     */
    public function setQueue(QueueEntry $queue): void
    {
        $this->queue = $queue;
    }

    /**
     * @inheritdoc
     */
    public function getQueue(): QueueEntry
    {
        return $this->queue ?? throw new \RuntimeException('Queue not set');
    }

    /**
     * @inheritdoc
     */
    public function getSmarty(): ExportSmarty
    {
        return $this->smarty;
    }

    /**
     * @inheritdoc
     */
    public function setSmarty(ExportSmarty $smarty): void
    {
        $this->smarty = $smarty;
    }

    /**
     * @inheritdoc
     */
    public function getModel(): Model
    {
        return $this->model ?? throw new \RuntimeException('Model not set');
    }

    /**
     * @inheritdoc
     */
    public function setModel(Model $model): void
    {
        $this->model = $model;
    }

    /**
     * @inheritdoc
     */
    public function getDB(): DbInterface
    {
        return $this->db;
    }

    /**
     * @inheritdoc
     */
    public function setDB(DbInterface $db): void
    {
        $this->db = $db;
    }

    public function getCache(): JTLCacheInterface
    {
        return $this->cache;
    }

    public function setCache(JTLCacheInterface $cache): void
    {
        $this->cache = $cache;
    }

    /**
     * @inheritdoc
     */
    public function getWriter(): ExportWriterInterface
    {
        return $this->writer ?? throw new \RuntimeException('Writer not set');
    }

    /**
     * @inheritdoc
     */
    public function setWriter(?ExportWriterInterface $writer): void
    {
        $this->writer = $writer;
    }

    /**
     * @inheritdoc
     */
    public function getStartedAt(): ?float
    {
        return $this->startedAt;
    }

    /**
     * @inheritdoc
     */
    public function setStartedAt(float $startedAt): void
    {
        $this->startedAt = $startedAt;
    }

    /**
     * @inheritdoc
     */
    public function update(): int
    {
        return 0;
    }

    /**
     * @inheritdoc
     */
    public function setZuletztErstellt(DateTime|string $lastCreated): ExporterInterface
    {
        return $this;
    }
}
