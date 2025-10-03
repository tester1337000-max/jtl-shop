<?php

declare(strict_types=1);

namespace JTL\License;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use JTL\Backend\AuthToken;
use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\License\Exception\AuthException;
use JTL\License\Struct\ExsLicense;
use JTL\Router\Route;
use JTL\Shop;
use stdClass;

/**
 * Class Manager
 * @package JTL\License
 */
class Manager
{
    private const MAX_REQUESTS = 10;

    private const CHECK_INTERVAL_HOURS = 4;

    private const USER_API_URL = 'https://oauth2.api.jtl-software.com/api/v1/user';

    private const API_LIVE_URL = 'https://checkout.jtl-software.com/v1/licenses';

    private const API_DEV_URL = 'https://checkout-stage.jtl-software.com/v1/licenses';

    private string $domain;

    private Client $client;

    private string $token;

    public function __construct(private DbInterface $db, private JTLCacheInterface $cache)
    {
        $host = \parse_url(\URL_SHOP, \PHP_URL_HOST);
        if (!\is_string($host)) {
            $host = '';
        }
        $this->client = new Client();
        $this->domain = $host;
        $this->token  = AuthToken::getInstance($this->db)->get();
    }

    private function checkUpdate(): bool
    {
        return ($lastItem = $this->getLicenseData()) === null
            || (\time() - \strtotime($lastItem->timestamp)) / (60 * 60) > self::CHECK_INTERVAL_HOURS;
    }

    /**
     * @return string[]
     */
    private function getHeaders(): array
    {
        return [
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'User-Agent'    => 'JTL-Shop/' . \APPLICATION_VERSION . '-' . $this->domain,
            'Authorization' => 'Bearer ' . $this->token
        ];
    }

    public function setBinding(string $url): string
    {
        if ($this->token === '') {
            throw new AuthException(\__('Invalid token.'));
        }
        try {
            $body = \json_encode((object)['domain' => $this->domain], \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return '';
        }
        $res = $this->client->request(
            'POST',
            $url,
            [
                'headers' => $this->getHeaders(),
                'verify'  => true,
                'body'    => $body,
                'timeout' => \CURL_TIMEOUT_IN_SECONDS
            ]
        );

        return (string)$res->getBody();
    }

    public function createLicense(string $url): string
    {
        if ($this->token === '') {
            throw new AuthException(\__('Invalid token.'));
        }
        try {
            $body = \json_encode((object)['domain' => $this->domain], \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return '';
        }
        $res = $this->client->request(
            'POST',
            $url,
            [
                'headers' => $this->getHeaders(),
                'verify'  => true,
                'body'    => $body,
                'timeout' => \CURL_TIMEOUT_IN_SECONDS
            ]
        );

        return (string)$res->getBody();
    }

    public function clearBinding(string $url): string
    {
        if ($this->token === '') {
            throw new AuthException(\__('Invalid token.'));
        }
        try {
            $body = \json_encode((object)['domain' => $this->domain], \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return '';
        }
        $res = $this->client->request(
            'GET',
            $url,
            [
                'headers' => $this->getHeaders(),
                'verify'  => true,
                'body'    => $body,
                'timeout' => \CURL_TIMEOUT_IN_SECONDS
            ]
        );

        return (string)$res->getBody();
    }

    public function extendUpgrade(string $url, string $exsID, string $key): string
    {
        if ($this->token === '') {
            throw new AuthException(\__('Invalid token.'));
        }
        try {
            $body = \json_encode(
                (object)[
                    'exsid'         => $exsID,
                    'reference'     => (object)[
                        'license' => $key,
                        'domain'  => $this->domain
                    ],
                    'redirect_urls' => (object)[
                        'return_url' => Shop::getAdminURL() . '/' . Route::LICENSE . '?extend=success',
                        'cancel_url' => Shop::getAdminURL() . '/' . Route::LICENSE . '?extend=fail'
                    ],
                ],
                \JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            return '';
        }
        $res = $this->client->request(
            'POST',
            $url,
            [
                'headers' => $this->getHeaders(),
                'verify'  => true,
                'body'    => $body,
                'timeout' => \CURL_TIMEOUT_IN_SECONDS
            ]
        );

        return (string)$res->getBody();
    }

    /**
     * @param stdClass[] $installedExtensions
     * @throws GuzzleException
     * @throws AuthException
     */
    public function update(bool $force = false, array $installedExtensions = []): int
    {
        if (!$force && !$this->checkUpdate()) {
            return 0;
        }
        if ($this->token === '') {
            throw new AuthException(\__('Invalid token.'));
        }
        try {
            $body = \json_encode(
                (object)[
                    'shop'       => [
                        'domain'  => $this->domain,
                        'version' => \APPLICATION_VERSION,
                    ],
                    'extensions' => $installedExtensions
                ],
                \JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            return 0;
        }
        $res = $this->client->request(
            'POST',
            \EXS_LIVE === true ? self::API_LIVE_URL : self::API_DEV_URL,
            [
                'headers' => $this->getHeaders(),
                'verify'  => true,
                'body'    => $body,
                'timeout' => \CURL_TIMEOUT_IN_SECONDS
            ]
        );
        if ($res->getStatusCode() !== 200 || \mb_strlen((string)$res->getBody()) === 0) {
            throw new \Exception(\__('Invalid response.'));
        }
        $this->housekeeping();
        $this->cache->flushTags([\CACHING_GROUP_LICENSES]);

        $owner = $this->getTokenOwner();
        try {
            /** @var stdClass $data */
            $data        = \json_decode((string)$res->getBody(), false, 512, \JSON_THROW_ON_ERROR);
            $data->owner = isset($owner->given_name, $owner->family_name) ? $owner : null;
            $data        = \json_encode($data, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $data = '';
        }

        return $this->db->insert('licenses', (object)['data' => $data, 'returnCode' => $res->getStatusCode()]);
    }

    /**
     * @throws GuzzleException
     */
    private function getTokenOwner(): stdClass
    {
        if ($this->token === '') {
            throw new AuthException(\__('Invalid token.'));
        }
        $res = $this->client->request(
            'GET',
            self::USER_API_URL,
            [
                'headers' => $this->getHeaders(),
                'verify'  => true,
                'timeout' => \CURL_TIMEOUT_IN_SECONDS
            ]
        );

        try {
            return \json_decode($res->getBody()->getContents(), false, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new stdClass();
        }
    }

    public function getLicenseData(): ?stdClass
    {
        $data = $this->db->getSingleObject(
            'SELECT * FROM licenses
                WHERE returnCode = 200
                ORDER BY id DESC
                LIMIT 1'
        );
        if ($data === null) {
            return null;
        }
        try {
            /** @var stdClass $obj */
            $obj             = \json_decode($data->data ?? '', false, 512, \JSON_THROW_ON_ERROR);
            $obj->timestamp  = $data->timestamp;
            $obj->returnCode = $data->returnCode;
        } catch (JsonException) {
            $obj = null;
        }

        return $obj === null || !isset($obj->extensions) ? null : $obj;
    }

    public function getLicenseByItemID(string $itemID): ?ExsLicense
    {
        return (new Mapper($this))->getCollection()->getBound()->getForItemID($itemID);
    }

    public function getLicenseByExsID(string $exsID): ?ExsLicense
    {
        return (new Mapper($this))->getCollection()->getBound()->getForExsID($exsID);
    }

    public function getLicenseByLicenseKey(string $key): ?ExsLicense
    {
        return (new Mapper($this))->getCollection()->getBound()->getForLicenseKey($key);
    }

    private function housekeeping(): int
    {
        return $this->db->getAffectedRows(
            'DELETE a 
                FROM licenses AS a 
                JOIN ( 
                    SELECT id 
                        FROM licenses 
                        ORDER BY timestamp DESC 
                        LIMIT 99999 OFFSET :max) AS b
                ON a.id = b.id',
            ['max' => self::MAX_REQUESTS]
        );
    }

    public function getDB(): DbInterface
    {
        return $this->db;
    }

    public function setDB(DbInterface $db): void
    {
        $this->db = $db;
    }

    public function getCache(): JTLCacheInterface
    {
        return $this->cache;
    }

    public function setCache(JTLCacheInterface $cache): void
    {
        $this->cache = $cache;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function setClient(Client $client): void
    {
        $this->client = $client;
    }
}
