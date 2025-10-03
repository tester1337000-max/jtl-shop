<?php

declare(strict_types=1);

namespace JTL\Cache;

/**
 * Interface ICachingMethod
 * @package JTL\Cache
 */
interface ICachingMethod
{
    /**
     * store value to cache
     *
     * @param string|int $cacheID - key to identify the value
     * @param mixed      $content - the content to save
     * @param int|null   $expiration - expiration time in seconds
     * @return bool - success
     */
    public function store($cacheID, $content, ?int $expiration = null): bool;

    /**
     * store multiple values to multiple keys at once to cache
     *
     * @param array<string, mixed> $idContent - array keys are cache IDs, array values are content to save
     * @param int|null             $expiration - expiration time in seconds
     * @return bool
     */
    public function storeMulti(array $idContent, ?int $expiration = null): bool;

    /**
     * get value from cache
     *
     * @param string|int $cacheID
     * @return mixed|bool - the loaded data or false if not found
     */
    public function load($cacheID);

    /**
     * check if key exists
     *
     * @param string|int $key
     * @return bool
     */
    public function keyExists($key): bool;

    /**
     * get multiple values at once from cache
     *
     * @param string[] $cacheIDs
     * @return array<string, mixed>
     */
    public function loadMulti(array $cacheIDs): array;

    /**
     * add cache tags to cached value
     *
     * @param string|string[] $tags
     * @param string|int      $cacheID
     * @return bool
     */
    public function setCacheTag($tags, $cacheID): bool;

    /**
     * get cache IDs by cache tag(s)
     *
     * @param string[]|string $tags
     * @return string[]|array{}
     */
    public function getKeysByTag($tags): array;

    /**
     * removes cache IDs associated with given tags from cache
     *
     * @param string[]|string $tags
     * @return int
     */
    public function flushTags($tags): int;

    /**
     * load journal
     *
     * @return array<mixed>
     */
    public function getJournal(): array;

    /**
     * class singleton getter
     *
     * @param array{activated: bool, method: string, redis_port: int, redis_pass: string|null,
     *        redis_host: string, redis_db: int|null, redis_persistent: bool, memcache_port: int,
     *        memcache_host: string, prefix: string, lifetime: int, collect_stats: bool, debug: bool,
     *        debug_method: string, cache_dir: string, file_extension: string, page_cache: bool,
     *        types_disabled: string[], redis_user: string|null, rediscluster_hosts: string,
     *        rediscluster_strategy: string} $options
     * @return ICachingMethod
     */
    public static function getInstance(array $options): ICachingMethod;

    /**
     * check if php functions for using the selected caching method exist
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * check if method was successfully initialized
     *
     * @return bool
     */
    public function isInitialized(): bool;

    /**
     * clear cache by cid or gid
     *
     * @param string|int|string[] $cacheID
     * @return bool - success
     */
    public function flush($cacheID): bool;

    /**
     * flushes all values from cache
     *
     * @return bool
     */
    public function flushAll(): bool;

    /**
     * test data integrity and if functions are working properly - default implementation @JTLCacheTrait
     *
     * @return bool - success
     */
    public function test(): bool;

    /**
     * get statistical data for caching method if supported
     *
     * @return array{}|array{entries: int|string, hits: int|string|null, misses: int|string|null, inserts?: int|null,
     *      mem: int, uptime_h?: string|null, hps?: float|null, mps?: float|null,
     *      max?: int|null, slow?: array<int<0, max>, array{}|array{date?: non-falsy-string, cmd?: mixed,
     *      exec_time?: (float|int)}>}
     */
    public function getStats(): array;

    /**
     * @return string|null
     */
    public function getJournalID(): ?string;

    /**
     * @param string $id
     */
    public function setJournalID(string $id): void;

    /**
     * @return string
     */
    public function getError(): string;

    /**
     * @param string $error
     */
    public function setError(string $error): void;
}
