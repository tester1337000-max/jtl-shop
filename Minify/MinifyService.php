<?php

declare(strict_types=1);

namespace JTL\Minify;

use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use JTL\Template\Model;

/**
 * Class MinifyService
 * @package JTL\Minify
 */
class MinifyService
{
    protected string $baseDir = \PFAD_ROOT . \PATH_STATIC_MINIFY;

    public const TYPE_CSS = 'css';

    public const TYPE_JS = 'js';

    public function __construct()
    {
        if (!\is_dir($this->baseDir) && !\mkdir($this->baseDir) && !\is_dir($this->baseDir)) {
            throw new \RuntimeException(\sprintf('Directory "%s" was not created', $this->baseDir));
        }
    }

    /**
     * Build a URI for the static cache
     *
     * @param string      $urlPrefix E.g. "/min/static"
     * @param string      $query E.g. "b=scripts&f=1.js,2.js"
     * @param string      $type "css" or "js"
     * @param string|null $cacheTime
     * @return string
     */
    public function buildURI(string $urlPrefix, string $query, string $type, ?string $cacheTime = null): string
    {
        $urlPrefix = \rtrim($urlPrefix, '/');
        $query     = \ltrim($query, '?');
        $ext       = '.' . $type;
        $cacheTime = $cacheTime ?? $this->getCacheTime();
        if (!\str_ends_with($query, $ext)) {
            $query .= '&z=' . $ext;
        }

        return $urlPrefix . '/' . $cacheTime . '/' . $query;
    }

    /**
     * Get the name of the current cache directory within static/. E.g. "1467089473"
     *
     * @param bool $autoCreate Automatically create the directory if missing?
     * @return null|string null if missing or can't create
     */
    protected function getCacheTime(bool $autoCreate = true): ?string
    {
        foreach (\scandir($this->baseDir) ?: [] as $entry) {
            if (\ctype_digit($entry)) {
                return $entry;
            }
        }

        if (!$autoCreate) {
            return null;
        }
        $time = (string)\time();
        $dir  = $this->baseDir . $time;
        if (!\mkdir($dir) && !\is_dir($dir)) {
            return null;
        }

        return $time;
    }

    public function flushCache(): bool
    {
        $time = $this->getCacheTime(false);

        return $time && $this->removeTree($this->baseDir . $time);
    }

    protected function removeTree(string $dir): bool
    {
        foreach (\array_diff(\scandir($dir) ?: [], ['.', '..']) as $file) {
            $path = $dir . \DIRECTORY_SEPARATOR . $file;
            \is_dir($path) ? $this->removeTree($path) : \unlink($path);
        }

        return \rmdir($dir);
    }

    public function buildURIs(JTLSmarty $smarty, Model $template, string $themeDir): void
    {
        $minify      = $template->getResources()->getMinifyArray();
        $versions    = $template->getResources()->getFileVersions();
        $tplVersion  = $template->getVersion();
        $allowStatic = (Shop::getSettingValue(\CONF_TEMPLATE, 'general')['use_minify'] ?? 'N') === 'static';
        $cacheTime   = $allowStatic ? $this->getCacheTime() : null;
        $css         = $minify[$themeDir . '.css'] ?? [];
        $js          = $minify['jtl3.js'] ?? [];
        $res         = [];
        $data        = [
            self::TYPE_CSS => [
                $themeDir . '.css',
                'plugin_css',
            ],
            self::TYPE_JS  => [
                'jtl3.js',
                'plugin_js_head',
                'plugin_js_body'
            ]
        ];
        \executeHook(\HOOK_LETZTERINCLUDE_CSS_JS, [
            'cCSS_arr'          => &$css,
            'cJS_arr'           => &$js,
            'cPluginCss_arr'    => &$minify['plugin_css'],
            'cPluginJsHead_arr' => &$minify['plugin_js_head'],
            'cPluginJsBody_arr' => &$minify['plugin_js_body'],
        ]);
        foreach ($data as $type => $groups) {
            $res[$type] = [];
            foreach ($groups as $group) {
                if (!isset($minify[$group]) || \count($minify[$group]) === 0) {
                    continue;
                }
                if ($allowStatic === true) {
                    $uri = $this->buildURI('static', 'g=' . $group, $type, $cacheTime);
                } else {
                    $hash = $this->getVersionHash($group, $versions);
                    $uri  = 'asset/' . $group . '?v=' . $tplVersion;
                    if ($hash !== null) {
                        $uri .= '&h=' . $hash;
                    }
                }
                if ($template->getIsPreview()) {
                    $uri .= '&preview=1';
                }
                $res[$type][$group] = $uri;
            }
        }
        if ($allowStatic === true) {
            $uri = 'g=' . $themeDir . '.css';
            if (isset($minify['plugin_css']) && \count($minify['plugin_css']) > 0) {
                $uri .= ',plugin_css';
            }
            $combinedCSS = $this->buildURI('static', $uri, self::TYPE_CSS, $cacheTime);
        } else {
            $combinedCSS = 'asset/' . $themeDir . '.css';
            $hash        = null;
            if (\count($minify['plugin_css']) > 0) {
                $combinedCSS .= ',plugin_css';
                $hash        = $this->getVersionHash('plugin_css', $versions);
            }
            $combinedCSS .= '?v=' . $tplVersion;
            if ($hash !== null) {
                $combinedCSS .= '&h=' . $hash;
            }
        }
        if ($template->getIsPreview()) {
            $combinedCSS .= '&preview=1';
        }
        $smarty->assign('cPluginCss_arr', $minify['plugin_css'])
            ->assign('cPluginJsHead_arr', $minify['plugin_js_head'])
            ->assign('cPluginJsBody_arr', $minify['plugin_js_body'])
            ->assign('minifiedCSS', $res[self::TYPE_CSS])
            ->assign('minifiedJS', $res[self::TYPE_JS])
            ->assign('combinedCSS', $combinedCSS)
            ->assign('cCSS_arr', $css)
            ->assign('cJS_arr', $js);
    }

    /**
     * @param array<string, mixed> $versions
     */
    private function getVersionHash(string $group, array $versions): ?string
    {
        if (!isset($versions[$group]) || \count($versions[$group]) === 0) {
            return null;
        }
        \ksort($versions[$group]);

        return \md5(\json_encode(\array_values($versions[$group]), \JSON_THROW_ON_ERROR));
    }
}
