<?php

declare(strict_types=1);

namespace JTL\Cache;

/**
 * Interface JTLCacheInterface
 * @package JTL\Cache
 */
interface JTLCacheInterface
{
    /**
     * @return array{name: string, nicename: string, value: string, description: string}[]
     */
    public function getCachingGroups(): array;

    /**
     * @param array{}|array{activated: bool, method: string, redis_port: int, redis_pass: string|null,
     *          redis_host: string, redis_db: int|null, redis_persistent: bool, memcache_port: int,
     *          memcache_host: string, prefix: string, lifetime: int, collect_stats: bool, debug: bool,
     *          debug_method: string, cache_dir: string, file_extension: string, page_cache: bool,
     *          types_disabled: string[], redis_user: string|null, rediscluster_hosts: string,
     *          rediscluster_strategy: string} $options
     * @return JTLCacheInterface
     */
    public function setOptions(array $options = []): JTLCacheInterface;

    /**
     * set caching method by name
     *
     * @param string $methodName
     * @return bool
     */
    public function setCache(string $methodName): bool;

    /**
     * load shop cache config from db
     *
     * @param \stdClass[] $config
     * @return array{activated: bool, method: string, redis_port: int, redis_pass: string|null,
     *         redis_host: string, redis_db: int|null, redis_persistent: bool, memcache_port: int,
     *         memcache_host: string, prefix: string, lifetime: int, collect_stats: bool, debug: bool,
     *         debug_method: string, cache_dir: string, file_extension: string, page_cache: bool,
     *         types_disabled: string[], redis_user: string|null, rediscluster_hosts: string,
     *         rediscluster_strategy: string}
     */
    public function getJtlCacheConfig(array $config): array;

    /**
     * @param array<mixed> $config
     * @return JTLCacheInterface
     */
    public function setJtlCacheConfig(array $config): JTLCacheInterface;

    /**
     * @return JTLCacheInterface
     */
    public function init(): JTLCacheInterface;

    /**
     * get current options
     *
     * @return array{activated: bool, method: string, redis_port: int, redis_pass: string|null,
     *           redis_host: string, redis_db: int|null, redis_persistent: bool, memcache_port: int,
     *           memcache_host: string, prefix: string, lifetime: int, collect_stats: bool, debug: bool,
     *           debug_method: string, cache_dir: string, file_extension: string, page_cache: bool,
     *           types_disabled: string[], redis_user: string|null, rediscluster_hosts: string,
     *           rediscluster_strategy: string}
     */
    public function getOptions(): array;

    /**
     * retrieve value from cache
     *
     * @param string|int    $cacheID
     * @param null|callable $callback
     * @param null|mixed    $customData
     * @return mixed
     */
    public function get($cacheID, ?callable $callback = null, $customData = null);

    /**
     * store value to cache
     *
     * @param string|int           $cacheID
     * @param mixed                $content
     * @param null|string[]|string $tags
     * @param null|int             $expiration
     * @return bool
     */
    public function set($cacheID, $content, $tags = null, ?int $expiration = null): bool;

    /**
     * store multiple values to multiple cache IDs at once
     *
     * @param array<string, mixed> $keyValue
     * @param string[]|null        $tags
     * @param int|null             $expiration
     * @return bool
     */
    public function setMulti(array $keyValue, ?array $tags = null, ?int $expiration = null): bool;

    /**
     * get multiple values from cache
     *
     * @param string[] $cacheIDs
     * @return array<string, mixed>
     */
    public function getMulti(array $cacheIDs): array;

    /**
     * check if cache for selected group id is active
     * this allows the disabling of certain cache types
     *
     * @param string|string[]|null $groupID
     * @return bool
     */
    public function isCacheGroupActive($groupID): bool;

    /**
     * @param string[]|string $tags
     * @return string[]|array{}
     */
    public function getKeysByTag($tags): array;

    /**
     * add cache tag to cache value by ID
     *
     * @param string[]|string $tags
     * @param string|int      $cacheID
     * @return bool
     */
    public function setCacheTag($tags, $cacheID): bool;

    /**
     * set custom cache lifetime
     *
     * @param int $lifetime
     * @return JTLCacheInterface
     */
    public function setCacheLifetime(int $lifetime): JTLCacheInterface;

    /**
     * set custom file cache directory
     *
     * @param string $dir
     * @return JTLCacheInterface
     */
    public function setCacheDir(string $dir): JTLCacheInterface;

    /**
     * get the currently activated cache method
     *
     * @return ICachingMethod
     */
    public function getActiveMethod(): ICachingMethod;

    /**
     * remove single ID from cache or group or remove whole group
     *
     * @param string|int|null      $cacheID
     * @param string[]|string|null $tags
     * @param array<mixed>|null    $hookInfo
     * @return bool|int
     */
    public function flush($cacheID = null, $tags = null, $hookInfo = null);

    /**
     * delete keys tagged with one or more tags
     *
     * @param string[]|string $tags
     * @param mixed           $hookInfo
     * @return int
     */
    public function flushTags($tags, $hookInfo = null): int;

    /**
     * clear all values from cache
     *
     * @return bool
     */
    public function flushAll(): bool;

    /**
     * get result code from last operation
     *
     * @return int
     */
    public function getResultCode(): int;

    /**
     * get caching method's journal data
     *
     * @return array<mixed>
     */
    public function getJournal(): array;

    /**
     * get statistical data
     *
     * @return array{}|array{entries: int|string, hits: int|null, misses: int|null, inserts?: int|null,
     *       mem: int, uptime?: int, uptime_h?: string|null, hps?: float|null, mps?: float|null,
     *       max?: int|null, slow?: array<int<0, max>, array{}|array{date?: non-falsy-string, cmd?: mixed,
     *       exec_time?: (float|int)}>}
     */
    public function getStats(): array;

    /**
     * test method's integrity
     *
     * @return bool
     */
    public function testMethod(): bool;

    /**
     * check if caching method is available
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * check if caching is enabled
     *
     * @return bool
     */
    public function isActive(): bool;

    /**
     * get list of all installed caching methods
     *
     * @return string[]
     */
    public function getAllMethods(): array;

    /**
     * check which caching methods are available and usable
     *
     * @return array<string, array{available: bool, functional: bool}>
     */
    public function checkAvailability(): array;

    /**
     * generate basic cache id with popular variables
     *
     * @param bool     $hash
     * @param bool|int $customerID
     * @param bool|int $customerGroup
     * @param bool|int $languageID
     * @param bool|int $currencyID
     * @param bool     $sslStatus
     * @return string
     */
    public function getBaseID(
        bool $hash = false,
        $customerID = false,
        $customerGroup = true,
        $languageID = true,
        $currencyID = true,
        bool $sslStatus = true
    ): string;

    /**
     * simple benchmark for different caching methods
     *
     * @param string|string[] $methods
     * @param string|mixed    $testData
     * @param int             $runCount
     * @param int             $repeat
     * @param bool            $echo
     * @param bool            $format
     * @return array{method: string, status: 'invalid'|'ok'|'failed',
     *       timings: array{get: float|string, set: float|string},
     *       rps?: array{get: string|float, set: string|float}}
     */
    public function benchmark(
        $methods = 'all',
        $testData = 'simple string',
        int $runCount = 1000,
        int $repeat = 1,
        bool $echo = true,
        bool $format = false
    ): array;

    /**
     * @return string
     */
    public function getError(): string;

    /**
     * @param string $error
     */
    public function setError(string $error): void;
}
