<?php

declare(strict_types=1);

namespace JTL\Cache\Methods;

use JTL\Cache\ICachingMethod;
use JTL\Cache\JTLCacheTrait;

/**
 * Class CacheFile
 * Implements caching via filesystem
 * @package JTL\Cache\Methods
 */
class CacheFile implements ICachingMethod
{
    use JTLCacheTrait;

    /**
     * @param array{activated: bool, method: string, redis_port: int, redis_pass: string|null,
     *       redis_host: string, redis_db: int|null, redis_persistent: bool, memcache_port: int,
     *       memcache_host: string, prefix: string, lifetime: int, collect_stats: bool, debug: bool,
     *       debug_method: string, cache_dir: string, file_extension: string, page_cache: bool,
     *       types_disabled: string[], redis_user: string|null, rediscluster_hosts: string,
     *       rediscluster_strategy: string} $options
     */
    public function __construct(array $options)
    {
        $this->setIsInitialized(true);
        $this->setJournalID('file_journal');
        $this->setOptions($options);
        self::$instance = $this;
    }

    private function getFileName(mixed $cacheID): false|string
    {
        return \is_string($cacheID)
            ? $this->options['cache_dir'] . $cacheID . $this->options['file_extension']
            : false;
    }

    /**
     * @inheritdoc
     */
    public function store($cacheID, $content, ?int $expiration = null): bool
    {
        $dir = $this->options['cache_dir'];
        if (!\is_dir($dir) && \mkdir($dir) === false && !\is_dir($dir)) {
            return false;
        }
        $fileName = $this->getFileName($cacheID);
        if ($fileName === false) {
            return false;
        }
        $info  = \pathinfo($fileName, \PATHINFO_DIRNAME);
        $real1 = \realpath($info);
        $real2 = \realpath($dir);
        if ($real1 === false || $real2 === false || !\str_starts_with($real1, $real2)) {
            return false;
        }
        $res = \file_put_contents(
            $fileName,
            \serialize([
                'value'    => $content,
                'lifetime' => $expiration ?? $this->options['lifetime']
            ])
        );

        return $res !== false;
    }

    /**
     * @inheritdoc
     */
    public function storeMulti(array $idContent, ?int $expiration = null): bool
    {
        foreach ($idContent as $_key => $_value) {
            $this->store($_key, $_value, $expiration);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function load($cacheID)
    {
        $fileName = $this->getFileName($cacheID);
        if ($fileName !== false && \file_exists($fileName)) {
            /** @var array{lifetime: int, value: mixed} $data */
            $data = \unserialize(\file_get_contents($fileName) ?: '');
            if ($data['lifetime'] === 0 || (\time() - \filemtime($fileName)) < $data['lifetime']) {
                return $data['value'];
            }
            $this->flush($cacheID);
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function loadMulti(array $cacheIDs): array
    {
        $res = [];
        foreach ($cacheIDs as $_cid) {
            $res[$_cid] = $this->load($_cid);
        }

        return $res;
    }

    /**
     * @inheritdoc
     */
    public function isAvailable(): bool
    {
        $res = \is_dir($this->options['cache_dir'])
            || (\mkdir($this->options['cache_dir']) && \is_dir($this->options['cache_dir']));

        return $res && \is_writable($this->options['cache_dir']);
    }

    private function recursiveDelete(string $str): bool
    {
        if (\is_file($str)) {
            return \unlink($str);
        }
        if (\is_dir($str)) {
            foreach (\glob(\rtrim($str, '/') . '/*') ?: [] as $path) {
                $this->recursiveDelete($path);
            }

            return $str === $this->options['cache_dir'] || \rmdir($str);
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function flush($cacheID): bool
    {
        $fileName = $this->getFileName($cacheID);

        return $fileName !== false && \file_exists($fileName) && \unlink($fileName);
    }

    /**
     * @inheritdoc
     */
    public function flushAll(): bool
    {
        $this->journal = null;

        return $this->recursiveDelete($this->options['cache_dir']);
    }

    /**
     * @inheritdoc
     */
    public function getStats(): array
    {
        $dir   = \opendir($this->options['cache_dir']);
        $total = 0;
        $num   = 0;
        while ($dir && ($file = \readdir($dir)) !== false) {
            if ($file !== '.' && $file !== '..') {
                if (\is_dir($this->options['cache_dir'] . $file)) {
                    $subDir = \opendir($this->options['cache_dir'] . $file);
                    if ($subDir === false) {
                        continue;
                    }
                    while (($f = \readdir($subDir)) !== false) {
                        if ($f !== '.' && $f !== '..') {
                            $filePath = $this->options['cache_dir'] . $file . '/' . $f;
                            $total    += \filesize($filePath);
                            ++$num;
                        }
                    }
                    \closedir($subDir);
                } elseif (\is_file($this->options['cache_dir'] . $file)) {
                    $total += \filesize($this->options['cache_dir'] . $file);
                    ++$num;
                }
            }
        }
        if ($dir !== false) {
            \closedir($dir);
        }

        return [
            'entries' => $num,
            'hits'    => null,
            'misses'  => null,
            'inserts' => null,
            'mem'     => $total
        ];
    }
}
