<?php

declare(strict_types=1);

namespace JTL\Redirect;

use InvalidArgumentException;
use JTL\Shop;

class URLParser
{
    public ?string $scheme = null;

    public ?string $user = null;

    public ?string $pass = null;

    public ?string $host = null;

    public ?int $port = null;

    public ?string $path = null;

    public ?string $query = null;

    public ?string $fragment = null;

    /**
     * @var array{scheme?: string, host?: string, port?: int<0, 65535>,
     *      user?: string, pass?: string, path?: string, query?: string, fragment?: string}
     */
    private array $shopURL;

    public function __construct(string $url, ?string $shopURL = null)
    {
        $parsed = \parse_url($shopURL ?? Shop::getURL() . '/');
        if (!\is_array($parsed)) {
            throw new InvalidArgumentException('Invalid shop URL');
        }
        $this->shopURL = $parsed;
        if (!empty($url)) {
            $this->getURLParts($url);
        }
    }

    /**
     * @return array{scheme: string|null, user: string|null, pass: string|null, host: string|null,
     *     port: int|null, path: string|null, query: string|null, fragment: string|null}
     */
    public function toArray(): array
    {
        return [
            'scheme'   => $this->scheme,
            'user'     => $this->user,
            'pass'     => $this->pass,
            'host'     => $this->host,
            'port'     => $this->port,
            'path'     => $this->path,
            'query'    => $this->query,
            'fragment' => $this->fragment,
        ];
    }

    private function getURLParts(string $url): void
    {
        $parsedUrl = \parse_url($url);
        if (!\is_array($parsedUrl)) {
            throw new InvalidArgumentException('Invalid URL: ' . $url);
        }
        $this->updateProperties($parsedUrl);
        $host     = $parsedUrl['host'] ?? null;
        $shopHost = $this->shopURL['host'] ?? null;
        if ($host === null && isset($this->shopURL['scheme'], $shopHost)) {
            $this->scheme = $this->shopURL['scheme'];
            $this->host   = $shopHost;
        } elseif (($host ?? '???') !== ($shopHost ?? '?')) {
            return;
        }
        if (!isset($parsedUrl['path'])) {
            $this->path = $this->shopURL['path'] ?? '';
        } elseif (!\str_starts_with($parsedUrl['path'], $this->shopURL['path'] ?? 'invalid')) {
            if ($host !== null) {
                return;
            }
            $this->path = \rtrim($this->shopURL['path'] ?? '', '/') . '/' . \ltrim($parsedUrl['path'], '/');
        }
        $this->addNoTrack();
    }

    /**
     * @param array{scheme?: string|null, host?: string|null, port?: int|null, user?: string|null,
     *      pass?: string|null, path?: string|null, query?: string|null, fragment?: string|null} $parts
     */
    private function updateProperties(array $parts): void
    {
        foreach ($parts as $key => $value) {
            if (\property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
        if ($this->path !== null) {
            $this->path = '/' . \ltrim($this->path, '/');
        }
    }

    private function addNoTrack(): void
    {
        if ($this->query === null) {
            $this->query = 'notrack';

            return;
        }
        $this->query .= '&notrack';
    }
}
