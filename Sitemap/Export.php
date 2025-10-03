<?php

declare(strict_types=1);

namespace JTL\Sitemap;

use JTL\Customer\CustomerGroup;
use JTL\DB\DbInterface;
use JTL\Helpers\Request;
use JTL\Helpers\Tax;
use JTL\Language\LanguageHelper;
use JTL\Language\LanguageModel;
use JTL\Shop;
use JTL\Sitemap\Factories\FactoryInterface;
use JTL\Sitemap\ItemRenderers\RendererInterface;
use JTL\Sitemap\Items\ItemInterface;
use JTL\Sitemap\SchemaRenderers\SchemaRendererInterface;
use Psr\Log\LoggerInterface;
use stdClass;

use function Functional\first;
use function Functional\some;

/**
 * Class Export
 * @package JTL\Sitemap
 */
final class Export
{
    public const SITEMAP_URL_GOOGLE = 'https://www.google.com/webmasters/tools/ping?sitemap=';

    public const SITEMAP_URL_BING = 'https://www.bing.com/ping?sitemap=';

    private const EXPORT_DIR = \PFAD_ROOT . \PFAD_EXPORT;

    private string $baseURL;

    private string $baseImageURL;

    /**
     * @var string[]
     */
    private static array $blockedURLs = [
        'navi.php',
        'suche.php',
        'jtl.php',
        'pass.php',
        'registrieren.php',
        'warenkorb.php',
    ];

    private bool $gzip;

    private int $itemLimit = \SITEMAP_ITEMS_LIMIT;

    private string $fileName = 'sitemap_';

    private ?string $indexFileName = 'sitemap_index.xml';

    /**
     * @param array<mixed> $config
     */
    public function __construct(
        private DbInterface $db,
        private LoggerInterface $logger,
        private RendererInterface $renderer,
        private SchemaRendererInterface $schemaRenderer,
        private array $config
    ) {
        $this->baseImageURL = Shop::getImageBaseURL();
        $this->baseURL      = Shop::getURL() . '/';
        $this->gzip         = \function_exists('gzopen');
        \executeHook(\HOOK_SITEMAP_EXPORT_INIT, ['instance' => $this]);
        $this->schemaRenderer->setConfig($config);
        $this->renderer->setConfig($config);
    }

    /**
     * @param int[]              $customerGroupIDs
     * @param LanguageModel[]    $languages
     * @param FactoryInterface[] $factories
     */
    public function generate(array $customerGroupIDs, array $languages, array $factories): void
    {
        $this->logger->debug('Sitemap wird erstellt');
        $timeStart  = \microtime(true);
        $fileNumber = 0;
        $itemCount  = 1;
        $urlCounts  = [0 => 0];
        $markup     = '';
        $this->setSessionData($customerGroupIDs);
        $this->deleteFiles();
        \executeHook(\HOOK_SITEMAP_EXPORT_GENERATE, [
            'factories' => &$factories,
            'instance'  => $this
        ]);
        foreach ($factories as $factory) {
            foreach ($factory->getCollection($languages, $customerGroupIDs) as $item) {
                if ($item === null) {
                    break;
                }
                /** @var ItemInterface $item */
                if ($itemCount > $this->itemLimit) {
                    $itemCount = 1;
                    $this->buildFile($fileNumber, $markup);
                    ++$fileNumber;
                    $urlCounts[$fileNumber] = 0;
                    $markup                 = '';
                }
                if (!$this->isURLBlocked($item->getLocation())) {
                    $markup .= $this->renderer->renderItem($item);
                    ++$itemCount;
                    ++$urlCounts[$fileNumber];
                }
            }
        }
        $markup .= $this->renderer->flush();
        $this->buildFile($fileNumber, $markup);
        $this->writeIndexFile($fileNumber);
        $timeTotal = \microtime(true) - $timeStart;
        \executeHook(\HOOK_SITEMAP_EXPORT_GENERATED, [
            'instance'       => $this,
            'nAnzahlURL_arr' => $urlCounts,
            'totalTime'      => $timeTotal
        ]);
        $this->buildReport($urlCounts, $timeTotal);
        $this->ping();
    }

    /**
     * @param int[] $customerGroupIDs
     */
    private function setSessionData(array $customerGroupIDs): void
    {
        $defaultLang             = LanguageHelper::getDefaultLanguage();
        $defaultLangID           = $defaultLang->getId();
        $_SESSION['kSprache']    = $defaultLangID;
        $_SESSION['cISOSprache'] = $defaultLang->getCode();
        Tax::setTaxRates();
        if (!isset($_SESSION['Kundengruppe'])) {
            $_SESSION['Kundengruppe'] = new CustomerGroup(0, $this->db);
        }
        $_SESSION['Kundengruppe']->setID(first($customerGroupIDs));
    }

    private function writeIndexFile(int $fileNumber): void
    {
        if ($this->indexFileName === null) {
            return;
        }
        $indexFile = self::EXPORT_DIR . $this->indexFileName;
        if (!\is_writable($indexFile) && \is_file($indexFile)) {
            return;
        }
        $extension    = $this->gzip ? '.xml.gz' : '.xml';
        $sitemapFiles = [];
        for ($i = 0; $i <= $fileNumber; ++$i) {
            $sitemapFiles[] = $this->baseURL . \PFAD_EXPORT . $this->fileName . $i . $extension;
        }
        $content = $this->schemaRenderer->buildIndex($sitemapFiles);
        if (\mb_strlen($content) > 0) {
            $handle = \fopen($indexFile, 'wb+');
            if ($handle === false) {
                return;
            }
            \fwrite($handle, $content);
            \fclose($handle);
        }
    }

    private function ping(): void
    {
        if ($this->config['sitemap']['sitemap_google_ping'] !== 'Y') {
            return;
        }
        $indexURL = \urlencode($this->baseURL . $this->indexFileName);
        foreach ([self::SITEMAP_URL_GOOGLE, self::SITEMAP_URL_BING] as $url) {
            $status = Request::http_get_status($url . $indexURL);
            if ($status !== 200) {
                $this->logger->notice('Sitemap ping to ' . $url . ' failed with status ' . $status);
            }
        }
    }

    private function isURLBlocked(string $url): bool
    {
        return some(self::$blockedURLs, fn(string $e): bool => \str_contains($url, $e));
    }

    private function buildFile(int $fileNumber, string $data): bool
    {
        if (empty($data)) {
            return false;
        }
        $fileName = self::EXPORT_DIR . $this->fileName . $fileNumber . '.xml';
        $handle   = $this->gzip
            ? \gzopen($fileName . '.gz', 'w9')
            : \fopen($fileName, 'wb+');
        if ($handle === false) {
            return false;
        }
        \fwrite(
            $handle,
            $this->schemaRenderer->buildHeader() . $data . $this->schemaRenderer->buildFooter()
        );
        \fclose($handle);

        return true;
    }

    private function deleteFiles(): bool
    {
        if (!\is_dir(self::EXPORT_DIR) || ($dh = \opendir(self::EXPORT_DIR)) === false) {
            return false;
        }
        while (($file = \readdir($dh)) !== false) {
            if ($file === $this->indexFileName || \str_contains($file, $this->fileName)) {
                \unlink(self::EXPORT_DIR . $file);
            }
        }
        \closedir($dh);

        return true;
    }

    /**
     * @param array<int, int> $urlCounts
     */
    private function buildReport(array $urlCounts, float $timeTotal): bool
    {
        if ($timeTotal <= 0 || \count($urlCounts) === 0) {
            return false;
        }
        $totalCount = \array_sum($urlCounts);

        $report                     = new stdClass();
        $report->nTotalURL          = $totalCount;
        $report->fVerarbeitungszeit = \number_format($timeTotal, 2);
        $report->dErstellt          = 'NOW()';

        $reportID = $this->db->insert('tsitemapreport', $report);
        foreach ($urlCounts as $i => $count) {
            if ($count <= 0) {
                continue;
            }
            $ins                 = new stdClass();
            $ins->kSitemapReport = $reportID;
            $ins->cDatei         = $this->fileName . $i . '.xml' . ($this->gzip ? '.gz' : '');
            $ins->nAnzahlURL     = $count;
            $ins->fGroesse       = \is_file(self::EXPORT_DIR . $ins->cDatei)
                ? \number_format(\filesize(self::EXPORT_DIR . $ins->cDatei) / 1024, 2)
                : 0;
            $this->db->insert('tsitemapreportfile', $ins);
        }
        $this->logger->debug(\sprintf('Sitemap erfolgreich mit %d URLs erstellt', $totalCount));

        return true;
    }

    public function getDB(): DbInterface
    {
        return $this->db;
    }

    public function setDB(DbInterface $db): void
    {
        $this->db = $db;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @return array<mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param array<mixed> $config
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getRenderer(): RendererInterface
    {
        return $this->renderer;
    }

    public function setRenderer(RendererInterface $renderer): void
    {
        $this->renderer = $renderer;
    }

    public function getSchemaRenderer(): SchemaRendererInterface
    {
        return $this->schemaRenderer;
    }

    public function setSchemaRenderer(SchemaRendererInterface $schemaRenderer): void
    {
        $this->schemaRenderer = $schemaRenderer;
    }

    public function getBaseURL(): string
    {
        return $this->baseURL;
    }

    public function setBaseURL(string $baseURL): void
    {
        $this->baseURL = $baseURL;
    }

    public function getBaseImageURL(): string
    {
        return $this->baseImageURL;
    }

    public function setBaseImageURL(string $baseImageURL): void
    {
        $this->baseImageURL = $baseImageURL;
    }

    public function getItemLimit(): int
    {
        return $this->itemLimit;
    }

    public function setItemLimit(int $itemLimit): void
    {
        $this->itemLimit = $itemLimit;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): void
    {
        $this->fileName = $fileName;
    }

    public function getIndexFileName(): ?string
    {
        return $this->indexFileName;
    }

    public function setIndexFileName(?string $indexFileName): void
    {
        $this->indexFileName = $indexFileName;
    }
}
