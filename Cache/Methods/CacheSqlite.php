<?php

declare(strict_types=1);

namespace JTL\Cache\Methods;

use JTL\Cache\ICachingMethod;
use JTL\Cache\JTLCacheTrait;
use SQLite3;

/**
 * Class CacheSqlite
 * @package JTL\Cache\Methods
 */
class CacheSqlite implements ICachingMethod
{
    use JTLCacheTrait;

    private string $dbRoot = \PFAD_ROOT . \PFAD_COMPILEDIR;

    private string $dbName = 'cache.sqlite3';

    private ?SQLite3 $db = null;

    private bool $dbIsCreated = false;

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
        if ($this->isAvailable()) {
            $this->db          = new SQLite3($this->dbRoot . $this->dbName);
            $this->dbIsCreated = \filesize($this->dbRoot . $this->dbName) > 5000;

            $this->db->exec(
                'PRAGMA busy_timeout = 5000;
                PRAGMA cache_size = 10000;
                PRAGMA synchronous = OFF;
                PRAGMA foreign_keys = ON;
                PRAGMA temp_store = MEMORY;
                PRAGMA default_temp_store = MEMORY;
                PRAGMA read_uncommitted = true;
                PRAGMA journal_mode = wal;'
            );

            $this->setJournalID('sqlite_journal');
            $this->setOptions($options);
            $this->setIsInitialized(true);
            $this->installDB();
        }
        self::$instance = $this;
    }

    public function __destruct()
    {
        $this->db?->close();
    }

    /**
     * @return array{}
     */
    public function __serialize(): array
    {
        return [];
    }

    /**
     * @param array<mixed> $data
     */
    public function __unserialize(array $data): void
    {
    }

    /**
     * @inheritdoc
     */
    public function store($cacheID, $content, ?int $expiration = null): bool
    {
        $exp  = ($expiration ?? $this->options['lifetime']);
        $data = \serialize($content);
        $stmt = $this->db?->prepare(
            "REPLACE INTO cache (id, value, lifetime)
            VALUES (:id, :value, DATETIME(CURRENT_TIMESTAMP ,'+" . $exp . " Second'))"
        ) ?? false;
        if ($stmt === false) {
            return false;
        }
        $stmt->bindParam(':id', $cacheID);
        $stmt->bindParam(':value', $data, \SQLITE3_BLOB);

        return $stmt->execute() !== false;
    }

    /**
     * @inheritdoc
     */
    public function storeMulti(array $idContent, ?int $expiration = null): bool
    {
        $ret = true;
        foreach ($idContent as $_key => $_value) {
            $ret = $this->store($_key, $_value, $expiration);
        }

        return $ret;
    }

    /**
     * @inheritdoc
     */
    public function load($cacheID)
    {
        $stmt = $this->db?->prepare(
            'SELECT id, value 
            FROM cache 
            WHERE id = :id AND CURRENT_TIMESTAMP < lifetime'
        ) ?? false;
        if ($stmt === false) {
            return false;
        }
        $stmt->bindParam(':id', $cacheID);
        $result = $stmt->execute();
        if ($result !== false && ($ret = $result->fetchArray(\SQLITE3_ASSOC)) !== false) {
            return \unserialize($ret['value']);
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function loadMulti(array $cacheIDs): array
    {
        $res    = [];
        $params = \implode(',', \array_fill(0, \count($cacheIDs), '?'));
        $stmt   = $this->db?->prepare(
            'SELECT id, value 
            FROM cache 
            WHERE id IN (' . $params . ') AND CURRENT_TIMESTAMP < lifetime'
        ) ?? false;
        if ($stmt === false) {
            return $res;
        }
        foreach (\array_values($cacheIDs) as $i => $cacheID) {
            $stmt->bindValue($i + 1, (string)$cacheID);
        }
        $result = $stmt->execute();
        if ($result === false) {
            return $res;
        }
        while ($ary = $result->fetchArray(\SQLITE3_ASSOC)) {
            $res[$ary['id']] = \unserialize($ary['value']);
        }

        return $res;
    }

    /**
     * @inheritdoc
     */
    public function isAvailable(): bool
    {
        return \extension_loaded('pdo_sqlite')
            && \extension_loaded('sqlite3')
            && \class_exists('SQLite3')
            && \is_writable($this->dbRoot);
    }

    /**
     * @inheritdoc
     */
    public function flush($cacheID): bool
    {
        $this->flushExpiredCaches();
        $stmt = $this->db?->prepare('DELETE FROM cache WHERE id = :id') ?? false;
        if ($stmt === false) {
            return false;
        }
        $stmt->bindParam(':id', $cacheID);
        $stmt->execute();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function flushAll(): bool
    {
        $res1 = $this->db?->exec('DELETE FROM cache_tag') ?? false;
        $res2 = $this->db?->exec('DELETE FROM cache') ?? false;
        $this->db?->exec('VACUUM');

        return $res1 && $res2;
    }

    /**
     * @inheritdoc
     */
    public function getStats(): array
    {
        $num    = 0;
        $total  = 0;
        $result = $this->db?->query('SELECT COUNT(*) num FROM cache') ?? false;
        if ($result !== false) {
            $data = $result->fetchArray(\SQLITE3_ASSOC) ?: [];
            $num  = (int)($data['num'] ?? 0);
        }
        if (\file_exists($this->dbRoot . $this->dbName)) {
            $total = \filesize($this->dbRoot . $this->dbName) ?: 0;
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
        $res  = false;
        $tags = (\is_string($tags)) ? [$tags] : $tags;
        foreach ($tags as $tag) {
            $stmt = $this->db?->prepare('REPLACE INTO cache_tag (group_id, id) VALUES (:group_id, :id)') ?? false;
            if ($stmt === false) {
                return false;
            }
            $stmt->bindParam(':group_id', $tag);
            $stmt->bindParam(':id', $cacheID);
            $res = $stmt->execute() !== false;
        }

        return $res;
    }

    /**
     * @inheritdoc
     */
    public function flushTags($tags): int
    {
        $tags   = \is_array($tags) ? \array_values($tags) : [$tags];
        $params = \implode(',', \array_fill(0, \count($tags), '?'));
        $stmt   = $this->db?->prepare(
            'DELETE FROM cache 
                WHERE id IN (SELECT id FROM cache_tag WHERE group_id IN (' . $params . '))'
        ) ?? false;
        if ($stmt === false) {
            return 0;
        }
        foreach ($tags as $i => $tag) {
            $stmt->bindValue($i + 1, $tag);
        }
        $stmt->execute();
        $stmt = $this->db?->prepare('DELETE FROM cache_tag WHERE group_id IN (' . $params . ')') ?? false;
        if ($stmt === false) {
            return 0;
        }
        foreach ($tags as $i => $tag) {
            $stmt->bindValue($i + 1, $tag);
        }
        $stmt->execute();

        return 0;
    }

    /**
     * @inheritdoc
     */
    public function getKeysByTag($tags): array
    {
        $res    = [];
        $tags   = \is_array($tags) ? \array_values($tags) : [$tags];
        $params = \implode(',', \array_fill(0, \count($tags), '?'));
        $stmt   = $this->db?->prepare(
            'SELECT group_id, id FROM cache_tag WHERE group_id IN (' . $params . ')'
        ) ?? false;
        if ($stmt === false) {
            return $res;
        }
        foreach ($tags as $i => $tag) {
            $stmt->bindValue($i + 1, (string)$tag);
        }
        $result = $stmt->execute();
        while ($result !== false && $item = $result->fetchArray(\SQLITE3_ASSOC)) {
            $res[] = $item['id'];
        }

        return $res;
    }

    private function flushExpiredCaches(): bool
    {
        if ($this->dbIsCreated && $this->db !== null) {
            $res1 = $this->db->exec('DELETE FROM cache WHERE CURRENT_TIMESTAMP > lifetime');
            $res2 = $this->db->exec('DELETE FROM cache_tag WHERE id NOT IN (SELECT id FROM cache)');

            return $res1 && $res2;
        }

        return false;
    }

    /**
     * Einrichtung der Cache Datenbank
     */
    private function installDB(): void
    {
        if (!$this->dbIsCreated && $this->db !== null) {
            $this->db->exec(
                'CREATE TABLE IF NOT EXISTS cache (
                    id VARCHAR(64) NOT NULL UNIQUE PRIMARY KEY,
                    value LONGBLOB NOT NULL,
                    lifetime TIME DEFAULT CURRENT_TIMESTAMP
                );
                CREATE INDEX lifetime ON cache (lifetime);
                CREATE TABLE IF NOT EXISTS cache_tag (
                    group_id VARCHAR(32) NOT NULL,
                    id VARCHAR(64) NOT NULL,
                    PRIMARY KEY (group_id, id)
                );'
            );
        }
    }
}
