<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum Cache: string implements OptionInterface
{
    case TYPES_DISABLED         = 'caching_types_disabled';
    case ENABLED                = 'caching_activated';
    case METHOD                 = 'caching_method';
    case REDIS_HOST             = 'caching_redis_host';
    case REDIS_PORT             = 'caching_redis_port';
    case REDIS_DB               = 'caching_redis_db';
    case REDIS_USER             = 'caching_redis_user';
    case REDIS_PASS             = 'caching_redis_pass';
    case REDIS_PERSISTENT       = 'caching_redis_persistent';
    case MEMCACHE_PORT          = 'caching_memcache_port';
    case MEMCACHE_HOST          = 'caching_memcache_host';
    case LIFETIME               = 'caching_lifetime';
    case DEBUG                  = 'caching_debug';
    case DEBUG_METHOD           = 'caching_debug_method';
    case REDIS_CLUSTER_HOSTS    = 'caching_rediscluster_hosts';
    case REDIS_CLUSTER_STRATEGY = 'caching_rediscluster_strategy';
    case COMPILE_CHECK          = 'compile_check';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::CACHE;
    }
}
