<?php

declare(strict_types=1);

namespace JTL\Cache\Methods;

use JTL\Cache\ICachingMethod;
use JTL\Cache\JTLCacheTrait;
use Memcache;

/**
 * Class CacheMemcache
 *
 * Implements the Memcache memory object caching system - no "d" at the end
 * @package JTL\Cache\Methods
 */
class CacheMemcache implements ICachingMethod
{
    use JTLCacheTrait;

    private ?Memcache $memcache = null;

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
        if (empty($options['memcache_host']) || empty($options['memcache_port']) || !$this->isAvailable()) {
            return;
        }
        $this->setMemcache($options['memcache_host'], (int)$options['memcache_port']);
        $this->setIsInitialized(true);
        $this->setJournalID('memcache_journal');
        // @see http://php.net/manual/de/memcached.expiration.php
        $options['lifetime'] = \min(60 * 60 * 24 * 30, $options['lifetime']);
        $this->setOptions($options);
        self::$instance = $this;
    }

    private function setMemcache(string $host, int $port): ICachingMethod
    {
        $this->memcache?->close();
        $this->memcache = new Memcache();
        $this->memcache->addServer($host, $port);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function store($cacheID, $content, ?int $expiration = null): bool
    {
        return $this->memcache?->set(
            $this->options['prefix'] . $cacheID,
            $content,
            0,
            $expiration ?? $this->options['lifetime']
        ) ?? false;
    }

    /**
     * @inheritdoc
     */
    public function storeMulti(array $idContent, ?int $expiration = null): bool
    {
        return $this->memcache?->set(
            $this->prefixArray($idContent),
            $expiration ?? $this->options['lifetime']
        ) ?? false;
    }

    /**
     * @inheritdoc
     */
    public function load($cacheID)
    {
        return $this->memcache?->get($this->options['prefix'] . $cacheID) ?? false;
    }

    /**
     * @inheritdoc
     */
    public function loadMulti(array $cacheIDs): array
    {
        if ($this->memcache === null) {
            return [];
        }
        $prefixedKeys = \array_map(fn($cid): string => $this->options['prefix'] . $cid, $cacheIDs);
        $res          = $this->dePrefixArray($this->memcache->get($prefixedKeys));
        // fill up result
        return \array_merge(\array_fill_keys($cacheIDs, false), $res);
    }

    /**
     * @inheritdoc
     */
    public function isAvailable(): bool
    {
        return \class_exists('Memcache');
    }

    /**
     * @inheritdoc
     */
    public function flush($cacheID): bool
    {
        return $this->memcache?->delete($this->options['prefix'] . $cacheID) ?? false;
    }

    /**
     * @inheritdoc
     */
    public function flushAll(): bool
    {
        return $this->memcache?->flush() ?? false;
    }

    /**
     * @inheritdoc
     */
    public function getStats(): array
    {
        if ($this->memcache === null) {
            return [];
        }
        $stats = $this->memcache->getStats();

        return [
            'entries' => $stats['curr_items'],
            'hits'    => $stats['get_hits'],
            'misses'  => $stats['get_misses'],
            'inserts' => $stats['cmd_set'],
            'mem'     => $stats['bytes']
        ];
    }
}
