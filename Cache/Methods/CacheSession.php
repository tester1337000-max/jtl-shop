<?php

declare(strict_types=1);

namespace JTL\Cache\Methods;

use JTL\Cache\ICachingMethod;
use JTL\Cache\JTLCacheTrait;

/**
 * Class CacheSession
 * Implements caching via PHP $_SESSION object
 * @package JTL\Cache\Methods
 * @deprecated since 5.5.0
 */
class CacheSession implements ICachingMethod
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
        $this->setJournalID('session_journal');
        $this->setOptions($options);
        self::$instance = $this;
    }

    /**
     * @inheritdoc
     */
    public function store($cacheID, $content, ?int $expiration = null): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function storeMulti(array $idContent, ?int $expiration = null): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function load($cacheID)
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function loadMulti(array $cacheIDs): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function isAvailable(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function flush($cacheID): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function flushAll(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function keyExists($key): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getStats(): array
    {
        return [
            'entries' => 0,
            'hits'    => null,
            'misses'  => null,
            'inserts' => null,
            'mem'     => 0
        ];
    }
}
