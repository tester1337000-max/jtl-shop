<?php

declare(strict_types=1);

namespace JTL\Session;

use Exception;
use JTL\Helpers\Request;
use JTL\Session\Handler\JTLHandlerInterface;
use JTL\Settings\Section;
use JTL\Settings\Settings;
use JTL\Shop;

use function Functional\last;

/**
 * Class AbstractSession
 * @package JTL\Session
 */
abstract class AbstractSession
{
    protected static JTLHandlerInterface $handler;

    protected static string $sessionName;

    public function __construct(bool $start, string $sessionName)
    {
        self::$sessionName = $sessionName;
        \session_name(self::$sessionName);
        self::$handler = (new Storage())->getHandler();
        $this->initCookie(Settings::fromSection(Section::GLOBAL), $start);
        self::$handler->setSessionData($_SESSION);
    }

    /**
     * pre-calculate all the localized shop base URLs
     */
    protected function initLanguageURLs(): void
    {
        if (\EXPERIMENTAL_MULTILANG_SHOP !== true) {
            return;
        }
        $urls      = [];
        $sslStatus = Request::checkSSL();
        foreach ($_SESSION['Sprachen'] ?? [] as $language) {
            $code = \mb_convert_case($language->getCode(), \MB_CASE_UPPER);
            /** @var string $shopURL */
            $shopURL = \defined('URL_SHOP_' . $code) ? \constant('URL_SHOP_' . $code) : \URL_SHOP;
            foreach ([0, 1] as $forceSSL) {
                if ($sslStatus === 2) {
                    $shopURL = \str_replace('http://', 'https://', $shopURL);
                } elseif ($sslStatus === 4 || ($sslStatus === 3 && $forceSSL)) {
                    $shopURL = \str_replace('http://', 'https://', $shopURL);
                }
                $urls[$language->getId()][$forceSSL] = \rtrim($shopURL, '/');
            }
        }
        Shop::setURLs($urls);
    }

    public static function getSessionName(): string
    {
        return self::$sessionName;
    }

    protected function initCookie(Settings $settings, bool $start = true): bool
    {
        $cookieConfig = new CookieConfig($settings);
        if ($start) {
            $this->start($cookieConfig);
        }
        $this->setCookie($cookieConfig);
        $this->clearDuplicateCookieHeaders();

        return true;
    }

    /**
     * @throws Exception
     */
    private function setCookie(CookieConfig $cookieConfig): bool
    {
        $sessionName = \session_name();
        $sessionID   = \session_id();
        if ($sessionName === false || $sessionID === false) {
            throw new Exception('Session could not be started');
        }

        return \setcookie(
            $sessionName,
            $sessionID,
            [
                'expires'  => ($cookieConfig->getLifetime() === 0) ? 0 : \time() + $cookieConfig->getLifetime(),
                'path'     => $cookieConfig->getPath(),
                'domain'   => $cookieConfig->getDomain(),
                'secure'   => $cookieConfig->isSecure(),
                'httponly' => $cookieConfig->isHttpOnly(),
                'samesite' => $cookieConfig->getSameSite()
            ]
        );
    }

    private function start(CookieConfig $cookieConfig): bool
    {
        return \session_start($cookieConfig->getSessionConfigArray());
    }

    /**
     * session_start() and setcookie both create Set-Cookie headers
     */
    private function clearDuplicateCookieHeaders(): void
    {
        if (\headers_sent()) {
            return;
        }
        $cookies = [];
        foreach (\headers_list() as $header) {
            // Identify cookie headers
            if (\str_starts_with($header, 'Set-Cookie:')) {
                $cookies[] = $header;
            }
        }
        if (\count($cookies) > 1) {
            \header_remove('Set-Cookie');
            /** @var string $last */
            $last = last($cookies);
            \header($last, false);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$handler->get($key, $default);
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @return array<mixed>
     */
    public static function set(string $key, mixed $value): array
    {
        return self::$handler->set($key, $value);
    }

    /**
     * @param string[] $allowed
     */
    protected function getBrowserLanguage(array $allowed, string $default): string
    {
        /** @var string $acceptLanguage */
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null;
        if (empty($acceptLanguage)) {
            return $default;
        }
        $current = $default;
        $quality = 0;
        foreach (\preg_split('/,\s*/', $acceptLanguage) ?: [] as $lang) {
            $res = \preg_match(
                '/^([a-z]{1,8}(?:-[a-z]{1,8})*)(?:;\s*q=(0(?:\.\d{1,3})?|1(?:\.0{1,3})?))?$/i',
                $lang,
                $matches
            );
            if (!$res) {
                continue;
            }
            $codes       = \explode('-', $matches[1]);
            $langQuality = isset($matches[2])
                ? (float)$matches[2]
                : 1.0;
            while (\count($codes)) {
                if (
                    $langQuality > $quality
                    && \in_array(\mb_convert_case(\implode('-', $codes), \MB_CASE_LOWER), $allowed, true)
                ) {
                    $current = \mb_convert_case(\implode('-', $codes), \MB_CASE_LOWER);
                    $quality = $langQuality;
                    break;
                }
                \array_pop($codes);
            }
        }

        return $current;
    }
}
