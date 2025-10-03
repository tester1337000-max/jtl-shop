<?php

declare(strict_types=1);

namespace JTL\Helpers;

use JTL\SingletonTrait;

/**
 * Class PHPSettings
 * @package JTL\Helpers
 */
class PHPSettings
{
    use SingletonTrait;

    private function shortHandToInt(string $shorthand): int
    {
        return match (\mb_substr($shorthand, -1)) {
            'M', 'm' => (int)$shorthand * 1048576,
            'K', 'k' => (int)$shorthand * 1024,
            'G', 'g' => (int)$shorthand * 1073741824,
            default  => (int)$shorthand,
        };
    }

    public function limit(): int
    {
        return $this->shortHandToInt(\ini_get('memory_limit') ?: '0');
    }

    public function version(): string
    {
        return \PHP_VERSION;
    }

    public function executionTime(): int
    {
        return (int)\ini_get('max_execution_time');
    }

    public function postMaxSize(): int
    {
        return $this->shortHandToInt((string)(\ini_get('post_max_size') ?: 0));
    }

    public function uploadMaxFileSize(): int
    {
        return $this->shortHandToInt((string)(\ini_get('upload_max_filesize') ?: 0));
    }

    public function safeMode(): bool
    {
        return false;
    }

    public function tempDir(): string
    {
        return \sys_get_temp_dir();
    }

    public function fopenWrapper(): bool
    {
        return (bool)\ini_get('allow_url_fopen');
    }

    /**
     * @param int $limit - in bytes
     * @return bool
     */
    public function hasMinLimit(int $limit): bool
    {
        $value = $this->limit();

        return $value === -1 || $value === 0 || $value >= $limit;
    }

    /**
     * @param int $limit - in S
     * @return bool
     */
    public function hasMinExecutionTime(int $limit): bool
    {
        return ($this->executionTime() >= $limit || $this->executionTime() === 0);
    }

    /**
     * @param int $limit - in bytes
     * @return bool
     */
    public function hasMinPostSize(int $limit): bool
    {
        return $this->postMaxSize() >= $limit;
    }

    /**
     * @param int $limit - in bytes
     * @return bool
     */
    public function hasMinUploadSize(int $limit): bool
    {
        return $this->uploadMaxFileSize() >= $limit;
    }

    public function isTempWriteable(): bool
    {
        return \is_writable($this->tempDir());
    }

    /**
     * @former pruefeSOAP()
     * @since 5.0.0
     */
    public static function checkSOAP(string $url = ''): bool
    {
        return !(\mb_strlen($url) > 0 && !self::phpLinkCheck($url)) && \class_exists('SoapClient');
    }

    /**
     * @former pruefeCURL()
     * @since 5.0.0
     */
    public static function checkCURL(string $cURL = ''): bool
    {
        return !(\mb_strlen($cURL) > 0 && !self::phpLinkCheck($cURL)) && \function_exists('curl_init');
    }

    /**
     * @former pruefeALLOWFOPEN()
     * @since 5.0.0
     */
    public static function checkAllowFopen(): bool
    {
        return (int)\ini_get('allow_url_fopen') === 1;
    }

    /**
     * @former pruefeSOCKETS()
     * @since 5.0.0
     */
    public static function checkSockets(string $cSOCKETS = ''): bool
    {
        return !(\mb_strlen($cSOCKETS) > 0 && !self::phpLinkCheck($cSOCKETS)) && \function_exists('fsockopen');
    }

    /**
     * @former phpLinkCheck()
     * @since 5.0.0
     */
    public static function phpLinkCheck(string $url): bool
    {
        $errno  = null;
        $errstr = null;
        $parsed = \parse_url(\trim($url));
        $scheme = \mb_convert_case($parsed['scheme'] ?? '', \MB_CASE_LOWER);
        if ($parsed === false || !isset($parsed['host']) || ($scheme !== 'http' && $scheme !== 'https')) {
            return false;
        }
        if (!isset($parsed['port'])) {
            $parsed['port'] = 80;
        }

        return \fsockopen($parsed['host'], $parsed['port'], $errno, $errstr, 30) !== false;
    }
}
