<?php

declare(strict_types=1);

namespace JTL\Cache\Methods;

use JTL\Cache\ICachingMethod;
use JTL\Cache\JTLCacheTrait;
use JTL\Shop;
use Redis;
use RedisCluster;
use RedisClusterException;

/**
 * Class CacheRedisCluster
 * @package JTL\Cache\Methods
 * Implements caching via phpredis in cluster mode
 *
 * @see https://github.com/nicolasff/phpredis
 */
class CacheRedisCluster implements ICachingMethod
{
    use JTLCacheTrait;

    private ?RedisCluster $redis = null;

    /**
     * @var string[]
     */
    private array $masters = [];

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
        $res = false;
        $this->setJournalID('redis_journal');
        $this->setOptions($options);
        if (isset($options['rediscluster_hosts']) && $this->isAvailable()) {
            $res = $this->setRedisCluster(
                $options['rediscluster_hosts'],
                $options['redis_persistent'],
                (int)$options['rediscluster_strategy'],
                $options['redis_pass']
            );
        }
        $this->setIsInitialized($res);
        self::$instance = $this;
    }

    private function setRedisCluster(
        ?string $hosts = null,
        bool $persist = false,
        int $strategy = 0,
        ?string $pass = null
    ): bool {
        if ($hosts === null) {
            return false;
        }
        try {
            $pass  = $pass !== null && \strlen($pass) > 0 ? $pass : null;
            $redis = new RedisCluster(null, \explode(',', $hosts), 1.5, 1.5, $persist, $pass);
            $redis->setOption(Redis::OPT_PREFIX, $this->options['prefix']);
            // set php serializer for objects and arrays
            $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
            switch ($strategy) {
                case 4:
                    $redis->setOption(RedisCluster::OPT_SLAVE_FAILOVER, RedisCluster::FAILOVER_DISTRIBUTE_SLAVES);
                    break;
                case 3:
                    $redis->setOption(RedisCluster::OPT_SLAVE_FAILOVER, RedisCluster::FAILOVER_DISTRIBUTE);
                    break;
                case 2:
                    $redis->setOption(RedisCluster::OPT_SLAVE_FAILOVER, RedisCluster::FAILOVER_ERROR);
                    break;
                case 1:
                default:
                    $redis->setOption(RedisCluster::OPT_SLAVE_FAILOVER, RedisCluster::FAILOVER_NONE);
                    break;
            }
            $this->masters = $redis->_masters();
            $this->redis   = $redis;
        } catch (RedisClusterException $e) {
            $this->setError($e->getMessage());
        }

        return \count($this->masters) > 0;
    }

    /**
     * @inheritdoc
     */
    public function store($cacheID, $content, ?int $expiration = null): bool
    {
        if ($this->redis === null) {
            return false;
        }
        $cacheID = (string)$cacheID;
        try {
            $exp = $expiration ?? $this->options['lifetime'];

            return $this->redis->set($cacheID, $content, $cacheID !== $this->journalID && $exp > -1 ? $exp : []);
        } catch (RedisClusterException $e) {
            Shop::Container()->getLogService()->error('RedisClusterException: {exc}', ['exc' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function storeMulti(array $idContent, ?int $expiration = null): bool
    {
        if ($this->redis === null) {
            return false;
        }
        try {
            $res = $this->redis->mset($idContent);
            $exp = $expiration ?? $this->options['lifetime'];
            $exp = $exp > -1 ? $exp : null;
            if ($exp !== null) {
                foreach (\array_keys($idContent) as $_cacheID) {
                    $this->redis->expire($_cacheID, $exp);
                }
            }

            return $res;
        } catch (RedisClusterException $e) {
            Shop::Container()->getLogService()->error('RedisClusterException: {exc}', ['exc' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function load($cacheID)
    {
        if ($this->redis === null) {
            return false;
        }
        try {
            return $this->redis->get((string)$cacheID);
        } catch (RedisClusterException $e) {
            Shop::Container()->getLogService()->error('RedisClusterException: {exc}', ['exc' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function loadMulti(array $cacheIDs): array
    {
        if ($this->redis === null) {
            return [];
        }
        $res    = $this->redis->mget($cacheIDs);
        $i      = 0;
        $return = [];
        foreach ($res as $_val) {
            $return[$cacheIDs[$i]] = $_val;
            ++$i;
        }

        return $return;
    }

    /**
     * @inheritdoc
     */
    public function isAvailable(): bool
    {
        return \class_exists('Redis');
    }

    /**
     * @inheritdoc
     */
    public function flush($cacheID): bool
    {
        return $this->redis !== null && $this->redis->del($cacheID) > 0;
    }

    /**
     * @inheritdoc
     */
    public function setCacheTag($tags, $cacheID): bool
    {
        if ($this->redis === null) {
            return false;
        }
        $res     = false;
        $cacheID = (string)$cacheID;
        if (\is_string($tags)) {
            $tags = [$tags];
        }
        if (\count($tags) > 0) {
            foreach ($tags as $tag) {
                $this->redis->sAdd(self::keyFromTagName($tag), $cacheID);
            }
            $res = true;
        }

        return $res;
    }

    private static function keyFromTagName(int|string $tagName): string
    {
        return 'tag_' . $tagName;
    }

    /**
     * @inheritdoc
     */
    public function flushTags($tags): int
    {
        $tagged = \array_unique($this->getKeysByTag($tags));
        $tags   = \is_string($tags)
            ? [self::keyFromTagName($tags)]
            : \array_map(self::keyFromTagName(...), $tags);

        return $this->flush(\array_merge($tags, $tagged)) ? \count($tags) : 0;
    }

    /**
     * @inheritdoc
     */
    public function flushAll(): bool
    {
        if ($this->redis === null) {
            return false;
        }
        foreach ($this->masters as $master) {
            $this->redis->flushDB($master);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function getKeysByTag($tags = []): array
    {
        if ($this->redis === null) {
            return [];
        }
        $matchTags = \is_string($tags)
            ? [self::keyFromTagName($tags)]
            : \array_map(self::keyFromTagName(...), $tags);
        $res       = \count($tags) === 1
            ? $this->redis->sMembers($matchTags[0])
            : $this->redis->sUnion($matchTags);
        if (\PHP_SAPI === 'srv' || \PHP_SAPI === 'cli') { // for some reason, hhvm does not unserialize values
            foreach ($res as &$_cid) {
                // phpredis will throw an exception when unserializing unserialized data
                try {
                    $_cid = $this->redis->_unserialize($_cid);
                } catch (RedisClusterException) {
                    // we know we don't have to continue unserializing when there was an exception
                    break;
                }
            }
        }

        return \is_array($res) ? $res : [];
    }

    /**
     * @inheritdoc
     */
    public function keyExists($key): bool
    {
        return (bool)$this->redis?->exists((string)$key);
    }

    /**
     * @inheritdoc
     */
    public function getStats(): array
    {
        if ($this->redis === null) {
            return [];
        }
        $numEntries  = [];
        $uptimes     = [];
        $stats       = [];
        $mem         = [];
        $slowLogs    = [];
        $slowLogData = [];
        $hits        = [];
        $misses      = [];
        $hps         = [];
        $mps         = [];
        try {
            foreach ($this->masters as $master) {
                $stats[]    = $this->redis->info($master);
                $slowLogs[] = \method_exists($this->redis, 'slowLog')
                    ? $this->redis->slowLog($master, 'get', 25)
                    : [];
            }
        } catch (RedisClusterException $e) {
            Shop::Container()->getLogService()->error('RedisClusterException: {exc}', ['exc' => $e->getMessage()]);

            return [];
        }
        $idx = 'db0';
        /** @var array<string, mixed> $stat */
        foreach ($stats as $stat) {
            $uptimes[] = $stat['uptime_in_seconds'] ?? 0;
            $hits[]    = $stat['keyspace_hits'];
            $misses[]  = $stat['keyspace_misses'];
            $mem[]     = $stat['used_memory'];
            $hps[]     = $stat['uptime_in_seconds'] > 0 ? $stat['keyspace_hits'] / $stat['uptime_in_seconds'] : 0;
            $mps[]     = $stat['uptime_in_seconds'] > 0 ? $stat['keyspace_misses'] / $stat['uptime_in_seconds'] : 0;
            if (isset($stat[$idx])) {
                $dbStats = \explode(',', $stat[$idx]);
                foreach ($dbStats as $dbStat) {
                    if (\str_contains($dbStat, 'keys=')) {
                        $numEntries[] = \str_replace('keys=', '', $dbStat);
                    }
                }
            }
        }
        foreach ($slowLogs as $slowLog) {
            /** @var array{0: int, 1: int, 2: int, 3: array{int, string}, 4: string, 5: string} $_slow */
            foreach ($slowLog as $_slow) {
                $slowLogDataEntry = [];
                if (isset($_slow[1])) {
                    $slowLogDataEntry['date'] = \date('d.m.Y H:i:s', $_slow[1]);
                }
                if (isset($_slow[3][0])) {
                    $slowLogDataEntry['cmd'] = $_slow[3][0];
                }
                if (isset($_slow[2]) && $_slow[2] > 0) {
                    $slowLogDataEntry['exec_time'] = ($_slow[2] / 1000000);
                }
                $slowLogData[] = $slowLogDataEntry;
            }
        }

        return [
            'entries'  => \implode('/', $numEntries),
            'uptime'   => \implode('/', $uptimes), // uptime in seconds
            'uptime_h' => \implode('/', \array_map($this->secondsToTime(...), $uptimes)), // human readable
            'hits'     => \implode('/', $hits), // cache hits
            'misses'   => \implode('/', $misses), // cache misses
            'hps'      => \implode('/', $hps), // hits per second
            'mps'      => \implode('/', $mps), // misses per second
            'mem'      => \implode('/', $mem), // used memory in bytes
            'slow'     => $slowLogData // redis slow log
        ];
    }
}
