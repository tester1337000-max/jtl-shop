<?php

declare(strict_types=1);

namespace JTL\Backend;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Shop;
use JTL\Template\XMLReader;
use SimpleXMLElement;

/**
 * Class AdminTemplate
 * @package JTL\Backend
 */
class AdminTemplate
{
    public static string $cTemplate = 'bootstrap';

    private static ?AdminTemplate $instance = null;

    public readonly string $version;

    public function __construct(private readonly DbInterface $db, private readonly JTLCacheInterface $cache)
    {
        $this->init();
        self::$instance = $this;
        $this->version  = '1.1.0';
    }

    public static function getInstance(?DbInterface $db = null, ?JTLCacheInterface $cache = null): self
    {
        return self::$instance ?? new self($db ?? Shop::Container()->getDB(), $cache ?? Shop::Container()->getCache());
    }

    /**
     * @deprecated since 5.4.0
     */
    public function getConfig(): bool
    {
        \trigger_error(__METHOD__ . ' is deprecated.', \E_USER_DEPRECATED);

        return false;
    }

    public function getDir(bool $absolute = false): string
    {
        return $absolute
            ? (\PFAD_ROOT . \PFAD_ADMIN . \PFAD_TEMPLATES . self::$cTemplate)
            : self::$cTemplate;
    }

    public function init(): self
    {
        $cacheID = 'adm_tpl_' . \APPLICATION_VERSION;
        if (($template = $this->cache->get($cacheID)) !== false) {
            self::$cTemplate = $template->cTemplate;
        } else {
            $template = $this->db->select('ttemplate', 'eTyp', 'admin');
            if ($template) {
                self::$cTemplate = $template->cTemplate;
                $this->cache->set($cacheID, $template, [\CACHING_GROUP_TEMPLATE]);

                return $this;
            }
        }

        return $this;
    }

    /**
     * get array of static resources in minify compatible format
     * @return array{'admin_css': string[], 'admin_js': string[]}
     */
    public function getMinifyArray(bool $absolute = false): array
    {
        $dir       = $this->getDir();
        $folders   = [];
        $folders[] = $dir;
        $cacheID   = 'tpl_mnfy_dta_adm_' . $dir . (($absolute === true) ? '_a' : '') . \APPLICATION_VERSION;
        /** @var array{'admin_css': string[], 'admin_js': string[]}|false $tplGroups */
        $tplGroups = $this->cache->get($cacheID);
        if ($tplGroups !== false) {
            return $tplGroups;
        }
        $tplGroups = [
            'admin_css' => [],
            'admin_js'  => []
        ];
        $reader    = new XMLReader();
        foreach ($folders as $dir) {
            $xml = $reader->getXML($dir, true);
            if ($xml === null) {
                continue;
            }
            $cssSource = $xml->Minify->CSS ?? new SimpleXMLElement('<CSS></CSS>');
            $jsSource  = $xml->Minify->JS ?? new SimpleXMLElement('<JS></JS>');
            $tplGroups = $this->getCSSSources($cssSource, $absolute, $dir, $tplGroups);
            $tplGroups = $this->getJSSources($jsSource, $absolute, $dir, $tplGroups);
        }
        $cacheTags = [\CACHING_GROUP_OPTION, \CACHING_GROUP_TEMPLATE, \CACHING_GROUP_PLUGIN];
        $this->cache->set($cacheID, $tplGroups, $cacheTags);

        return $tplGroups;
    }

    /**
     * @param array<mixed> $tplGroups
     * @return array<string, array<string>>
     */
    private function getJSSources(SimpleXMLElement $jsSource, bool $absolute, string $dir, array $tplGroups): array
    {
        $prefix = $absolute === true ? \PFAD_ROOT : '';
        foreach ($jsSource as $js) {
            $name = (string)$js->attributes()->Name;
            if (!isset($tplGroups[$name])) {
                $tplGroups[$name] = [];
            }
            foreach ($js->File as $jsFile) {
                $tplGroups[$name][] = $prefix
                    . \PFAD_ADMIN . \PFAD_TEMPLATES
                    . $dir . '/' . $jsFile->attributes()->Path;
            }
        }

        return $tplGroups;
    }

    /**
     * @param array<mixed> $tplGroups
     * @return array<string, array<string>>
     */
    private function getCSSSources(SimpleXMLElement $cssSource, bool $absolute, string $dir, array $tplGroups): array
    {
        $prefix = $absolute === true ? \PFAD_ROOT : '';
        foreach ($cssSource as $css) {
            $name = (string)$css->attributes()->Name;
            if (!isset($tplGroups[$name])) {
                $tplGroups[$name] = [];
            }
            foreach ($css->File as $cssFile) {
                $file     = (string)$cssFile->attributes()->Path;
                $filePath = \PFAD_ROOT . \PFAD_ADMIN . \PFAD_TEMPLATES . $dir . '/' . $file;
                if (!\file_exists($filePath)) {
                    continue;
                }
                $tplGroups[$name][] = $prefix
                    . \PFAD_ADMIN . \PFAD_TEMPLATES
                    . $dir . '/' . $cssFile->attributes()->Path;
                $customFilePath     = \str_replace('.css', '_custom.css', $filePath);
                if (\file_exists($customFilePath)) {
                    $tplGroups[$name][] = \str_replace(
                        '.css',
                        '_custom.css',
                        $prefix
                        . \PFAD_ADMIN . \PFAD_TEMPLATES
                        . $dir . '/' . $cssFile->attributes()->Path
                    );
                }
            }
        }

        return $tplGroups;
    }

    /**
     * build string to serve minified files or direct head includes
     *
     * @param bool $minify - generates absolute links for minify when true
     * @return array{'js': string, 'css': string} - list of js/css resources
     */
    public function getResources(bool $minify = true): array
    {
        $outputCSS = '';
        $outputJS  = '';
        $baseURL   = Shop::getURL();
        $files     = $this->getMinifyArray($minify);
        if ($minify === false) {
            $fileSuffix = '?v=' . $this->version;
            foreach ($files['admin_js'] as $file) {
                $outputJS .= '<script type="text/javascript" src="'
                    . $baseURL . '/'
                    . $file
                    . $fileSuffix
                    . '"></script>'
                    . "\n";
            }
            foreach ($files['admin_css'] as $file) {
                $outputCSS .= '<link rel="stylesheet" type="text/css" href="'
                    . $baseURL . '/'
                    . $file
                    . $fileSuffix
                    . '" media="screen" />'
                    . "\n";
            }
        } else {
            $tplString  = $this->getDir(); // add tpl string to avoid caching
            $fileSuffix = '&v=' . $this->version;
            $outputCSS  = '<link rel="stylesheet" type="text/css" href="'
                . $baseURL . '/'
                . \PFAD_MINIFY . '/index.php?g=admin_css&tpl='
                . $tplString
                . $fileSuffix
                . '" media="screen" />';
            $outputJS   = '<script type="text/javascript" src="'
                . $baseURL . '/'
                . \PFAD_MINIFY
                . '/index.php?g=admin_js&tpl='
                . $tplString
                . $fileSuffix
                . '"></script>';
        }

        return ['js' => $outputJS, 'css' => $outputCSS];
    }
}
