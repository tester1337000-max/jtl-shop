<?php

declare(strict_types=1);

namespace JTL\Cache;

use JTL\Cache\Methods\CacheNull;
use JTL\Helpers\Request;
use JTL\Profiler;
use JTL\Session\Frontend;
use JTL\Shop;

/**
 * Class JTLCache
 * @package JTL\Cache
 */
final class JTLCache implements JTLCacheInterface
{
    /**
     * default port for redis caching method
     */
    public const DEFAULT_REDIS_PORT = 6379;

    /**
     * default host name for redis caching method
     */
    public const DEFAULT_REDIS_HOST = 'localhost';

    /**
     * default memcache(d) port
     */
    public const DEFAULT_MEMCACHE_PORT = 11211;

    /**
     * default memcache(d) host name
     */
    public const DEFAULT_MEMCACHE_HOST = 'localhost';

    /**
     * default cache life time in seconds (86400 = 1 day)
     */
    public const DEFAULT_LIFETIME = 86400;

    /**
     * result code for successful getting result from cache
     */
    public const RES_SUCCESS = 1;

    /**
     * result code for cache miss
     */
    public const RES_FAIL = 2;

    /**
     * result code when getting multiple values at once
     */
    public const RES_UNDEF = 3;

    private ICachingMethod $method;

    /**
     * caching options
     *
     * @var array{activated: bool, method: string, redis_port: int, redis_pass: string|null,
     *            redis_host: string, redis_db: int|null, redis_persistent: bool, memcache_port: int,
     *            memcache_host: string, prefix: string, lifetime: int, collect_stats: bool, debug: bool,
     *            debug_method: string, cache_dir: string, file_extension: string, page_cache: bool,
     *            types_disabled: string[], redis_user: string|null, rediscluster_hosts: string,
     *            rediscluster_strategy: string}
     */
    private array $options;

    private int $resultCode = self::RES_UNDEF;

    private string $error = '';

    /**
     * @var array{name: string, nicename: string, value: string, description: string}[]
     */
    private const CACHING_GROUPS = [
        [
            'name'        => 'CACHING_GROUP_ARTICLE',
            'nicename'    => 'cg_article_nicename',
            'value'       => \CACHING_GROUP_ARTICLE,
            'description' => 'cg_article_description'
        ],
        [
            'name'        => 'CACHING_GROUP_CATEGORY',
            'nicename'    => 'cg_category_nicename',
            'value'       => \CACHING_GROUP_CATEGORY,
            'description' => 'cg_category_description'
        ],
        [
            'name'        => 'CACHING_GROUP_SHIPPING',
            'nicename'    => 'cg_shipping_nicename',
            'value'       => \CACHING_GROUP_SHIPPING,
            'description' => 'cg_shipping_description'
        ],
        [
            'name'        => 'CACHING_GROUP_LANGUAGE',
            'nicename'    => 'cg_language_nicename',
            'value'       => \CACHING_GROUP_LANGUAGE,
            'description' => 'cg_language_description'
        ],
        [
            'name'        => 'CACHING_GROUP_TEMPLATE',
            'nicename'    => 'cg_template_nicename',
            'value'       => \CACHING_GROUP_TEMPLATE,
            'description' => 'cg_template_description'
        ],
        [
            'name'        => 'CACHING_GROUP_OPTION',
            'nicename'    => 'cg_option_nicename',
            'value'       => \CACHING_GROUP_OPTION,
            'description' => 'cg_option_description'
        ],
        [
            'name'        => 'CACHING_GROUP_PLUGIN',
            'nicename'    => 'cg_plugin_nicename',
            'value'       => \CACHING_GROUP_PLUGIN,
            'description' => 'cg_plugin_description'
        ],
        [
            'name'        => 'CACHING_GROUP_CORE',
            'nicename'    => 'cg_core_nicename',
            'value'       => \CACHING_GROUP_CORE,
            'description' => 'cg_core_description'
        ],
        [
            'name'        => 'CACHING_GROUP_OBJECT',
            'nicename'    => 'cg_object_nicename',
            'value'       => \CACHING_GROUP_OBJECT,
            'description' => 'cg_object_description'
        ],
        [
            'name'        => 'CACHING_GROUP_BOX',
            'nicename'    => 'cg_box_nicename',
            'value'       => \CACHING_GROUP_BOX,
            'description' => 'cg_box_description'
        ],
        [
            'name'        => 'CACHING_GROUP_NEWS',
            'nicename'    => 'cg_news_nicename',
            'value'       => \CACHING_GROUP_NEWS,
            'description' => 'cg_news_description'
        ],
        [
            'name'        => 'CACHING_GROUP_ATTRIBUTE',
            'nicename'    => 'cg_attribute_nicename',
            'value'       => \CACHING_GROUP_ATTRIBUTE,
            'description' => 'cg_attribute_description'
        ],
        [
            'name'        => 'CACHING_GROUP_MANUFACTURER',
            'nicename'    => 'cg_manufacturer_nicename',
            'value'       => \CACHING_GROUP_MANUFACTURER,
            'description' => 'cg_manufacturer_description'
        ],
        [
            'name'        => 'CACHING_GROUP_FILTER',
            'nicename'    => 'cg_filter_nicename',
            'value'       => \CACHING_GROUP_FILTER,
            'description' => 'cg_filter_description'
        ],
        [
            'name'        => 'CACHING_GROUP_STATUS',
            'nicename'    => 'cg_status_nicename',
            'value'       => \CACHING_GROUP_STATUS,
            'description' => 'cg_status_description'
        ],
        [
            'name'        => 'CACHING_GROUP_OPC',
            'nicename'    => 'cg_opc_nicename',
            'value'       => \CACHING_GROUP_OPC,
            'description' => 'cg_opc_description'
        ],
    ];

    /**
     * init cache and set default method
     *
     * @param array{}|array{activated: bool, method: string, redis_port: int, redis_pass: string|null,
     *            redis_host: string, redis_db: int|null, redis_persistent: bool, memcache_port: int,
     *            memcache_host: string, prefix: string, lifetime: int, collect_stats: bool, debug: bool,
     *            debug_method: string, cache_dir: string, file_extension: string, page_cache: bool,
     *            types_disabled: string[], redis_user: string|null, rediscluster_hosts: string,
     *            rediscluster_strategy: string} $options
     */
    public function __construct(array $options = [])
    {
        $this->setOptions($options);
        $this->setMethod(CacheNull::getInstance($options));
    }

    /**
     * @inheritdoc
     */
    public function getCachingGroups(): array
    {
        return self::CACHING_GROUPS;
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
     * @inheritdoc
     */
    public function setOptions(array $options = []): JTLCacheInterface
    {
        // merge defaults with assigned options and set them
        $this->options = \array_merge($this->getDefaultOptions(), $options);
        // always add trailing slash
        if (!\str_ends_with($this->options['cache_dir'], '/')) {
            $this->options['cache_dir'] .= '/';
        }
        if ($this->options['method'] !== 'redis' && $this->options['lifetime'] < 0) {
            $this->options['lifetime'] = 0;
        }
        // accept only valid integer lifetime values
        $this->options['lifetime']       = ($this->options['lifetime'] === '' || $this->options['lifetime'] === 0)
            ? self::DEFAULT_LIFETIME
            : (int)$this->options['lifetime'];
        $this->options['redis_db']       = (int)$this->options['redis_db'];
        $this->options['redis_port']     = (int)$this->options['redis_port'];
        $this->options['memcache_port']  = (int)$this->options['memcache_port'];
        $this->options['types_disabled'] = $this->options['types_disabled'] ?? [];

        return $this;
    }

    /**
     * @return array{activated: bool, method: string, redis_port: int, redis_pass: string|null,
     *           redis_host: string, redis_db: int|null, redis_persistent: bool, memcache_port: int,
     *           memcache_host: string, prefix: string, lifetime: int, collect_stats: bool, debug: bool,
     *           debug_method: string, cache_dir: string, file_extension: string, page_cache: bool,
     *           types_disabled: string[], redis_user: string|null, rediscluster_hosts: string,
     *           rediscluster_strategy: string}
     */
    protected function getDefaultOptions(): array
    {
        return [
            'activated'             => false, // main switch
            'method'                => 'null', // caching method to use
            'redis_port'            => self::DEFAULT_REDIS_PORT, // port of redis server
            'redis_pass'            => null, // password for redis server
            'redis_host'            => self::DEFAULT_REDIS_HOST, // host of redis server
            'redis_db'              => null, // optional redis database id, null or 0 for default
            'redis_persistent'      => false, // optional redis database id, null or 0 for default
            'memcache_port'         => self::DEFAULT_MEMCACHE_PORT, // port for memcache(d) server
            'memcache_host'         => self::DEFAULT_MEMCACHE_HOST, // host of memcache(d) server
            'prefix'                => $this->getPrefix(), // try to make a unique prefix if multiple shops are used
            'lifetime'              => self::DEFAULT_LIFETIME, // cache lifetime in seconds
            'collect_stats'         => false,
            'debug'                 => false, // enable or disable collecting of debug data
            'debug_method'          => 'echo', // 'ssd'/'jtld' for SmarterSmartyDebug/JTLDebug, 'echo' for direct echo
            'cache_dir'             => \OBJECT_CACHE_DIR, // file cache directory
            'file_extension'        => '.fc', // file extension for file cache
            'page_cache'            => false, // smarty page cache switch
            'types_disabled'        => [], // disabled cache groups
            'redis_user'            => null,
            'rediscluster_hosts'    => '',
            'rediscluster_strategy' => '1'
        ];
    }

    protected function getPrefix(): string
    {
        return \defined('CACHE_PREFIX')
            ? \CACHE_PREFIX
            : 'j_' . \APPLICATION_VERSION . '_' . (\defined('DB_NAME') ? \DB_NAME . '_' : '');
    }

    /**
     * @inheritdoc
     */
    public function setCache(string $methodName): bool
    {
        if (\SAFE_MODE !== true) {
            /** @var class-string<ICachingMethod> $class */
            $class = 'JTL\Cache\Methods\Cache' . \ucfirst($methodName);
            $cache = \class_exists($class) ? new $class($this->options) : null;
            /** @var ICachingMethod $class */
            if ($cache instanceof ICachingMethod) {
                $this->setError($cache->getError());
                if ($cache->isInitialized() && $cache->isAvailable()) {
                    $this->setMethod($cache);

                    return true;
                }
            }
        }
        $this->setMethod(CacheNull::getInstance($this->options));

        return false;
    }

    private function setMethod(ICachingMethod $method): JTLCacheInterface
    {
        $this->method = $method;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getJtlCacheConfig(array $config): array
    {
        $cacheInit = [];
        foreach ($config as $_conf) {
            if (\in_array($_conf->cWert, ['Y', 'y'], true)) {
                $value = true;
            } elseif (\in_array($_conf->cWert, ['N', 'n'], true)) {
                $value = false;
            } elseif ($_conf->cWert === '') {
                $value = null;
            } else {
                $value = $_conf->cWert;
            }
            // naming convention is 'caching_'<var-name> for options saved in database
            $cacheInit[\str_replace('caching_', '', $_conf->cName)] = $value;
        }
        // disabled cache types are saved as serialized string in db
        $disabledTypes = $cacheInit['types_disabled'] ?? '';
        if (\is_string($disabledTypes) && $disabledTypes !== '') {
            $cacheInit['types_disabled'] = \unserialize($disabledTypes, ['allowed_classes' => false]);
        }

        return $cacheInit;
    }

    /**
     * @inheritdoc
     */
    public function setJtlCacheConfig(array $config): JTLCacheInterface
    {
        $this->setOptions($this->getJtlCacheConfig($config))->init();

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function init(): JTLCacheInterface
    {
        if ($this->options['activated'] === true) {
            // set the configure caching method
            $this->setCache($this->options['method']);
        } else {
            // set fallback null method
            $this->setCache('null');
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @inheritdoc
     */
    public function get($cacheID, ?callable $callback = null, $customData = null)
    {
        $res              = $this->options['activated'] === true
            ? $this->method->load($cacheID)
            : false;
        $this->resultCode = ($res !== false || $this->method->keyExists($cacheID))
            ? self::RES_SUCCESS
            : self::RES_FAIL;
        $this->debug((string)$cacheID, $this->resultCode === self::RES_SUCCESS, 'get');
        if ($callback !== null && $this->resultCode !== self::RES_SUCCESS) {
            $content    = null;
            $tags       = null;
            $expiration = null;
            $res        = \call_user_func_array(
                $callback,
                [$this, $cacheID, &$content, &$tags, &$expiration, $customData]
            );
            if ($res === true) {
                $this->set($cacheID, $content, $tags, $expiration);

                return $content;
            }
        }

        return $res;
    }

    /**
     * @inheritdoc
     */
    public function set($cacheID, $content, $tags = null, ?int $expiration = null): bool
    {
        $res = false;
        if ($this->options['activated'] === true && $this->isCacheGroupActive($tags) === true) {
            $res = $this->method->store($cacheID, $content, $expiration);
            if ($res === true && $tags !== null) {
                $this->setCacheTag($tags, $cacheID);
            }
        }
        $this->debug((string)$cacheID, $res, 'set');
        $this->resultCode = $res === false ? self::RES_FAIL : self::RES_SUCCESS;

        return $res;
    }

    /**
     * @inheritdoc
     */
    public function setMulti(array $keyValue, ?array $tags = null, ?int $expiration = null): bool
    {
        if ($this->options['activated'] === true && $this->isCacheGroupActive($tags) === true) {
            $res = $this->method->storeMulti($keyValue, $expiration);
            if ($res === true && $tags !== null) {
                foreach (\array_keys($keyValue) as $_cacheID) {
                    $this->setCacheTag($tags, $_cacheID);
                }
            }
            $this->resultCode = self::RES_UNDEF; // for now, let's not check every part of the result

            return $res;
        }
        $this->resultCode = self::RES_FAIL;

        return false;
    }

    /**
     * @inheritdoc
     */
    public function getMulti(array $cacheIDs): array
    {
        $this->resultCode = self::RES_UNDEF; // for now, let's not check every part of the result

        return $this->method->loadMulti($cacheIDs);
    }

    /**
     * @inheritdoc
     */
    public function isCacheGroupActive($groupID): bool
    {
        if ($this->options['activated'] === false) {
            // if the cache is disabled, every tag is inactive
            return false;
        }
        if (
            \is_string($groupID)
            && \is_array($this->options['types_disabled'])
            && \in_array($groupID, $this->options['types_disabled'], true)
        ) {
            return false;
        }
        if (\is_array($groupID)) {
            foreach ($groupID as $group) {
                if (\in_array($group, $this->options['types_disabled'], true)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function getKeysByTag($tags): array
    {
        return $this->method->getKeysByTag($tags);
    }

    /**
     * @inheritdoc
     */
    public function setCacheTag($tags, $cacheID): bool
    {
        return $this->options['activated'] === true && $this->method->setCacheTag($tags, $cacheID);
    }

    /**
     * @inheritdoc
     */
    public function setCacheLifetime(int $lifetime): JTLCacheInterface
    {
        $this->options['lifetime'] = $lifetime > 0 ? $lifetime : self::DEFAULT_LIFETIME;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setCacheDir(string $dir): JTLCacheInterface
    {
        $this->options['cache_dir'] = $dir;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getActiveMethod(): ICachingMethod
    {
        return $this->method;
    }

    /**
     * @inheritdoc
     */
    public function flush($cacheID = null, $tags = null, $hookInfo = null)
    {
        $res = false;
        if ($cacheID !== null && $tags === null) {
            $res = $this->options['activated'] === true && $this->method->flush($cacheID);
        } elseif ($tags !== null) {
            $res = $this->flushTags($tags, $hookInfo);
        }
        $this->debug((string)$cacheID, \is_int($res) ? $res > 0 : $res, 'flush');
        if ($hookInfo !== null && \defined('HOOK_CACHE_FLUSH_AFTER')) {
            \executeHook(\HOOK_CACHE_FLUSH_AFTER, $hookInfo);
        }
        $this->resultCode = \is_int($res) ? self::RES_FAIL : self::RES_SUCCESS;

        return $res;
    }

    /**
     * @inheritdoc
     */
    public function flushTags($tags, $hookInfo = null): int
    {
        $deleted = $this->method->flushTags(\is_array($tags) ? $tags : [$tags]);
        if ($hookInfo !== null && \defined('HOOK_CACHE_FLUSH_AFTER')) {
            \executeHook(\HOOK_CACHE_FLUSH_AFTER, $hookInfo);
        }

        return $deleted;
    }

    /**
     * @inheritdoc
     */
    public function flushAll(): bool
    {
        return $this->method->flushAll();
    }

    /**
     * @inheritdoc
     */
    public function getResultCode(): int
    {
        return $this->resultCode;
    }

    /**
     * @inheritdoc
     */
    public function getJournal(): array
    {
        return $this->method->getJournal();
    }

    /**
     * @inheritdoc
     */
    public function getStats(): array
    {
        return $this->method->getStats();
    }

    /**
     * @inheritdoc
     */
    public function testMethod(): bool
    {
        return $this->method->test();
    }

    /**
     * @inheritdoc
     */
    public function isAvailable(): bool
    {
        return $this->method->isAvailable();
    }

    /**
     * @inheritdoc
     */
    public function isActive(): bool
    {
        return (bool)$this->options['activated'];
    }

    /**
     * @inheritdoc
     */
    public function getAllMethods(): array
    {
        return [
            'advancedfile',
            'apc',
            'file',
            'memcache',
            'memcached',
            'null',
            'redis',
            'redisCluster',
            'sqlite'
        ];
    }

    /**
     * @inheritdoc
     */
    public function checkAvailability(): array
    {
        $available = [];
        foreach ($this->getAllMethods() as $methodName) {
            $class = 'JTL\Cache\Methods\Cache' . \ucfirst($methodName);
            /** @var ICachingMethod $instance */
            $instance               = new $class($this->options);
            $available[$methodName] = [
                'available'  => $instance->isAvailable(),
                'functional' => $instance->test()
            ];
        }

        return $available;
    }

    /**
     * @inheritdoc
     */
    public function getBaseID(
        bool $hash = false,
        $customerID = false,
        $customerGroup = true,
        $languageID = true,
        $currencyID = true,
        bool $sslStatus = true
    ): string {
        $baseID = 'b';
        // add customer ID
        if ($customerID === true) {
            $baseID .= '_cid';
            $baseID .= $_SESSION['Kunde']->kKunde ?? '-1';
        } elseif (\is_numeric($customerID)) {
            $baseID .= '_cid' . (int)$customerID;
        }
        // add customer group
        if ($customerGroup === true) {
            $baseID .= '_cgid' . Frontend::getCustomerGroup()->getID();
        } elseif (\is_numeric($customerGroup)) {
            $baseID .= '_cgid' . (int)$customerGroup;
        }
        // add language ID
        if ($languageID === true) {
            $baseID .= '_lid';

            $lang = Shop::getLanguageID();
            if ($lang > 0) {
                $baseID .= $lang;
            } elseif (Shop::getLanguageID() > 0) {
                $baseID .= Shop::getLanguageID();
            } else {
                $baseID .= '0';
            }
        } elseif (\is_numeric($languageID)) {
            $baseID .= '_lid' . (int)$languageID;
        }
        // add currency ID
        if ($currencyID === true) {
            $baseID .= '_curid' . Frontend::getCurrency()->getID();
        } elseif (\is_numeric($currencyID)) {
            $baseID .= '_curid' . (int)$currencyID;
        }
        // add current SSL status
        if ($sslStatus === true) {
            $baseID .= '_ssl' . Request::checkSSL();
        }

        return $hash === true ? \md5($baseID) : $baseID;
    }

    /**
     * @inheritdoc
     */
    public function benchmark(
        $methods = 'all',
        $testData = 'simple string',
        int $runCount = 1000,
        int $repeat = 1,
        bool $echo = true,
        bool $format = false
    ): array {
        $this->options['activated'] = true;
        $this->options['lifetime']  = self::DEFAULT_LIFETIME;
        // sanitize input
        $runCount = \max($runCount, 1);
        $repeat   = \max($repeat, 1);
        $results  = [];
        if ($methods === 'all') {
            $methods = $this->getAllMethods();
        }
        if (\is_array($methods)) {
            foreach ($methods as $method) {
                if ($method !== 'null') {
                    $results[] = $this->benchmark($method, $testData, $runCount, $repeat, $echo, $format);
                }
            }
        } else {
            return $this->runBenchmark($methods, $testData, $echo, $repeat, $runCount, $format);
        }

        return $results;
    }

    /**
     * @return array{method: string, status: 'invalid'|'ok'|'failed',
     *      timings: array{get: float|string, set: float|string},
     *      rps?: array{get: string|float, set: string|float}}
     */
    private function runBenchmark(
        string $method,
        mixed $testData,
        bool $echo,
        int $repeat,
        int $runCount,
        bool $format
    ): array {
        $timesSet         = 0.0;
        $timesGet         = 0.0;
        $cacheSetRes      = $this->setCache($method);
        $testDataIsObject = \is_object($testData);
        $validResults     = true;
        $result           = [
            'method'  => $method,
            'status'  => 'ok',
            'timings' => ['get' => 0.0, 'set' => 0.0]
        ];
        $this->benchOutput($echo, '### Testing ' . $method . ' cache ###');
        if ($cacheSetRes === false) {
            $this->benchOutput($echo, '<br />Caching method not supported by server<br /><br />');
            $result['status'] = 'failed';

            return $result;
        }
        for ($i = 0; $i < $repeat; ++$i) {
            // set testing
            $start = \microtime(true);
            for ($j = 0; $j < $runCount; ++$j) {
                $this->set('c_' . $j, $testData);
            }
            $timesSet += (\microtime(true) - $start);
            // get testing
            $start = \microtime(true);
            for ($j = 0; $j < $runCount; ++$j) {
                $read = $this->get('c_' . $j);
                if ($testDataIsObject && \is_object($read)) {
                    continue;
                }
                if ($read !== $testData) {
                    $validResults = false;
                }
            }
            $timesGet += (\microtime(true) - $start);
        }
        if ($timesSet > 0.0 && $timesGet > 0.0 && $validResults !== false) {
            // calculate averages
            $rpsGet = (float)($runCount * $repeat / $timesGet);
            $rpsSet = (float)($runCount * $repeat / $timesSet);

            $timesSet /= $repeat;
            $timesGet /= $repeat;
            if ($format === true) {
                $timesSet = \number_format($timesSet, 4, ',', '.');
                $timesGet = \number_format($timesGet, 4, ',', '.');
                $rpsSet   = \number_format($rpsSet, 2, ',', '.');
                $rpsGet   = \number_format($rpsGet, 2, ',', '.');
            }
            // output averages
            $this->benchOutput(
                $echo,
                \sprintf('<br />Avg. time for setting: %s (%s requests per second)', $timesSet, $rpsSet)
            );
            $this->benchOutput(
                $echo,
                \sprintf('<br> Avg. time for getting: %s (%s requests per second)', $timesGet, $rpsGet)
            );

            $result['timings'] = ['get' => $timesGet, 'set' => $timesSet];
            $result['rps']     = ['get' => $rpsGet, 'set' => $rpsSet];
        }
        if ($validResults === false) {
            $this->benchOutput($echo, '<br />Got invalid results when loading cached values!');
            $result['status'] = 'invalid';
        }
        $this->benchOutput($echo, '<br /><br />');

        return $result;
    }

    private function benchOutput(bool $echo, string $msg): void
    {
        if ($echo === true) {
            echo $msg;
        }
    }

    protected function debug(string $cacheID, bool $res, string $method): void
    {
        if ($this->options['debug'] !== true) {
            return;
        }
        if ($this->options['debug_method'] === 'echo') {
            $action = match ($method) {
                'set'   => 'set',
                'get'   => 'loaded',
                'flush' => 'flushed',
                default => 'processed (' . $method . ')'
            };
            \printf('<br />Key %s %s %s.', $cacheID, $res === false ? 'could not be' : 'successfully', $action);
        } else {
            Profiler::setCacheProfile($method, (($res !== false) ? 'success' : 'failure'), $cacheID);
        }
    }
}
