<?php

declare(strict_types=1);

namespace JTL\Export\Exporter;

use Exception;
use JTL\Cache\JTLCacheInterface;
use JTL\Cron\QueueEntry;
use JTL\Customer\CustomerGroup;
use JTL\DB\DbInterface;
use JTL\Export\AsyncCallback;
use JTL\Export\ExportWriterInterface;
use JTL\Export\FileWriter;
use JTL\Export\Model;
use JTL\Export\Product;
use JTL\Export\Session;
use JTL\Router\Route;
use JTL\Session\Backend;
use JTL\Session\Frontend;
use JTL\Shop;
use JTL\Smarty\ExportSmarty;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Class AbstractExporter
 * @package JTL\Export\Exporter
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

    protected int $exportID;

    protected int $max = 0;

    protected bool $isAsync = false;

    protected bool $isCron = false;

    protected bool $started = false;

    protected bool $finishedInThisRun = false;

    protected Session $pseudoSession;

    protected int $cacheHits = 0;

    protected int $cacheMisses = 0;

    protected string $shopURL;

    public function __construct(
        protected DbInterface $db,
        protected LoggerInterface $logger,
        protected JTLCacheInterface $cache,
        protected ?ExportWriterInterface $writer = null
    ) {
        $this->shopURL = Shop::getURL();
    }

    public function initialize(int $exportID, Model $model, bool $isAsync, bool $isCron): void
    {
        $this->startedAt = \microtime(true);
        $this->setExportID($exportID);
        $this->setModel($model);
        $this->initConfig();
        $this->isCron  = $isCron;
        $this->isAsync = $isAsync;

        $this->pseudoSession = new Session();
        $this->pseudoSession->initSession($model, $this->db, $this->config);
        $this->initSmarty();

        $fileWriterClass = $this->getFileWriterClass();
        $this->writer    = $this->writer ?? new $fileWriterClass($this->model, $this->config, $this->smarty);
    }

    protected function initSmarty(): void
    {
        $this->smarty = new ExportSmarty($this->db);
        $this->smarty->assign('URL_SHOP', $this->shopURL)
            ->assign('ShopURL', $this->shopURL)
            ->assign('Waehrung', Frontend::getCurrency())
            ->assign('Einstellungen', $this->getConfig());
    }

    protected function initConfig(): void
    {
        $confObj = $this->db->selectAll(
            'texportformateinstellungen',
            'kExportformat',
            $this->exportID
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
     * @param QueueEntry $queueEntry
     * @param int|null   $max
     * @return bool|ResponseInterface
     */
    public function start(QueueEntry $queueEntry, ?int $max = null)
    {
        $this->prepare($queueEntry, $max);
        if ($this->getQueue()->tasksExecuted === 0) {
            $this->getWriter()->deleteOldTempFile();
        }
        try {
            $this->getWriter()->start();
        } catch (Exception $e) {
            $result = $this->handleException($e);
            if ($result !== null) {
                return $result;
            }
        }
        $customerGroup   = CustomerGroup::getByID($this->getModel()->getCustomerGroupID());
        $customerGroupID = $customerGroup->getID();

        $this->logger->notice(
            'Starting exportformat "{nme}" for language {lid}, currency {cur}'
            . ' and customer group {cid} with caching {cie} - {ex}/{max} products exported',
            [
                'nme' => $this->getModel()->getName(),
                'lid' => $this->getModel()->getLanguageID(),
                'cur' => $this->getModel()->getCurrency()->getName(),
                'cid' => $customerGroupID,
                'ex'  => $queueEntry->tasksExecuted,
                'max' => $this->max,
                'cie' => ($this->getCache()->isActive() && $this->getModel()->getUseCache())
                    ? 'enabled'
                    : 'disabled'
            ]
        );
        if ($this->getQueue()->tasksExecuted === 0) {
            $this->getWriter()->writeHeader();
        }
        $output = $this->export();
        if (\mb_strlen($output) > 0) {
            $this->getWriter()->writeContent($output);
        }
        $cb     = $this->finishRun();
        $result = $this->getNextStep($cb);
        if ($result !== null) {
            return $result;
        }
        $this->pseudoSession->restoreSession();

        return !$this->started;
    }

    protected function prepare(QueueEntry $queueEntry, ?int $max = null): void
    {
        $this->started = false;
        $this->setQueue($queueEntry);
        $max = $max ?? $this->getTotalCount();
        \executeHook(\HOOK_EXPORT_START, [
            'exporter' => $this,
            'exportID' => $this->exportID,
            'isAsync'  => $this->isAsync,
            'isCron'   => $this->isCron,
            'max'      => &$max
        ]);
        $this->max = $max;
    }

    /**
     * @param AsyncCallback $cb
     * @return ResponseInterface|null|void
     */
    public function getNextStep(AsyncCallback $cb)
    {
        if ($this->started === true) {
            $this->getWriter()->close();
            if ($this->isAsync) {
                return $cb->getResponse();
            }

            return $this->syncContinue($cb);
        }
        $this->finish($cb);

        return null;
    }

    /**
     * @param Exception $e
     * @return false|void
     */
    public function handleException(Exception $e)
    {
        $this->logger->warning($e->getMessage());
        if (!$this->isAsync) {
            return false;
        }
        $cb = new AsyncCallback();
        $cb->setExportID($this->getModel()->getId())
            ->setQueueID($this->getQueue()->jobQueueID)
            ->setError($e->getMessage());
        $this->finish($cb);
        exit;
    }

    protected function export(): string
    {
        if ($this->model === null || $this->queue === null) {
            return '';
        }
        $output           = '';
        $fallback         = \str_contains($this->model->getContent(), '->oKategorie_arr');
        $options          = Product::getExportOptions();
        $imageBaseURL     = Shop::getImageBaseURL();
        $res              = $this->db->getPDOStatement($this->getExportSQL());
        $languageID       = $this->model->getLanguageID();
        $currency         = $this->model->getCurrency();
        $currencyID       = $currency->getID();
        $customerGroup    = CustomerGroup::getByID($this->model->getCustomerGroupID());
        $customerGroupID  = $customerGroup->getID();
        $conversionFactor = $this->pseudoSession->getCurrency()->getConversionFactor();
        $campaignValue    = $this->model->getCampaignValue();
        $noCache          = !$this->model->getUseCache();
        while (($productData = $res->fetchObject()) !== false) {
            $product = new Product($this->db, $customerGroup, $currency, $this->cache);
            $product->fuelleArtikel(
                (int)$productData->kArtikel,
                $options,
                $customerGroupID,
                $languageID,
                $noCache
            );
            if ($product->kArtikel <= 0) {
                continue;
            }
            $product->kSprache                 = $languageID;
            $product->kKundengruppe            = $customerGroupID;
            $product->kWaehrung                = $currencyID;
            $product->campaignValue            = $campaignValue;
            $product->currencyConversionFactor = $conversionFactor;

            $this->started = true;
            ++$this->getQueue()->tasksExecuted;
            $this->getQueue()->lastProductID = $product->kArtikel;
            if ($product->cacheHit === true) {
                ++$this->cacheHits;
            } else {
                ++$this->cacheMisses;
            }
            $product = $product->augmentProduct($this->config, $this->model);
            $product->addCategoryData($fallback);
            $product->Kategoriepfad = $product->Kategorie?->getCategoryPathString($languageID);
            $product->cDeeplink     = $product->cURLFull;
            $product->Artikelbild   = !empty($product->Bilder[0]->cPfadGross)
                ? $imageBaseURL . $product->Bilder[0]->cPfadGross
                : '';
            \executeHook(\HOOK_EXPORT_PRE_RENDER, [
                'product'  => $product,
                'exporter' => $this,
                'exportID' => $this->exportID
            ]);

            $_out = $this->renderProduct($product);
            if (!empty($_out)) {
                $output .= $_out . $this->getWriter()->getNewLine();
            }
            \executeHook(\HOOK_DO_EXPORT_OUTPUT_FETCHED);
            $this->progress($this->getQueue());
        }
        $this->finishedInThisRun = $this->getQueue()->tasksExecuted >= $this->max;

        return $output;
    }

    protected function renderProduct(Product $product): string
    {
        return $this->smarty->assign('Artikel', $product)->fetch('db:' . $this->getModel()->getId());
    }

    protected function progress(QueueEntry $queueEntry): void
    {
        // max. 10 status updates per run
        if (($queueEntry->tasksExecuted % \max(\round($queueEntry->taskLimit / 10), 10)) !== 0) {
            return;
        }
        $this->logger->notice(
            '{start}/{max} products exported, {hits} cache hits, {misses} cache misses',
            [
                'start'  => $queueEntry->tasksExecuted,
                'max'    => $this->max,
                'hits'   => $this->cacheHits,
                'misses' => $this->cacheMisses
            ]
        );
    }

    protected function finish(AsyncCallback $cb)
    {
        $this->db->queryPrepared(
            'UPDATE texportformat 
                SET dZuletztErstellt = NOW() 
                WHERE kExportformat = :eid',
            ['eid' => $this->getModel()->getId()]
        );
        $this->db->delete('texportqueue', 'kExportqueue', $this->getQueue()->foreignKeyID);
        $this->getWriter()->writeFooter();
        if ($this->getWriter()->finish()) {
            try {
                $this->getWriter()->split();
            } catch (Exception $e) {
                $cb->setError($e->getMessage());
            }
            $cb->setMessage(
                \sprintf(
                    \__('Successfully created export file %s'),
                    $this->getModel()->getSanitizedFilepath()
                )
            );
        } else {
            try {
                $errorMessage = \sprintf(
                    \__('Cannot create export file %s. Missing write permissions?'),
                    $this->getModel()->getSanitizedFilepath()
                );
            } catch (Exception $e) {
                $errorMessage = $e->getMessage();
            }
            $cb->setError($errorMessage);
        }
    }

    protected function finishRun(): AsyncCallback
    {
        require \PFAD_ROOT . \PFAD_INCLUDES . 'profiler_inc.php';
        $cb = new AsyncCallback();
        if ($this->queue === null || $this->model === null) {
            return $cb;
        }
        $cb->setExportID($this->model->getId())
            ->setTasksExecuted($this->queue->tasksExecuted)
            ->setQueueID($this->queue->jobQueueID)
            ->setProductCount($this->max)
            ->setLastProductID($this->queue->lastProductID)
            ->setIsFinished(false)
            ->setIsFirst($this->queue->tasksExecuted === 0)
            ->setCacheHits($this->cacheHits)
            ->setCacheMisses($this->cacheMisses)
            ->setError('');
        if ($this->started === true) {
            // One or more products have been exported
            $this->db->queryPrepared(
                'UPDATE texportqueue SET
                nLimit_n       = nLimit_n + :nLimitM,
                nLastArticleID = :nLastArticleID
                WHERE kExportqueue = :kExportqueue',
                [
                    'nLimitM'        => $this->queue->taskLimit,
                    'nLastArticleID' => $this->queue->lastProductID,
                    'kExportqueue'   => $this->queue->jobQueueID,
                ]
            );
        }

        return $cb;
    }

    public function syncContinue(AsyncCallback $cb): RedirectResponse
    {
        return new RedirectResponse(
            \sprintf(
                '%s/%s?e=%d&back=admin&token=%s&max=%d',
                Shop::getAdminURL(),
                Route::EXPORT_START,
                $this->getQueue()->jobQueueID,
                Backend::get('jtl_token'),
                $cb->getProductCount()
            ),
            302
        );
    }

    public function syncReturn(AsyncCallback $cb): RedirectResponse
    {
        return new RedirectResponse(
            \sprintf(
                '%s/%s?action=exported&token=%s&kExportformat=%d&max=%d&hasError=%d',
                Shop::getAdminURL(),
                Route::EXPORT,
                Backend::get('jtl_token'),
                $this->getModel()->getId(),
                $cb->getProductCount(),
                (int)($cb->getError() !== '' && $cb->getError() !== null)
            ),
            302
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
     * @return class-string<ExportWriterInterface>
     */
    public function getFileWriterClass(): string
    {
        return FileWriter::class;
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
        return $this->queue ?? throw new \RuntimeException('Queue not initialized');
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
        return $this->model ?? throw new \RuntimeException('Model not initialized');
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
        return $this->writer ?? throw new \RuntimeException('Writer not initialized');
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

    public function update(): int
    {
        return 0;
    }

    public function setZuletztErstellt(): self
    {
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getTotalCount(): int
    {
        return (int)($this->db->getSingleObject($this->getExportSQL(true))->nAnzahl ?? 0);
    }

    public function getExportID(): int
    {
        return $this->exportID;
    }

    public function setExportID(int $exportID): void
    {
        $this->exportID = $exportID;
    }

    /**
     * @inheritdoc
     */
    public function isFinished(): bool
    {
        return $this->finishedInThisRun;
    }
}
