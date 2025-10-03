<?php

declare(strict_types=1);

namespace JTL;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\xtea\XTEA;
use stdClass;

/**
 * Class Nice
 * @package JTL
 */
class Nice
{
    private static ?Nice $instance = null;

    private string $brocken;

    private string $apiKey = '';

    private string $domain = '';

    /**
     * @var int[]
     */
    private array $moduleIDs = [];

    public static function getInstance(?DbInterface $db = null, ?JTLCacheInterface $cache = null): self
    {
        return self::$instance ?? new self($db ?? Shop::Container()->getDB(), $cache ?? Shop::Container()->getCache());
    }

    protected function __construct(private readonly DbInterface $db, private readonly JTLCacheInterface $cache)
    {
        $this->brocken = $this->load();
        if (\mb_strlen($this->brocken) > 0) {
            $parts = \explode(';', $this->brocken);
            if (!empty($parts[0])) {
                $this->apiKey = $parts[0];
            }
            if (!empty($parts[1])) {
                $this->domain = \trim($parts[1]);
            }
            if (($count = \count($parts)) > 2) {
                for ($i = 2; $i < $count; $i++) {
                    $this->moduleIDs[] = (int)$parts[$i];
                }
            }
        }
        $this->initConstants();
        self::$instance = $this;
    }

    private function load(): string
    {
        $cacheID = 'cbrocken';
        /** @var string|false $brocken */
        $brocken = $this->cache->get($cacheID);
        if ($brocken === false) {
            $brocken = '';
            $data    = $this->db->getSingleObject(
                'SELECT cBrocken 
                    FROM tbrocken 
                    LIMIT 1'
            );
            if ($data !== null && !empty($data->cBrocken)) {
                $passA   = \mb_substr(\base64_decode($data->cBrocken), 0, 9);
                $passE   = \mb_substr(
                    \base64_decode($data->cBrocken),
                    \mb_strlen(\base64_decode($data->cBrocken)) - 11
                );
                $xtea    = new XTEA($passA . $passE);
                $brocken = $xtea->decrypt(
                    \str_replace(
                        [$passA, $passE],
                        ['', ''],
                        \base64_decode($data->cBrocken)
                    )
                );
                $this->cache->set($cacheID, $brocken, [\CACHING_GROUP_CORE]);
            }
        }

        return $brocken;
    }

    public function checkErweiterung(int $moduleID): bool
    {
        return $this->apiKey !== ''
            && \mb_strlen($this->apiKey) > 0
            && !empty($this->domain)
            && \count($this->moduleIDs) > 0
            && \in_array($moduleID, $this->moduleIDs, true);
    }

    private function initConstants(): self
    {
        \ifndef('SHOP_ERWEITERUNG_SEO', 8001);
        \ifndef('SHOP_ERWEITERUNG_AUSWAHLASSISTENT', 8031);
        \ifndef('SHOP_ERWEITERUNG_UPLOADS', 8041);
        \ifndef('SHOP_ERWEITERUNG_DOWNLOADS', 8051);
        \ifndef('SHOP_ERWEITERUNG_KONFIGURATOR', 8061);
        \ifndef('SHOP_ERWEITERUNG_WARENRUECKSENDUNG', 8071);
        \ifndef('SHOP_ERWEITERUNG_BRANDFREE', 8081);

        return $this;
    }

    /**
     * @return stdClass[]
     */
    public function gibAlleMoeglichenModule(): array
    {
        Shop::Container()->getGetText()->loadAdminLocale('widgets');
        $modules = [];
        $this->initConstants();
        $module           = new stdClass();
        $module->kModulId = \SHOP_ERWEITERUNG_AUSWAHLASSISTENT;
        $module->cName    = \__('moduleSelectionWizard');
        $module->cDefine  = 'SHOP_ERWEITERUNG_AUSWAHLASSISTENT';
        $module->cURL     = 'https://jtl-url.de/q6tox';
        $modules[]        = $module;
        $module           = new stdClass();
        $module->kModulId = \SHOP_ERWEITERUNG_UPLOADS;
        $module->cName    = \__('moduleUpload');
        $module->cDefine  = 'SHOP_ERWEITERUNG_UPLOADS';
        $module->cURL     = 'https://jtl-url.de/7-cop';
        $modules[]        = $module;
        $module           = new stdClass();
        $module->kModulId = \SHOP_ERWEITERUNG_DOWNLOADS;
        $module->cName    = \__('moduleDownload');
        $module->cDefine  = 'SHOP_ERWEITERUNG_DOWNLOADS';
        $module->cURL     = 'https://jtl-url.de/i0zvj';
        $modules[]        = $module;
        $module           = new stdClass();
        $module->kModulId = \SHOP_ERWEITERUNG_KONFIGURATOR;
        $module->cName    = \__('moduleConfigurator');
        $module->cDefine  = 'SHOP_ERWEITERUNG_KONFIGURATOR';
        $module->cURL     = 'https://jtl-url.de/ni9f5';
        $modules[]        = $module;
        $module           = new stdClass();
        $module->kModulId = \SHOP_ERWEITERUNG_BRANDFREE;
        $module->cName    = \__('moduleBrandFree');
        $module->cDefine  = 'SHOP_ERWEITERUNG_BRANDFREE';
        $module->cURL     = 'https://jtl-url.de/t4egb';
        $modules[]        = $module;

        return $modules;
    }

    public function getBrocken(): string
    {
        return $this->brocken;
    }

    public function getAPIKey(): string
    {
        return $this->apiKey;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * @return int[]
     */
    public function getShopModul(): array
    {
        return $this->moduleIDs;
    }
}
