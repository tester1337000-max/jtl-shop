<?php

declare(strict_types=1);

namespace JTL\Recommendation;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;
use JsonException;
use JTL\Cache\JTLCacheInterface;
use JTL\Services\JTL\AlertServiceInterface;
use JTL\Shop;

/**
 * Class Manager
 * @package JTL\Recommendation
 */
class Manager
{
    public const SCOPE_WIZARD_PAYMENT_PROVIDER = 'wizard.payment-provider';

    public const SCOPE_WIZARD_LEGAL_TEXTS = 'wizard.legal-texts';

    public const SCOPE_BACKEND_PAYMENT_PROVIDER = 'backend.payment-provider';

    public const SCOPE_BACKEND_LEGAL_TEXTS = 'backend.legal-texts';

    private const API_DEV_URL = 'https://checkout-stage.jtl-software.com/v1/recommendations';

    private const API_LIVE_URL = 'https://checkout.jtl-software.com/v1/recommendations';

    private Client $client;

    /**
     * @var Collection<int, Recommendation>
     */
    private Collection $recommendations;

    private JTLCacheInterface $cache;

    public function __construct(
        private readonly AlertServiceInterface $alertService,
        private readonly string $scope,
        ?JTLCacheInterface $cache = null
    ) {
        $this->client          = new Client();
        $this->recommendations = new Collection();
        $this->cache           = $cache ?? Shop::Container()->getCache();
    }

    public function setRecommendations(): void
    {
        foreach ($this->getJSONFromAPI($this->getScope()) as $recommendation) {
            $this->recommendations->push(new Recommendation($recommendation));
        }
        if ($this->recommendations->isNotEmpty()) {
            $this->cache->set(
                $this->getCacheID(),
                $this->recommendations,
                [\CACHING_GROUP_RECOMMENDATIONS]
            );
        }
    }

    /**
     * @return Collection<int, Recommendation>
     */
    public function getRecommendations(): Collection
    {
        $recommendations = $this->cache->get($this->getCacheID());
        if ($recommendations instanceof Collection) {
            $this->recommendations = $recommendations;
        }
        if ($this->recommendations->isEmpty()) {
            $this->setRecommendations();
        }

        return $this->recommendations;
    }

    public function getRecommendationById(string $id, bool $showAlert = true): ?Recommendation
    {
        if ($this->recommendations->isEmpty()) {
            $this->getRecommendations();
        }
        $recommendation = $this->recommendations->first(
            static fn(Recommendation $recommendation): bool => $recommendation->getId() === $id
        );
        if ($recommendation === null && $showAlert) {
            $this->alertService->addWarning(\__('noRecommendationFound'), 'noRecommendationFound');
        }

        return $recommendation;
    }

    /**
     * @return \stdClass[]
     * @throws GuzzleException
     * @throws JsonException
     */
    private function getJSONFromAPI(string $scope): array
    {
        $url = (\EXS_LIVE === true ? self::API_LIVE_URL : self::API_DEV_URL) . '?scope=' . $scope;
        try {
            $res = $this->client->request(
                'GET',
                $url,
                [
                    'headers' => [
                        'Accept'       => 'application/json',
                        'Content-Type' => 'application/json',
                    ],
                    'verify'  => true,
                    'timeout' => \CURL_TIMEOUT_IN_SECONDS
                ]
            );
        } catch (Exception $e) {
            $res = null;
            Shop::Container()->getLogService()->error($e->getMessage());
        }

        return $res === null
            ? []
            : \json_decode(
                (string)$res->getBody(),
                false,
                512,
                \JSON_THROW_ON_ERROR
            )->extensions;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    private function getCacheID(): string
    {
        return 'recommendations_' . $this->getScope();
    }
}
