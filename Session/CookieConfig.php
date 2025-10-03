<?php

declare(strict_types=1);

namespace JTL\Session;

use JTL\Language\LanguageHelper;
use JTL\Settings\Option\Globals;
use JTL\Settings\Settings;

/**
 * Class CookieConfig
 * @package JTL\Session
 */
class CookieConfig
{
    private string $path = '';

    private string $domain = '';

    /**
     * @var 'Lax'|'lax'|'None'|'none'|'Strict'|'strict'|''
     */
    private string $sameSite = '';

    private int $lifetime = 0;

    private bool $httpOnly = false;

    private bool $secure = false;

    public function __construct(Settings $settings)
    {
        $this->readDefaults();
        $this->mergeWithConfig($settings);
    }

    private function readDefaults(): void
    {
        $defaults       = \session_get_cookie_params();
        $this->lifetime = $defaults['lifetime'];
        $this->path     = $defaults['path'];
        $this->domain   = $defaults['domain'];
        $this->secure   = $defaults['secure'];
        $this->httpOnly = $defaults['httponly'];
        $this->sameSite = $defaults['samesite'];
    }

    private function mergeWithConfig(Settings $settings): void
    {
        $this->secure   = $this->secure || $settings->bool(Globals::COOKIE_SECURE);
        $this->httpOnly = $this->httpOnly || $settings->bool(Globals::COOKIE_HTTPONLY);
        /** @phpstan-var ('Lax'|'None'|'Strict'|'S'|'N') $samesite */
        $samesite = $settings->string(Globals::COOKIE_SAMESITE);
        if ($samesite !== 'S') {
            if ($samesite === 'N') {
                $samesite = '';
            }
            $this->sameSite = $samesite;
        }
        if (($domain = $settings->string(Globals::COOKIE_DOMAIN)) !== '') {
            $this->domain = $this->experimentalMultiLangDomain($domain);
        }
        if (($lifetime = $settings->int(Globals::COOKIE_LIFETIME)) > 0) {
            $this->lifetime = $lifetime;
        }
        $path = $settings->string(Globals::COOKIE_PATH);
        if (!empty($path)) {
            $this->path = $path;
        }
        $this->secure = $this->secure
            && ($settings->string(Globals::CHECKOUT_SSL) === 'P' || \str_starts_with(\URL_SHOP, 'https://'));
    }

    private function experimentalMultiLangDomain(string $domain): string
    {
        if (\EXPERIMENTAL_MULTILANG_SHOP !== true) {
            return $domain;
        }
        $host = $_SERVER['HTTP_HOST'] ?? ' ';
        foreach (LanguageHelper::getAllLanguages() as $language) {
            $code = \mb_convert_case($language->getCode(), \MB_CASE_UPPER);
            if (!\defined('URL_SHOP_' . $code)) {
                continue;
            }
            /** @var string $localized */
            $localized = \constant('URL_SHOP_' . $code);
            if (\defined('COOKIE_DOMAIN_' . $code) && \str_contains($localized, $host)) {
                /** @var string $defined */
                $defined = \constant('COOKIE_DOMAIN_' . $code);

                return $defined;
            }
        }

        return $domain;
    }

    /**
     * @return array{use_cookies: string, cookie_domain: string, cookie_secure: bool,
     *      cookie_lifetime: int, cookie_path: string, cookie_httponly: bool,
     *      cookie_samesite: 'Lax'|'lax'|'None'|'none'|'Strict'|'strict'|''}
     */
    public function getSessionConfigArray(): array
    {
        return [
            'use_cookies'     => '1',
            'cookie_domain'   => $this->getDomain(),
            'cookie_secure'   => $this->isSecure(),
            'cookie_lifetime' => $this->getLifetime(),
            'cookie_path'     => $this->getPath(),
            'cookie_httponly' => $this->isHttpOnly(),
            'cookie_samesite' => $this->getSameSite()
        ];
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }

    /**
     * @return 'Lax'|'lax'|'None'|'none'|'Strict'|'strict'|''
     */
    public function getSameSite(): string
    {
        return $this->sameSite;
    }

    /**
     * @param 'Lax'|'lax'|'None'|'none'|'Strict'|'strict' $sameSite
     */
    public function setSameSite(string $sameSite): void
    {
        $this->sameSite = $sameSite;
    }

    public function getLifetime(): int
    {
        return $this->lifetime;
    }

    public function setLifetime(int $lifetime): void
    {
        $this->lifetime = $lifetime;
    }

    public function isHttpOnly(): bool
    {
        return $this->httpOnly;
    }

    public function setHttpOnly(bool $httpOnly): void
    {
        $this->httpOnly = $httpOnly;
    }

    public function isSecure(): bool
    {
        return $this->secure;
    }

    public function setSecure(bool $secure): void
    {
        $this->secure = $secure;
    }
}
