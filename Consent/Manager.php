<?php

declare(strict_types=1);

namespace JTL\Consent;

use Illuminate\Support\Collection;
use JTL\Cache\JTLCacheInterface;
use JTL\Consent\Statistics\Services\ConsentStatisticsService;
use JTL\DB\DbInterface;
use JTL\Session\Frontend;

/**
 * Class Manager
 * @package JTL\Consent
 */
class Manager implements ManagerInterface
{
    /**
     * @var array<int, Collection<int, ItemInterface>>|array{}
     */
    private array $activeItems = [];

    private ConsentStatisticsService $consentStatisticsService;

    public function __construct(
        private readonly DbInterface $db,
        private readonly JTLCacheInterface $cache,
        ?ConsentStatisticsService $consentStatisticsService = null,
    ) {
        $this->consentStatisticsService = $consentStatisticsService ?? new ConsentStatisticsService();
    }

    /**
     * @inheritdoc
     */
    public function getConsents(): array
    {
        return Frontend::get('consents') ?? [];
    }

    /**
     * @inheritdoc
     */
    public function itemRevokeConsent(ItemInterface $item): void
    {
        $consents                     = $this->getConsents();
        $consents[$item->getItemID()] = false;
        Frontend::set('consents', $consents);
    }

    /**
     * @inheritdoc
     */
    public function itemGiveConsent(ItemInterface $item): void
    {
        $consents                     = $this->getConsents();
        $consents[$item->getItemID()] = true;
        Frontend::set('consents', $consents);
    }

    /**
     * @inheritdoc
     */
    public function itemHasConsent(ItemInterface $item): bool
    {
        return $this->hasConsent($item->getItemID());
    }

    /**
     * @inheritdoc
     */
    public function hasConsent(string $itemID): bool
    {
        return (($this->getConsents())[$itemID]) ?? false;
    }

    /**
     * @inheritdoc
     */
    public function save(array|string $data): array
    {
        if (!\is_array($data)) {
            return [];
        }
        $consents            = $this->getConsentsFromData($data);
        $visitor             = Frontend::get('oBesucher');
        $consentsFromSession = Frontend::get('consents');
        if (!\is_object($visitor) || empty($visitor->kBesucher)) {
            return \is_array($consentsFromSession)
                ? $consentsFromSession
                : [];
        }
        // Use previously saved consents to check if any consent item has changed and save changes into DB
        $consents['accepted_all'] = $this->consentStatisticsService->hasAcceptedAll($consents);
        $this->consentStatisticsService->saveConsentValues(
            $visitor->kBesucher,
            $consents,
            $consentsFromSession !== null
                ? $visitor->dZeit ?? null
                : null
        );
        Frontend::set('consents', $consents);

        return $consents;
    }

    /**
     * @param array<string|mixed, string> $data
     * @return array<string, bool>
     */
    private function getConsentsFromData(array $data): array
    {
        $consents = [];
        foreach ($data as $item => $value) {
            if (!\is_string($item) || !\in_array($value, ['true', 'false'], true)) {
                continue;
            }
            $consents[$item] = $value === 'true';
        }

        return $consents;
    }

    /**
     * @inheritdoc
     */
    public function initActiveItems(int $languageID): Collection
    {
        $cached  = true;
        $cacheID = 'jtl_consent_models_' . $languageID;
        /** @var Collection<int, ItemInterface>|false $items */
        $items = $this->cache->get($cacheID);
        if ($items === false) {
            $items = ConsentModel::loadAll($this->db, 'active', 1)
                ->map(fn(ConsentModel $model): ItemInterface => (new Item($languageID))->loadFromModel($model))
                ->sortBy(fn(Item $item): bool => $item->getItemID() !== 'necessary');
            $this->cache->set($cacheID, $items, [\CACHING_GROUP_CORE]);
            $cached = false;
        }
        \executeHook(\CONSENT_MANAGER_GET_ACTIVE_ITEMS, ['items' => $items, 'cached' => $cached]);
        $this->activeItems[$languageID] = $items;

        return $items;
    }

    /**
     * @inheritdoc
     */
    public function getActiveItems(int $languageID): Collection
    {
        return $this->activeItems[$languageID] ?? $this->initActiveItems($languageID);
    }
}
