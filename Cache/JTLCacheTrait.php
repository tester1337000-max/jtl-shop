<?php

declare(strict_types=1);

namespace JTL\Cache;

use DateTime;

/**
 * Trait JTLCacheTrait
 * @package JTL\Cache
 */
trait JTLCacheTrait
{
    /**
     * @var array{activated: bool, method: string, redis_port: int, redis_pass: string|null,
     *         redis_host: string, redis_db: int|null, redis_persistent: bool, memcache_port: int,
     *         memcache_host: string, prefix: string, lifetime: int, collect_stats: bool, debug: bool,
     *         debug_method: string, cache_dir: string, file_extension: string, page_cache: bool,
     *         types_disabled: string[], redis_user: string|null, rediscluster_hosts: string,
     *         rediscluster_strategy: string}
     */
    public array $options;

    public string $journalID = '';

    /**
     * @var array<string, array<mixed>>|array{}|null
     */
    public ?array $journal = null;

    public bool $isInitialized = false;

    public bool $journalHasChanged = false;

    private string $error = '';

    public static ?ICachingMethod $instance = null;

    /**
     * @inheritdoc
     */
    public static function getInstance(array $options): ICachingMethod
    {
        return self::$instance ?? new self($options);
    }

    /**
     * save the journal to persistent cache
     */
    public function __destruct()
    {
        // save journal on destruct
        if ($this->isInitialized === true && $this->journalHasChanged === true) {
            $this->store($this->journalID, $this->journal, 0);
        }
    }

    /**
     * @param array<mixed> $data
     */
    public function __unserialize(array $data): void
    {
    }

    /**
     * @return array{}
     */
    public function __serialize(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getJournalID(): ?string
    {
        return $this->journalID;
    }

    /**
     * @inheritdoc
     */
    public function setJournalID(string $id): void
    {
        $this->journalID = $id;
    }

    /**
     * @inheritdoc
     */
    public function test(): bool
    {
        // if it's not available, it's not working
        if ($this->isInitialized === false || !$this->isAvailable()) {
            return false;
        }
        // store value to cache and load again
        $cID   = 'jtl_cache_test';
        $value = 'test-value';
        $set   = $this->store($cID, $value, 10);
        $load  = $this->load($cID);
        $flush = $this->flush($cID);
        // loaded value should equal stored value and it should be correctly flushed
        return $value === $load && $set && $flush;
    }

    public function is_serialized(mixed $data): bool
    {
        // if it isn't a string, it isn't serialized
        if (!\is_string($data)) {
            return false;
        }
        $data = \trim($data);
        if ($data === 'N;') {
            return true;
        }
        if (!\preg_match('/^([adObis]):/', $data, $badions)) {
            return false;
        }
        switch ($badions[1]) {
            case 'a':
            case 'O':
            case 's':
                if (\preg_match('/^' . $badions[1] . ':\d+:.*[;}]$/s', $data)) {
                    return true;
                }
                break;
            case 'b':
            case 'i':
            case 'd':
                if (\preg_match('/^' . $badions[1] . ':[\d.E-]+;$/', $data)) {
                    return true;
                }
                break;
        }

        return false;
    }

    /**
     * check if data has to be serialized before storing
     * can be used by caching methods that don't support storing of native php objects/arrays
     */
    public function must_be_serialized(mixed $data): bool
    {
        return \is_object($data) || \is_array($data);
    }

    /**
     * write meta data to journal - for use of cache tags
     *
     * @param string|string[] $tags
     * @param int|string      $cacheID - not prefixed
     * @return bool
     */
    public function writeJournal(array|string $tags, int|string $cacheID): bool
    {
        if ($this->journal === null) {
            $this->getJournal();
        }
        $this->journalHasChanged = true;
        if (\is_string($tags)) {
            $tags = [$tags];
        }
        foreach ($tags as $tag) {
            if (isset($this->journal[$tag])) {
                if (!\in_array($cacheID, $this->journal[$tag], true)) {
                    $this->journal[$tag][] = $cacheID;
                }
            } else {
                $journalEntry        = [];
                $journalEntry[]      = $cacheID;
                $this->journal[$tag] = $journalEntry;
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function getKeysByTag($tags): array
    {
        // load journal from extra cache
        $this->getJournal();
        if (\is_string($tags)) {
            return $this->journal[$tags] ?? [];
        }
        if (\is_array($tags)) {
            $res = [];
            foreach ($tags as $tag) {
                if (isset($this->journal[$tag])) {
                    foreach ($this->journal[$tag] as $cacheID) {
                        $res[] = $cacheID;
                    }
                }
            }
            // remove duplicate keys from array and return it
            return \array_unique($res);
        }

        return [];
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
    public function setCacheTag($tags, $cacheID): bool
    {
        return $this->writeJournal($tags, $cacheID);
    }

    /**
     * @inheritdoc
     */
    public function flushTags($tags): int
    {
        $deleted = 0;
        foreach ($this->getKeysByTag($tags) as $_id) {
            $res = $this->flush($_id);
            $this->clearCacheTags($_id);
            if ($res === true) {
                ++$deleted;
            }
        }

        return $deleted;
    }

    /**
     * clean up journal after deleting cache entries
     *
     * @param string|string[] $tags
     * @return bool
     */
    public function clearCacheTags(array|string $tags): bool
    {
        if (\is_array($tags)) {
            foreach ($tags as $tag) {
                $this->clearCacheTags($tag);
            }
        }
        $this->getJournal();
        // avoid infinite loops
        if ($tags === $this->journalID || $this->journal === null) {
            return false;
        }
        // load meta data
        foreach ($this->journal as $tagName => $value) {
            // search for key in meta values
            if (($index = \array_search($tags, $value, true)) !== false) {
                unset($this->journal[$tagName][$index]);
                if (\count($this->journal[$tagName]) === 0) {
                    // remove empty tag nodes
                    unset($this->journal[$tagName]);
                }
            }
        }
        // write back journal
        $this->journalHasChanged = true;

        return true;
    }

    /**
     * load journal
     *
     * @return array<string, array<mixed>>|array{}
     */
    public function getJournal(): array
    {
        if ($this->journal === null) {
            $this->journal = ($j = $this->load($this->journalID)) !== false
                ? ($j ?? [])
                : [];
        }

        return $this->journal;
    }

    /**
     * adds prefixes to array of cache IDs
     *
     * @param array<string, mixed> $array
     * @return array<string, mixed>
     */
    protected function prefixArray(array $array): array
    {
        $newKeyArray = [];
        foreach ($array as $_key => $_val) {
            $newKey               = $this->options['prefix'] . $_key;
            $newKeyArray[$newKey] = $_val;
        }

        return $newKeyArray;
    }

    /**
     * removes prefixes from result array of cached keys/values
     *
     * @param string[] $array
     * @return string[]
     */
    protected function dePrefixArray(array $array): array
    {
        $newKeyArray = [];
        foreach ($array as $_key => $_val) {
            $newKey               = \str_replace($this->options['prefix'], '', $_key);
            $newKeyArray[$newKey] = $_val;
        }

        return $newKeyArray;
    }

    protected function secondsToTime(int|string $seconds): string
    {
        $dtF = new DateTime('@0');
        $dtT = new DateTime('@' . $seconds);

        return $dtF->diff($dtT)->format(
            '%a ' . \__('days') . ', %h' . \__('hours') . ', %i ' . \__('minutes') . ', %s ' . \__('seconds')
        );
    }

    /**
     * @inheritdoc
     */
    public function isInitialized(): bool
    {
        return $this->isInitialized;
    }

    public function setIsInitialized(bool $initialized): void
    {
        $this->isInitialized = $initialized;
    }

    /**
     * @inheritdoc
     */
    public function getError(): string
    {
        return $this->error;
    }

    /**
     * @inheritdoc
     */
    public function setError(string $error): void
    {
        $this->error = $error;
    }

    /**
     * @return array{activated: bool, method: string, redis_port: int, redis_pass: string|null,
     *       redis_host: string, redis_db: int|null, redis_persistent: bool, memcache_port: int,
     *       memcache_host: string, prefix: string, lifetime: int, collect_stats: bool, debug: bool,
     *       debug_method: string, cache_dir: string, file_extension: string, page_cache: bool,
     *       types_disabled: string[], redis_user: string|null, rediscluster_hosts: string,
     *       rediscluster_strategy: string}
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param array{activated: bool, method: string, redis_port: int, redis_pass: string|null,
     *      redis_host: string, redis_db: int|null, redis_persistent: bool, memcache_port: int,
     *      memcache_host: string, prefix: string, lifetime: int, collect_stats: bool, debug: bool,
     *      debug_method: string, cache_dir: string, file_extension: string, page_cache: bool,
     *      types_disabled: string[], redis_user: string|null, rediscluster_hosts: string,
     *      rediscluster_strategy: string} $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }
}
