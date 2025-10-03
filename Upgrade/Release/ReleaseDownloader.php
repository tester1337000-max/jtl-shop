<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Release;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class ReleaseDownloader
{
    public const API_URL = \URL_SHOP . '/versions.json';

    public function __construct(private readonly Client $client = new Client())
    {
    }

    /**
     * @throws GuzzleException
     */
    public function download(): ResponseInterface
    {
        $res = $this->client->get(
            self::API_URL,
            [
                'timeout' => \CURL_TIMEOUT_IN_SECONDS,
                'verify'  => true
            ]
        );
        if ($res->getStatusCode() !== 200) {
            throw new Exception(\__('Invalid response code'));
        }

        return $res;
    }
}
