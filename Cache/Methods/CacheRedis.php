<?php

declare(strict_types=1);

namespace JTL\Cache\Methods;

use Exception;
use JTL\Cache\ICachingMethod;
use JTL\Cache\JTLCacheTrait;
use JTL\Shop;
use Redis;
use RedisException;

/**
 * Class CacheRedis
 * Implements caching via phpredis
 *
 * @see https://github.com/nicolasff/phpredis
 * @package JTL\Cache\Methods
 */
class CacheRedis implements ICachingMethod
{
    use JTLCacheTrait;

    private ?Redis $redis = null;

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
        if ($this->isAvailable()) {
            $res = $this->setRedis(
                $options['redis_host'],
                (int)$options['redis_port'],
                $options['redis_pass'],
                $options['redis_user'] ?? null,
                (int)$options['redis_db'],
                $options['redis_persistent']
            );
        }
        if ($res !== false) {
            $this->setIsInitialized(true);
            self::$instance = $this;
        } else {
            $this->redis = null;
            $this->setIsInitialized(false);
        }
    }

    private function setRedis(
        ?string $host = null,
        ?int $port = null,
        ?string $pass = null,
        ?string $user = null,
        ?int $database = null,
        bool $persist = false
    ): bool {
        $redis   = new Redis();
        $connect = $persist === false ? 'connect' : 'pconnect';
        if ($host === null) {
            return false;
        }
        try {
            $res = ($port !== null && $host[0] !== '/')
                ? $redis->$connect($host, $port, \REDIS_CONNECT_TIMEOUT)
                : $redis->$connect($host); // for connecting to socket
        } catch (RedisException $e) {
            $this->setError($e->getMessage());
            $res = false;
        }
        if ($pass !== null && $pass !== '') {
            try {
                if ($user !== null && $user !== '') {
                    $res = $redis->auth([$user, $pass]);
                } else {
                    $res = $redis->auth($pass);
                }
            } catch (RedisException $e) {
                $this->setError($e->getMessage());
                $res = false;
            }
        }
        if ($database !== null) {
            try {
                $res = $redis->select($database);
            } catch (RedisException $e) {
                $this->setError($e->getMessage());
                $res = false;
            }
        }
        if ($res === false) {
            return false;
        }
        $this->setError('');
        // set custom prefix
        $redis->setOption(Redis::OPT_PREFIX, $this->options['prefix']);
        // set php serializer for objects and arrays
        $redis->setOption(Redis::OPT_SERIALIZER, (string)Redis::SERIALIZER_PHP);
        $this->redis = $redis;

        return true;
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
            $res = $this->redis->set($cacheID, $content);
            $exp = $expiration ?? $this->options['lifetime'];
            // the journal and negative expiration values should not cause an expiration
            if ($cacheID !== $this->journalID && $exp > -1) {
                $this->redis->expire($cacheID, $exp);
            }

            return \is_bool($res) ? $res : false;
        } catch (RedisException $e) {
            $this->handleException($e);

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
            $res = $this->redis->mSet($idContent);
            foreach (\array_keys($idContent) as $_cacheID) {
                $this->redis->expire($_cacheID, $expiration ?? $this->options['lifetime']);
            }

            return $res;
        } catch (RedisException $e) {
            $this->handleException($e);

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
        $cacheID = (string)$cacheID;
        try {
            return $this->redis->get($cacheID);
        } catch (RedisException $e) {
            $this->handleException($e);

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
        try {
            $res    = $this->redis->mGet($cacheIDs);
            $i      = 0;
            $return = [];
            foreach ($res as $_val) {
                $return[$cacheIDs[$i]] = $_val;
                ++$i;
            }

            return $return;
        } catch (RedisException $e) {
            $this->handleException($e);

            return [];
        }
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
     * @param string|string[] $cacheID
     */
    public function flush($cacheID): bool
    {
        if ($this->redis === null) {
            return false;
        }
        try {
            $res = $this->redis->del($cacheID);

            return \is_numeric($res) && $res > 0;
        } catch (RedisException $e) {
            $this->handleException($e);

            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function setCacheTag($tags, $cacheID): bool
    {
        if ($this->redis === null) {
            return false;
        }
        $res   = false;
        $redis = $this->redis->multi();
        if (\is_string($tags)) {
            $tags = [$tags];
        }
        if (\count($tags) > 0) {
            foreach ($tags as $tag) {
                $redis->sAdd(self::keyFromTagName($tag), $cacheID);
            }
            $redis->exec();
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

        return $this->redis->flushDB();
    }

    /**
     * @inheritdoc
     */
    public function getKeysByTag($tags = []): array
    {
        if ($this->redis === null) {
            return [];
        }
        /** @var string[] $matchTags */
        $matchTags = \is_string($tags)
            ? [self::keyFromTagName($tags)]
            : \array_map(self::keyFromTagName(...), $tags);
        $res       = \count($matchTags) === 1
            ? $this->redis->sMembers($matchTags[0])
            : $this->redis->sUnion($matchTags);
        if (\PHP_SAPI === 'srv' || \PHP_SAPI === 'cli') { // for some reason, hhvm does not unserialize values
            foreach ($res as &$_cid) {
                // phpredis will throw an exception when unserializing unserialized data
                try {
                    $_cid = $this->redis->_unserialize($_cid);
                } catch (RedisException) {
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
        $numEntries  = 0;
        $slowLog     = [];
        $slowLogData = [];
        try {
            $stats = $this->redis->info();
        } catch (RedisException $e) {
            $this->handleException($e);

            return [];
        }
        try {
            $slowLog = \method_exists($this->redis, 'slowLog')
                ? $this->redis->slowLog('get', 25)
                : [];
        } catch (RedisException $e) {
            $this->handleException($e);
        }
        $db  = $this->redis?->getDbNum() ?? 0;
        $idx = 'db' . $db;
        if (isset($stats[$idx])) {
            $dbStats = \explode(',', $stats[$idx]);
            foreach ($dbStats as $stat) {
                if (\str_contains($stat, 'keys=')) {
                    $numEntries = (int)\str_replace('keys=', '', $stat);
                }
            }
        }
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

        return [
            'entries'  => $numEntries,
            'uptime'   => $stats['uptime_in_seconds'] ?? null, // uptime in seconds
            'uptime_h' => isset($stats['uptime_in_seconds'])
                ? $this->secondsToTime($stats['uptime_in_seconds'])
                : null, // human readable
            'hits'     => $stats['keyspace_hits'], // cache hits
            'misses'   => $stats['keyspace_misses'], // cache misses
            'hps'      => isset($stats['uptime_in_seconds'])
                ? ($stats['keyspace_hits'] / $stats['uptime_in_seconds'])
                : null, // hits per second
            'mps'      => isset($stats['uptime_in_seconds'])
                ? ($stats['keyspace_misses'] / $stats['uptime_in_seconds'])
                : null, // misses per second
            'mem'      => $stats['used_memory'], // used memory in bytes
            'max'      => $stats['maxmemory'] ?? null,
            'slow'     => $slowLogData // redis slow log
        ];
    }

    /**
     * @throws Exception
     */
    private function handleException(Exception $e): void
    {
        if ($this->options['debug'] === true && $this->options['debug_method'] === 'echo') {
            echo $e->getMessage();

            return;
        }
        try {
            Shop::Container()->getLogService()->error($e->getMessage());
        } catch (Exception) {
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $data                          = \get_object_vars($this);
        $data['options']['redis_pass'] = '*****';

        return $data;
    }
}
