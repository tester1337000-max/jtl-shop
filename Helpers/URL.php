<?php

declare(strict_types=1);

namespace JTL\Helpers;

use JTL\Catalog\Category\MenuItem;
use JTL\Catalog\Hersteller;
use JTL\Catalog\Product\MerkmalWert;
use JTL\Language\LanguageHelper;
use JTL\Link\Link;
use JTL\Link\LinkInterface;
use JTL\Link\SpecialPageNotFoundException;
use JTL\News\Item;
use JTL\Router\Router;
use JTL\Shop;

/**
 * Class URL
 * @package JTL\Helpers
 */
class URL
{
    private string $url = '';

    private string $scheme = '';

    private string $host = '';

    private string $port = '';

    private string $user = '';

    private string $pass = '';

    private string $path = '';

    private string $query = '';

    private string $fragment = '';

    /**
     * @var array<string, string>
     */
    private array $defaultPorts = ['http' => '80', 'https' => '443'];

    public function __construct(?string $url = null)
    {
        if ($url !== null) {
            $this->setUrl($url);
        }
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): bool
    {
        $this->url  = $url;
        $components = \parse_url($this->url);
        if (!$components) {
            return false;
        }
        foreach ($components as $key => $value) {
            if (\property_exists($this, $key)) {
                $this->$key = (string)$value;
            }
        }

        return true;
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function setScheme(string $scheme): void
    {
        $this->scheme = $scheme;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function setHost(string $host): void
    {
        $this->host = $host;
    }

    public function getPort(): string
    {
        return $this->port;
    }

    public function setPort(string $port): void
    {
        $this->port = $port;
    }

    public function getUser(): string
    {
        return $this->user;
    }

    public function setUser(string $user): void
    {
        $this->user = $user;
    }

    public function getPass(): string
    {
        return $this->pass;
    }

    public function setPass(string $pass): void
    {
        $this->pass = $pass;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function setQuery(string $query): void
    {
        $this->query = $query;
    }

    public function getFragment(): string
    {
        return $this->fragment;
    }

    public function setFragment(string $fragment): void
    {
        $this->fragment = $fragment;
    }

    public function normalize(): string
    {
        if ($this->path !== '') {
            // case normalization
            $path = \preg_replace_callback(
                '/(%([\dabcdef][\dabcdef]))/x',
                static fn($x): string => '%' . \mb_convert_case($x[2], \MB_CASE_UPPER),
                $this->path
            ) ?? '';
            // percent-encoding normalization
            $path = $this->urlDecodeUnreservedChars($path) ?? '';
            // path segment normalization
            $this->path = $this->removeDotSegments($path);
        }
        $scheme = '';
        if ($this->scheme) {
            $this->scheme = \mb_convert_case($this->scheme, \MB_CASE_LOWER);
            $scheme       = $this->scheme . '://';
        }
        if ($this->host) {
            $this->host = \mb_convert_case($this->host, \MB_CASE_LOWER);
        }
        $this->schemeBasedNormalization();
        // reconstruct uri
        $query         = '';
        $fragment      = '';
        $port          = '';
        $authorization = '';
        if ($this->query) {
            $query = '?' . $this->query;
        }
        if ($this->fragment) {
            $fragment = '#' . $this->fragment;
        }
        if ($this->port) {
            $port = ':' . $this->port;
        }
        if ($this->user) {
            $authorization = $this->user . ':' . $this->pass . '@';
        }

        return $scheme . $authorization . $this->host . $port . $this->path . $query . $fragment;
    }

    /**
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-2.3
     */
    public function urlDecodeUnreservedChars(string $string): ?string
    {
        $unreserved = [];
        for ($octet = 65; $octet <= 90; $octet++) {
            $unreserved[] = \dechex($octet);
        }
        for ($octet = 97; $octet <= 122; $octet++) {
            $unreserved[] = \dechex($octet);
        }
        for ($octet = 48; $octet <= 57; $octet++) {
            $unreserved[] = \dechex($octet);
        }
        $unreserved[] = \dechex(\mb_ord('-'));
        $unreserved[] = \dechex(\mb_ord('.'));
        $unreserved[] = \dechex(\mb_ord('_'));
        $unreserved[] = \dechex(\mb_ord('~'));

        return \preg_replace_callback(
            \array_map(
                static fn($str): string => '/%' . \mb_convert_case($str, \MB_CASE_UPPER) . '/x',
                $unreserved
            ),
            static function ($matches): string {
                $match = \str_starts_with($matches[0], '%') ? \mb_substr($matches[0], 1) : $matches[0];
                // php7.4+ expects strings like "7E" instead of "%7E"
                return \chr((int)\hexdec($match));
            },
            $string
        );
    }

    /**
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-5.2.4
     */
    public function removeDotSegments(string $path): string
    {
        $new_path = '';
        while (!empty($path)) {
            $pattern_a   = '!^(\.\./|\./)!x';
            $pattern_b_1 = '!^(/\./)!x';
            $pattern_b_2 = '!^(/\.)$!x';
            $pattern_c   = '!^(/\.\./|/\.\.)!x';
            $pattern_d   = '!^(\.|\.\.)$!x';
            $pattern_e   = '!(/*[^/]*)!x';

            if (\preg_match($pattern_a, $path)) {
                // remove prefix from $path
                /** @var string $path */
                $path = \preg_replace($pattern_a, '', $path);
            } elseif (\preg_match($pattern_b_1, $path, $matches) || \preg_match($pattern_b_2, $path, $matches)) {
                /** @var string $path */
                $path = \preg_replace('!^' . $matches[1] . '!', '/', $path);
            } elseif (\preg_match($pattern_c, $path, $matches)) {
                /** @var string $path */
                $path = \preg_replace('!^' . \preg_quote($matches[1], '!') . '!x', '/', $path);
                // remove the last segment and its preceding "/" (if any) from output buffer
                /** @var string $new_path */
                $new_path = \preg_replace('!/([^/]+)$!x', '', $new_path);
            } elseif (\preg_match($pattern_d, $path)) {
                /** @var string $path */
                $path = \preg_replace($pattern_d, '', $path);
            } elseif (\preg_match($pattern_e, $path, $matches)) {
                $first_path_segment = $matches[1];

                $path = \preg_replace('/^' . \preg_quote($first_path_segment, '/') . '/', '', $path, 1);

                $new_path .= $first_path_segment;
            }
        }

        return $new_path;
    }

    private function schemeBasedNormalization(): self
    {
        if (isset($this->defaultPorts[$this->scheme]) && $this->defaultPorts[$this->scheme] === $this->port) {
            $this->port = '';
        }

        return $this;
    }

    /**
     * Build an URL string from a given associative array of parts according to PHP's \parse_url()
     *
     * @param array{scheme?: ?string, user?: ?string, pass?: ?string, host: ?string, port?: int|string|null,
     *     path?: ?string, query?: ?string, fragment?: ?string} $parts
     * @return string - the resulting URL
     */
    public static function unparseURL(array $parts): string
    {
        return (isset($parts['scheme']) ? $parts['scheme'] . '://' : '')
            . (isset($parts['user']) ? $parts['user'] . (isset($parts['pass']) ? ':' . $parts['pass'] : '') . '@' : '')
            . ($parts['host'] ?? '')
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . ($parts['path'] ?? '')
            . (isset($parts['query']) ? '?' . $parts['query'] : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }

    private static function getLocalizedFallback(): string
    {
        return LanguageHelper::isDefaultLanguageActive(true)
            ? ''
            : ('&lang=' . Shop::getLanguageCode());
    }

    /**
     * @former baueURL()
     * @since 5.0.0
     */
    public static function buildURL(
        object $obj,
        int $type,
        bool $full = false,
        ?string $prefix = null,
        ?string $locale = null
    ): string {
        if ($obj instanceof LinkInterface) {
            return $obj->getURL();
        }
        $prefix = $prefix ?? ($full === false ? '' : (Shop::getURL() . '/'));
        $router = Shop::getRouter();
        $locale = $locale ?? Text::convertISO2ISO639(Shop::getLanguageCode());
        \executeHook(\HOOK_TOOLSGLOBAL_INC_SWITCH_BAUEURL, ['obj' => &$obj, 'art' => &$type]);
        switch ($type) {
            case \URLART_ARTIKEL:
                return $full
                    ? $router->getURLByType(
                        Router::TYPE_PRODUCT,
                        ['lang' => $locale, 'name' => $obj->cSeo ?? null, 'id' => $obj->kArtikel ?? null]
                    )
                    : $router->getPathByType(
                        Router::TYPE_PRODUCT,
                        ['lang' => $locale, 'name' => $obj->cSeo ?? null, 'id' => $obj->kArtikel ?? null]
                    );

            case \URLART_KATEGORIE:
                $slug = null;
                $id   = $obj !== null && \method_exists($obj, 'getID')
                    ? $obj->getID()
                    : ($obj->kKategorie ?? null);
                if ($obj instanceof MenuItem) {
                    $slug = $obj->getURL();
                } elseif (!empty($obj->cSeo)) {
                    $slug = $obj->cSeo;
                }
                if ($slug !== null) {
                    return $full
                        ? $router->getURLByType(
                            Router::TYPE_CATEGORY,
                            ['lang' => $locale, 'name' => $slug, 'id' => $id]
                        )
                        : $router->getPathByType(
                            Router::TYPE_CATEGORY,
                            ['lang' => $locale, 'name' => $slug, 'id' => $id]
                        );
                }

                return $prefix . '?k=' . $id . self::getLocalizedFallback();

            case \URLART_SEITE:
                if (!empty($obj->cSeo)) {
                    return $full
                        ? $router->getURLByType(
                            Router::TYPE_PAGE,
                            ['lang' => $locale, 'name' => $obj->cSeo, 'id' => $obj->kLink ?? null]
                        )
                        : $router->getPathByType(
                            Router::TYPE_PAGE,
                            ['lang' => $locale, 'name' => $obj->cSeo, 'id' => $obj->kLink ?? null]
                        );
                }
                if (
                    isset($_SESSION['cISOSprache'], $obj->cLocalizedSeo[$_SESSION['cISOSprache']])
                    && \mb_strlen($obj->cLocalizedSeo[$_SESSION['cISOSprache']])
                ) {
                    return $prefix . $obj->cLocalizedSeo[$_SESSION['cISOSprache']];
                }

                return Shop::Container()->getLinkService()->getSpecialPage((int)$obj->nLinkart)->getURL() ?? '';

            case \URLART_HERSTELLER:
                if ($obj instanceof Hersteller) {
                    return $obj->getURL() ?? '';
                }
                if (!empty($obj->cSeo)) {
                    return $full
                        ? $router->getURLByType(
                            Router::TYPE_MANUFACTURER,
                            ['id' => $obj->kHersteller, 'name' => $obj->cSeo, 'lang' => $locale]
                        )
                        : $router->getPathByType(
                            Router::TYPE_MANUFACTURER,
                            ['id' => $obj->kHersteller, 'name' => $obj->cSeo, 'lang' => $locale]
                        );
                }

                return $prefix . '?h=' . $obj->kHersteller . self::getLocalizedFallback();

            case \URLART_LIVESUCHE:
                return $full
                    ? $router->getURLByType(
                        Router::TYPE_SEARCH_QUERY,
                        ['id' => $obj->kSuchanfrage, 'name' => $obj->cSeo ?? null, 'lang' => $locale]
                    )
                    : $router->getPathByType(
                        Router::TYPE_SEARCH_QUERY,
                        ['id' => $obj->kSuchanfrage, 'name' => $obj->cSeo ?? null, 'lang' => $locale]
                    );

            case \URLART_MERKMAL:
                $slug = null;
                $id   = null;
                if ($obj instanceof MerkmalWert) {
                    $slug = $obj->getURL();
                } elseif (!empty($obj->cSeo)) {
                    $slug = $obj->cSeo;
                    $id   = $obj->kMerkmalWert ?? null;
                }
                if ($slug !== null) {
                    return $full
                        ? $router->getURLByType(
                            Router::TYPE_CHARACTERISTIC_VALUE,
                            ['lang' => $locale, 'name' => $slug, 'id' => $id]
                        )
                        : $router->getPathByType(
                            Router::TYPE_CHARACTERISTIC_VALUE,
                            ['lang' => $locale, 'name' => $slug, 'id' => $id]
                        );
                }

                return $prefix . '?m=' . $obj->kMerkmalWert . self::getLocalizedFallback();

            case \URLART_NEWS:
                if ($obj instanceof Item) {
                    return !empty($obj->getSEO())
                        ? $obj->getURL()
                        : $prefix . '?n=' . $obj->getID() . self::getLocalizedFallback();
                }
                return $full
                    ? $router->getURLByType(
                        Router::TYPE_NEWS,
                        ['id' => $obj->kNews, 'name' => $obj->cSeo ?? null, 'lang' => $locale]
                    )
                    : $router->getPathByType(
                        Router::TYPE_NEWS,
                        ['id' => $obj->kNews, 'name' => $obj->cSeo ?? null, 'lang' => $locale]
                    );

            case \URLART_NEWSMONAT:
                return $full
                    ? $router->getURLByType(
                        Router::TYPE_NEWS,
                        ['id' => $obj->kNewsMonatsUebersicht, 'name' => $obj->cSeo ?? null, 'lang' => $locale]
                    )
                    : $router->getPathByType(
                        Router::TYPE_NEWS,
                        ['id' => $obj->kNewsMonatsUebersicht, 'name' => $obj->cSeo ?? null, 'lang' => $locale]
                    );

            case \URLART_NEWSKATEGORIE:
                return $full
                    ? $router->getURLByType(
                        Router::TYPE_NEWS,
                        ['id' => $obj->kNewsKategorie, 'name' => $obj->cSeo ?? null, 'lang' => $locale]
                    )
                    : $router->getPathByType(
                        Router::TYPE_NEWS,
                        ['id' => $obj->kNewsKategorie, 'name' => $obj->cSeo ?? null, 'lang' => $locale]
                    );

            case \URLART_SEARCHSPECIALS:
                return $full
                    ? $router->getURLByType(
                        Router::TYPE_SEARCH_SPECIAL,
                        ['id' => $obj->kSuchspecial, 'name' => $obj->cSeo ?? null, 'lang' => $locale]
                    )
                    : $router->getPathByType(
                        Router::TYPE_SEARCH_SPECIAL,
                        ['id' => $obj->kSuchspecial, 'name' => $obj->cSeo ?? null, 'lang' => $locale]
                    );
            case \URLART_NEWSLETTER:
                try {
                    /** @var Link $link */
                    $link   = Shop::Container()->getLinkService()->getSpecialPage(\LINKTYP_NEWSLETTER);
                    $prefix = $full ? $link->getURL() : $link->getURLPath();
                } catch (SpecialPageNotFoundException $e) {
                    $prefix = '';
                    Shop::Container()->getLogService()->error($e->getMessage());
                }
                return !empty($obj->cSeo)
                    ? $prefix . '/' . $obj->cSeo
                    : $prefix . '?show=' . $obj->kNewsletterHistory;

            default:
                return '';
        }
    }
}
