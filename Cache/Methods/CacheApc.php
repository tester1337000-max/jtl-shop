<?php

declare(strict_types=1);

namespace JTL\Cache\Methods;

use JTL\Cache\ICachingMethod;
use JTL\Cache\JTLCacheTrait;

/**
 * Class CacheApc
 *
 * implements the APC Opcode Cache
 * @package JTL\Cache\Methods
 */
class CacheApc implements ICachingMethod
{
    use JTLCacheTrait;

    /**
     * check whether apc_ or apcu_ functions should be used
     */
    private bool $u;

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
        $this->setJournalID('apc_journal');
        $this->setOptions($options);
        $this->u        = \function_exists('apcu_store');
        self::$instance = $this;
    }

    /**
     * @inheritdoc
     */
    public function store($cacheID, $content, ?int $expiration = null): bool
    {
        $func = $this->u ? '\apcu_store' : '\apc_store';

        return $func($this->options['prefix'] . $cacheID, $content, $expiration ?? $this->options['lifetime']);
    }

    /**
     * @inheritdoc
     */
    public function storeMulti(array $idContent, ?int $expiration = null): bool
    {
        $func = $this->u ? '\apcu_store' : '\apc_store';
        $res  = $func($this->prefixArray($idContent), null, $expiration ?? $this->options['lifetime']);

        return \is_bool($res) ? $res : false;
    }

    /**
     * @inheritdoc
     */
    public function load($cacheID)
    {
        $func = $this->u ? '\apcu_fetch' : '\apc_fetch';

        return $func($this->options['prefix'] . $cacheID);
    }

    /**
     * @inheritdoc
     */
    public function loadMulti(array $cacheIDs): array
    {
        $func         = $this->u ? '\apcu_fetch' : '\apc_fetch';
        $prefixedKeys = [];
        foreach ($cacheIDs as $_cid) {
            $prefixedKeys[] = $this->options['prefix'] . $_cid;
        }
        $res = $this->dePrefixArray($func($prefixedKeys));

        // fill up with false values
        return \array_merge(\array_fill_keys($cacheIDs, false), $res);
    }

    /**
     * @inheritdoc
     */
    public function isAvailable(): bool
    {
        return ((\function_exists('apc_store') && \function_exists('apc_exists'))
            || (\function_exists('apcu_store') && \function_exists('apcu_exists')));
    }

    /**
     * @inheritdoc
     */
    public function flush($cacheID): bool
    {
        $cacheID = $this->options['prefix'] . $cacheID;

        return $this->u ? \apcu_delete($cacheID) : \apc_delete($cacheID);
    }

    /**
     * @inheritdoc
     */
    public function flushAll(): bool
    {
        return $this->u ? \apcu_clear_cache() : \apc_clear_cache('user');
    }

    /**
     * @inheritdoc
     */
    public function keyExists($key): bool
    {
        $func = $this->u ? '\apcu_exists' : '\apc_exists';

        return $func($this->options['prefix'] . $key);
    }

    /**
     * @inheritdoc
     */
    public function getStats(): array
    {
        try {
            $memSize = 0;
            $memUsed = 0;
            $tmp     = $this->u ? \apcu_cache_info() : \apc_cache_info('user');
            $mem     = $this->u ? \apcu_sma_info(true) : \apc_sma_info(true);
            if (isset($mem['avail_mem'], $mem['num_seg'], $mem['seg_size'])) {
                $memSize  = $mem['num_seg'] * $mem['seg_size'];
                $memAvail = $mem['avail_mem'];
                $memUsed  = $memSize - $memAvail;
            }
            $stats = [
                'entries' => $tmp['num_entries'] ?? 0,
                'hits'    => $tmp['num_hits'] ?? 0,
                'misses'  => $tmp['num_misses'] ?? 0,
                'inserts' => $tmp['num_inserts'] ?? 0,
                'mem'     => $memUsed,
                'max'     => $memSize
            ];
        } catch (\Exception) {
            $stats = [];
        }

        return $stats;
    }
}
