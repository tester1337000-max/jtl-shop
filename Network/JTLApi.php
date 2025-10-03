<?php

declare(strict_types=1);

namespace JTL\Network;

use Exception;
use JTL\Cache\JTLCacheInterface;
use JTL\Helpers\Request;
use JTL\Nice;
use JTLShop\SemVer\Version;
use stdClass;

use function Functional\last;

/**
 * Class JTLApi
 * @package JTL\Network
 */
final class JTLApi
{
    public const URI = 'https://api.jtl-software.de/shop';

    public const URI_VERSION = 'https://api.jtl-shop.de';

    public function __construct(private readonly Nice $nice, private readonly JTLCacheInterface $cache)
    {
    }

    public function getSubscription(): ?stdClass
    {
        $cacheID = 'rs_subscriptions';
        $cached  = $this->cache->get($cacheID);
        if ($cached !== false) {
            return $cached;
        }
        $data         = null;
        $uri          = self::URI . '/check/subscription';
        $subscription = $this->call($uri, [
            'key'    => $this->nice->getAPIKey(),
            'domain' => $this->nice->getDomain(),
        ]);
        if (!\is_object($subscription)) {
            return null;
        }
        if (isset($subscription->kShop) && $subscription->kShop > 0) {
            $subscription->kShop            = (int)$subscription->kShop;
            $subscription->kKaufprodukt     = (int)($subscription->kKaufprodukt ?? '0');
            $subscription->nLaufendeVersion = (int)($subscription->nLaufendeVersion ?? '0');
            $subscription->kAccount         = (int)($subscription->kAccount ?? '0');
            $subscription->bUpdate          = (int)($subscription->bUpdate ?? '0');
            $subscription->nDayDiff         = (int)($subscription->nDayDiff ?? '0');
            $data                           = $subscription;
        }
        $this->cache->set($cacheID, $data, [\CACHING_GROUP_CORE], 60 * 60);

        return $subscription;
    }

    /**
     * @param bool $includingDev
     * @return array<mixed>|null
     */
    public function getAvailableVersions(bool $includingDev = false): ?array
    {
        $cacheID = 'rs_versions' . ($includingDev === true ? '-dev' : '');
        $cached  = $this->cache->get($cacheID);
        if ($cached !== false) {
            return $cached;
        }
        $url = self::URI_VERSION . '/versions';
        if ($includingDev === true) {
            $url .= '-dev';
        }
        $versions = $this->call($url);
        if (!\is_object($versions)) {
            return null;
        }
        $data = (array)$versions;
        $this->cache->set($cacheID, $data, [\CACHING_GROUP_CORE], 60 * 60);

        return $data;
    }

    /**
     * @throws Exception
     */
    public function getLatestVersion(): Version
    {
        $shopVersion       = \APPLICATION_VERSION;
        $parsedShopVersion = Version::parse($shopVersion);
        $availableVersions = $this->getAvailableVersions() ?? [];
        $newerVersions     = \array_filter($availableVersions, static function ($v) use ($parsedShopVersion) {
            try {
                return Version::parse($v->reference)->greaterThan($parsedShopVersion);
            } catch (Exception) {
                return false;
            }
        });
        $version           = \count($newerVersions) > 0 ? last($newerVersions) : \end($availableVersions);

        return Version::parse($version->reference);
    }

    public function hasNewerVersion(): bool
    {
        try {
            return \APPLICATION_BUILD_SHA !== '#DEV#'
                && $this->getLatestVersion()->greaterThan(Version::parse(\APPLICATION_VERSION));
        } catch (Exception) {
            return false;
        }
    }

    /**
     * @param string            $uri
     * @param array<mixed>|null $data
     * @return string|bool|null
     * @throws \JsonException
     */
    private function call(string $uri, ?array $data = null): mixed
    {
        $content = Request::http_get_contents($uri, 10, $data);

        return empty($content) || !\is_string($content)
            ? null
            : \json_decode($content, false, 512, \JSON_THROW_ON_ERROR);
    }
}
