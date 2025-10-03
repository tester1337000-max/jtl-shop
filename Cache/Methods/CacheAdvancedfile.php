<?php

declare(strict_types=1);

namespace JTL\Cache\Methods;

use FilesystemIterator;
use JTL\Cache\ICachingMethod;
use JTL\Cache\JTLCacheTrait;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Class CacheAdvancedfile
 *
 * Implements caching via filesystem where tags are not stored in a central file
 * but organized in folder and symlinked to the actual cache entry
 * @package JTL\Cache\Methods
 */
class CacheAdvancedfile implements ICachingMethod
{
    use JTLCacheTrait {
        test as traitTest;
    }

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
        $this->setJournalID('advancedfile_journal');
        $this->setOptions($options);
        $this->setIsInitialized(true);
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
        $fileName = $this->getFileName($cacheID);
        $dir      = $this->options['cache_dir'];
        if ($fileName === false || (!\is_dir($dir) && \mkdir($dir) === false && !\is_dir($dir))) {
            return false;
        }
        $real    = \realpath(\pathinfo($fileName, \PATHINFO_DIRNAME));
        $realDir = \realpath($dir);
        if (!\is_string($real) || !\is_string($realDir) || !\str_starts_with($real, $realDir)) {
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
        if (
            !\is_dir($this->options['cache_dir'])
            && !\mkdir($this->options['cache_dir'])
            && !\is_dir($this->options['cache_dir']) // check again after creating
        ) {
            return false;
        }

        return \is_writable($this->options['cache_dir']);
    }

    /**
     * @inheritdoc
     */
    public function test(): bool
    {
        return $this->traitTest()
            && \function_exists('symlink')
            && \touch($this->options['cache_dir'] . 'check')
            && \symlink($this->options['cache_dir'] . 'check', $this->options['cache_dir'] . 'link')
            && \readlink($this->options['cache_dir'] . 'link') === $this->options['cache_dir'] . 'check'
            && \unlink($this->options['cache_dir'] . 'link')
            && \unlink($this->options['cache_dir'] . 'check');
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
        $rdi = new RecursiveDirectoryIterator(
            $this->options['cache_dir'],
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS
        );
        /** @var \SplFileInfo $value */
        foreach (new RecursiveIteratorIterator($rdi, RecursiveIteratorIterator::CHILD_FIRST) as $value) {
            if ($value->isLink() || $value->isFile()) {
                \unlink($value->getPathname());
            } elseif ($value->isDir()) {
                \rmdir($value->getPathname());
            }
        }
        $this->flush($this->getJournalID());

        return true;
    }

    public function getJournalID(): string
    {
        return $this->journalID;
    }

    /**
     * this currently only calculate size/file count for real cache entries
     * and ignores symlinks which always are located in sub dirs
     *
     * @inheritdoc
     */
    public function getStats(): array
    {
        $dir   = \opendir($this->options['cache_dir']);
        $total = 0;
        $num   = 0;
        while ($dir && ($file = \readdir($dir)) !== false) {
            if ($file !== '.' && $file !== '..' && \is_file($this->options['cache_dir'] . $file)) {
                $total += \filesize($this->options['cache_dir'] . $file);
                ++$num;
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

    /**
     * @inheritdoc
     */
    public function setCacheTag($tags, $cacheID): bool
    {
        $fileName = $this->getFileName($cacheID);
        if ($fileName === false || !\file_exists($fileName) || \is_link($fileName)) {
            return false;
        }
        if (\is_string($tags)) {
            $tags = [$tags];
        }
        if (\count($tags) === 0) {
            return false;
        }
        $res = true;
        foreach ($tags as $tag) {
            $path = $this->options['cache_dir'];
            // create subdirs for every underscore
            foreach (\explode('_', $tag) as $dir) {
                if ($dir === '') {
                    $res = false;
                    continue;
                }
                $path .= $dir . '/';
                if (!\file_exists($path) && !\mkdir($path) && !\is_dir($path)) {
                    $res = false;
                }
            }
            if (\file_exists($path . $cacheID) || !\symlink($fileName, $path . $cacheID)) {
                $res = false;
            }
        }

        return $res;
    }

    /**
     * @inheritdoc
     */
    public function flushTags($tags): int
    {
        $deleted = 0;
        if (\is_string($tags)) {
            $tags = [$tags];
        }
        foreach ($tags as $tag) {
            $dirs = \explode('_', $tag);
            $path = $this->options['cache_dir'];
            foreach ($dirs as $dir) {
                $path .= $dir . '/';
            }
            if (!\is_dir($path)) {
                continue;
            }
            $rdi = new RecursiveDirectoryIterator(
                $path,
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS
            );
            /** @var \SplFileInfo $value */
            foreach (new RecursiveIteratorIterator($rdi, RecursiveIteratorIterator::CHILD_FIRST) as $value) {
                $res = false;
                if ($value->isLink()) {
                    $value = $value->getPathname();
                    // cache entries may have multiple tags - so check if the real entry still exists
                    if (($target = \readlink($value)) !== false && \is_file($target)) {
                        // delete real cache entry
                        $res = \unlink($target);
                    }
                    // delete symlink to the entry
                    \unlink($value);
                }
                if ($res === true) {
                    // only count cache files, not symlinks
                    ++$deleted;
                }
            }
        }

        return $deleted;
    }

    /**
     * clean up journal after deleting cache entries
     * not needed for this method
     *
     * @inheritdoc
     * @param string[]|string $tags
     */
    public function clearCacheTags(array|string $tags): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getKeysByTag($tags): array
    {
        if (\is_string($tags)) {
            $tags = [$tags];
        }
        if (!\is_array($tags)) {
            return [];
        }
        $res = [];
        foreach ($tags as $tag) {
            $dirs = \explode('_', $tag);
            $path = $this->options['cache_dir'];
            foreach ($dirs as $dir) {
                $path .= $dir . '/';
            }
            if (!\is_dir($path)) {
                continue;
            }
            $rdi = new RecursiveDirectoryIterator(
                $path,
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS
            );
            /** @var \SplFileInfo $value */
            foreach (new RecursiveIteratorIterator($rdi, RecursiveIteratorIterator::CHILD_FIRST) as $value) {
                if ($value->isFile()) {
                    $res[] = $value->getFilename();
                }
            }
        }

        return \array_unique($res);
    }
}
