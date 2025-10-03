<?php

declare(strict_types=1);

namespace JTL\Cache\Methods;

use JTL\Cache\ICachingMethod;
use JTL\Cache\JTLCacheTrait;
use Memcached;

use function Functional\first;

/**
 * Class CacheMemcached
 * Implements the Memcached memory object caching system - notice the "d" at the end
 *
 * @package JTL\Cache\Methods
 */
class CacheMemcached implements ICachingMethod
{
    use JTLCacheTrait;

    private ?Memcached $memcached = null;

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
        $this->setMemcached($options['memcache_host'], (int)$options['memcache_port'], $options['prefix']);
        $this->setIsInitialized(true);
        $test = $this->test();
        $this->setError($test === true ? '' : $this->memcached?->getResultMessage() ?? '');
        $this->setJournalID('memcached_journal');
        // @see http://php.net/manual/de/memcached.expiration.php
        $options['lifetime'] = \min(60 * 60 * 24 * 30, $options['lifetime']);
        $this->setOptions($options);
        self::$instance = $this;
    }

    private function setMemcached(string $host, int $port, string $prefix): ICachingMethod
    {
        $this->memcached?->quit();
        $this->memcached = new Memcached();
        $this->memcached->addServer($host, $port);
        $this->memcached->setOption(Memcached::OPT_PREFIX_KEY, $prefix);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function store($cacheID, $content, ?int $expiration = null): bool
    {
        return $this->memcached?->set(
            (string)$cacheID,
            $content,
            $expiration ?? $this->options['lifetime']
        ) ?? false;
    }

    /**
     * @inheritdoc
     */
    public function storeMulti(array $idContent, ?int $expiration = null): bool
    {
        return $this->memcached?->setMulti($idContent, $expiration ?? $this->options['lifetime']) ?? false;
    }

    /**
     * @inheritdoc
     */
    public function load($cacheID)
    {
        return $this->memcached?->get((string)$cacheID) ?? false;
    }

    /**
     * @inheritdoc
     */
    public function loadMulti(array $cacheIDs): array
    {
        return \array_merge(\array_fill_keys($cacheIDs, false), $this->memcached?->getMulti($cacheIDs) ?: []);
    }

    /**
     * @inheritdoc
     */
    public function isAvailable(): bool
    {
        return \class_exists('Memcached');
    }

    /**
     * @inheritdoc
     */
    public function flush($cacheID): bool
    {
        return $this->memcached?->delete((string)$cacheID) ?? false;
    }

    /**
     * @inheritdoc
     */
    public function flushAll(): bool
    {
        return $this->memcached?->flush() ?? false;
    }

    /**
     * @inheritdoc
     */
    public function keyExists($key): bool
    {
        if ($this->memcached === null) {
            return false;
        }
        $res = $this->memcached->get((string)$key);

        return ($res !== false || $this->memcached->getResultCode() === Memcached::RES_SUCCESS);
    }

    /**
     * @inheritdoc
     */
    public function getStats(): array
    {
        if ($this->memcached === null || !\method_exists($this->memcached, 'getStats')) {
            return [];
        }
        $stats = $this->memcached->getStats();
        if (!\is_array($stats) || \count($stats) === 0) {
            return [];
        }
        $stat = null;
        if (\count($stats) > 1) {
            $options = $this->getOptions();
            $stat    = $stats[$options['memcache_host'] . ':' . $options['memcache_port']] ?? null;
        }
        if ($stat === null) {
            $stat = first($stats);
        }

        return [
            'entries' => $stat['curr_items'],
            'hits'    => $stat['get_hits'],
            'misses'  => $stat['get_misses'],
            'inserts' => $stat['cmd_set'],
            'mem'     => $stat['bytes'],
            'max'     => $stat['limit_maxbytes']
        ];
    }
}
