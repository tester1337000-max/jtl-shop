<?php

declare(strict_types=1);

namespace JTL\Helpers;

use JTL\GeneralDataProtection\IpAnonymizer;
use JTL\Shop;

/**
 * Class Request
 * @package JTL\Helpers
 * @since 5.0.0
 */
class Request
{
    /**
     * @param string $var
     * @param mixed  $default
     * @return mixed|null
     */
    public static function getVar(string $var, mixed $default = null): mixed
    {
        return $_GET[$var] ?? $default;
    }

    /**
     * @param string $var
     * @param mixed  $default
     * @return mixed|null
     */
    public static function postVar(string $var, mixed $default = null): mixed
    {
        return $_POST[$var] ?? $default;
    }

    /**
     * @template T
     * @param string   $var
     * @param mixed<T> $default
     * @return ($default is int ? int : T)
     */
    public static function getInt(string $var, mixed $default = 0): mixed
    {
        return isset($_GET[$var]) ? (int)$_GET[$var] : $default;
    }

    public static function gInt(string $var): int
    {
        return (int)($_GET[$var] ?? '0');
    }

    /**
     * @return int[]
     */
    public static function gIntArray(string $var): array
    {
        return \array_map('\intval', (array)($_GET[$var] ?? []));
    }

    public static function pInt(string $var): int
    {
        return (int)($_POST[$var] ?? '0');
    }

    /**
     * @return int[]
     */
    public static function pIntArray(string $var): array
    {
        return \array_map('\intval', (array)($_POST[$var] ?? []));
    }

    public static function gString(string $var): string
    {
        return (string)($_GET[$var] ?? '');
    }

    public static function pString(string $var): string
    {
        return (string)($_POST[$var] ?? '');
    }

    /**
     * @template T
     * @param string   $var
     * @param mixed<T> $default
     * @return ($default is int ? int : T)
     */
    public static function postInt(string $var, mixed $default = 0): mixed
    {
        return isset($_POST[$var]) ? (int)$_POST[$var] : $default;
    }

    /**
     * @since 5.0.0
     */
    public static function hasGPCData(string $var): bool
    {
        return isset($_POST[$var]) || isset($_GET[$var]) || isset($_COOKIE[$var]);
    }

    /**
     * @return int[]
     * @since 5.0.0
     */
    public static function verifyGPDataIntegerArray(string $var): array
    {
        $val = $_REQUEST[$var] ?? null;
        if ($val === null || $val === '') {
            return [];
        }

        return \is_numeric($val)
            ? [(int)$val]
            : \array_map('\intval', (array)$val);
    }

    /**
     * @former verifyGPCDataInteger()
     * @since 5.0.0
     */
    public static function verifyGPCDataInt(string $var): int
    {
        return (int)($_GET[$var] ?? $_POST[$var] ?? $_COOKIE[$var] ?? 0);
    }

    /**
     * @since 5.0.0
     */
    public static function verifyGPDataString(string $var): string
    {
        if (isset($_POST[$var])) {
            return (string)$_POST[$var];
        }

        return isset($_GET[$var]) ? (string)$_GET[$var] : '';
    }

    /**
     * @former getRealIp()
     * @since 5.0.0
     */
    public static function getRealIP(): string
    {
        $ip = null;
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $list = \explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip   = $list[0];
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        // if the given IP is not valid, we return placeholders (note: placeholders are the "legacy way")
        if (!\filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4 | \FILTER_FLAG_IPV6)) {
            return (new IpAnonymizer($ip ?? ''))->getPlaceholder();
        }

        return $ip;
    }

    /**
     * Gibt einen String für einen Header mit dem angegebenen Status-Code aus
     * @since 5.0.0
     */
    public static function makeHTTPHeader(int $status): string
    {
        $proto = !empty($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
        $codes = [
            100 => $proto . ' 100 Continue',
            101 => $proto . ' 101 Switching Protocols',
            200 => $proto . ' 200 OK',
            201 => $proto . ' 201 Created',
            202 => $proto . ' 202 Accepted',
            203 => $proto . ' 203 Non-Authoritative Information',
            204 => $proto . ' 204 No Content',
            205 => $proto . ' 205 Reset Content',
            206 => $proto . ' 206 Partial Content',
            300 => $proto . ' 300 Multiple Choices',
            301 => $proto . ' 301 Moved Permanently',
            302 => $proto . ' 302 Found',
            303 => $proto . ' 303 See Other',
            304 => $proto . ' 304 Not Modified',
            305 => $proto . ' 305 Use Proxy',
            307 => $proto . ' 307 Temporary Redirect',
            400 => $proto . ' 400 Bad Request',
            401 => $proto . ' 401 Unauthorized',
            402 => $proto . ' 402 Payment Required',
            403 => $proto . ' 403 Forbidden',
            404 => $proto . ' 404 Not Found',
            405 => $proto . ' 405 Method Not Allowed',
            406 => $proto . ' 406 Not Acceptable',
            407 => $proto . ' 407 Proxy Authentication Required',
            408 => $proto . ' 408 Request Time-out',
            409 => $proto . ' 409 Conflict',
            410 => $proto . ' 410 Gone',
            411 => $proto . ' 411 Length Required',
            412 => $proto . ' 412 Precondition Failed',
            413 => $proto . ' 413 Request Entity Too Large',
            414 => $proto . ' 414 Request-URI Too Large',
            415 => $proto . ' 415 Unsupported Media Type',
            416 => $proto . ' 416 Requested range not satisfiable',
            417 => $proto . ' 417 Expectation Failed',
            500 => $proto . ' 500 Internal Server Error',
            501 => $proto . ' 501 Not Implemented',
            502 => $proto . ' 502 Bad Gateway',
            503 => $proto . ' 503 Service Unavailable',
            504 => $proto . ' 504 Gateway Time-out'
        ];

        return $codes[$status] ?? '';
    }

    /**
     * Prueft ob SSL aktiviert ist und auch durch Einstellung genutzt werden soll
     * -1 = SSL nicht aktiv und nicht erlaubt
     * 1 = SSL aktiv durch Einstellung nicht erwünscht
     * 2 = SSL aktiv und erlaubt
     * 4 = SSL nicht aktiv aber erzwungen
     *
     * @return int
     * @former pruefeSSL()
     * @since 5.0.0
     */
    public static function checkSSL(): int
    {
        $useSSL = Shop::getSettingValue(\CONF_GLOBAL, 'kaufabwicklung_ssl_nutzen');
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            $_SERVER['HTTPS'] = 'on';
        }
        if (
            isset($_SERVER['HTTPS']) &&
            (\mb_convert_case($_SERVER['HTTPS'], \MB_CASE_LOWER) === 'on' || $_SERVER['HTTPS'] === '1')
        ) {
            if ($useSSL === 'P') { // SSL durch Einstellung erlaubt?
                return 2;
            }

            return 1;
        }
        if ($useSSL === 'P') {
            return 4;
        }

        return -1;
    }

    public static function curl_exec_follow(\CurlHandle $ch, int $maxredirect = 5): bool|string
    {
        $mr = $maxredirect <= 0 ? 5 : $maxredirect;
        if (\ini_get('open_basedir') === '') {
            \curl_setopt($ch, \CURLOPT_FOLLOWLOCATION, true);
            \curl_setopt($ch, \CURLOPT_MAXREDIRS, $mr);
        } else {
            \curl_setopt($ch, \CURLOPT_FOLLOWLOCATION, false);
            $newurl = \curl_getinfo($ch, \CURLINFO_EFFECTIVE_URL);
            $rch    = \curl_copy_handle($ch);
            if ($rch === false) {
                throw new \Exception('Canno copy cURL handle');
            }
            \curl_setopt($rch, \CURLOPT_HEADER, true);
            \curl_setopt($rch, \CURLOPT_NOBODY, true);
            \curl_setopt($rch, \CURLOPT_FORBID_REUSE, false);
            \curl_setopt($rch, \CURLOPT_RETURNTRANSFER, true);
            do {
                \curl_setopt($rch, \CURLOPT_URL, $newurl);
                $header = \curl_exec($rch);
                if (\curl_errno($rch)) {
                    $code = 0;
                } else {
                    $code = \curl_getinfo($rch, \CURLINFO_HTTP_CODE);
                    if (
                        ($code === 301 || $code === 302)
                        && \is_string($header)
                        && \preg_match('/Location:(.*?)\n/i', $header, $matches)
                    ) {
                        $newurl = \trim(\array_pop($matches) ?? '');
                    } else {
                        $code = 0;
                    }
                }
            } while ($code && --$mr);
            \curl_close($rch);
            if (!$mr) {
                return false;
            }
            \curl_setopt($ch, \CURLOPT_URL, $newurl);
        }

        return \curl_exec($ch);
    }

    /**
     * @param string                   $url
     * @param int                      $timeout
     * @param array<mixed>|string|null $post
     * @return bool|int|string
     */
    public static function http_get_contents(
        string $url,
        int $timeout = 5,
        string|array|null $post = null
    ): bool|int|string {
        return self::make_http_request($url, $timeout, $post);
    }

    /**
     * @param string            $url
     * @param int               $timeout
     * @param array|string|null $post
     * @return bool|int|string
     */
    public static function http_get_status(
        string $url,
        int $timeout = 5,
        string|array|null $post = null
    ): bool|int|string {
        return self::make_http_request($url, $timeout, $post, true);
    }

    /**
     * @param string                   $url
     * @param int                      $timeout
     * @param array<mixed>|string|null $post
     * @param bool                     $state - false = return content on success / true = return status code
     * @param bool                     $skipStatusCheck
     * @return bool|int|string
     * @throws \Exception
     */
    public static function make_http_request(
        string $url,
        int $timeout = 5,
        string|array|null $post = null,
        bool $state = false,
        bool $skipStatusCheck = false
    ): bool|int|string {
        $status = 0;
        $data   = '';

        if (\function_exists('curl_init')) {
            $curl = \curl_init();
            if ($curl === false) {
                throw new \Exception('Cannot initialize cURL');
            }

            \curl_setopt($curl, \CURLOPT_URL, $url);
            \curl_setopt($curl, \CURLOPT_CONNECTTIMEOUT, $timeout);
            \curl_setopt($curl, \CURLOPT_TIMEOUT, $timeout);
            \curl_setopt($curl, \CURLOPT_RETURNTRANSFER, true);
            \curl_setopt($curl, \CURLOPT_SSL_VERIFYPEER, \DEFAULT_CURL_OPT_VERIFYPEER);
            \curl_setopt($curl, \CURLOPT_SSL_VERIFYHOST, \DEFAULT_CURL_OPT_VERIFYHOST);
            \curl_setopt($curl, \CURLOPT_REFERER, Shop::getURL());

            if ($post !== null) {
                \curl_setopt($curl, \CURLOPT_POST, true);
                \curl_setopt($curl, \CURLOPT_POSTFIELDS, $post);
            }

            $data   = self::curl_exec_follow($curl);
            $info   = \curl_getinfo($curl);
            $status = (int)$info['http_code'];

            \curl_close($curl);
        } elseif (\ini_get('allow_url_fopen')) {
            @\ini_set('default_socket_timeout', (string)$timeout);
            $fileHandle = @\fopen($url, 'rb');
            if ($fileHandle) {
                @\stream_set_timeout($fileHandle, $timeout);
                while (($buffer = \fgets($fileHandle)) !== false) {
                    $data .= $buffer;
                }
                if (\preg_match('|HTTP/\d\.\d\s+(\d+)\s+.*|', $http_response_header[0], $match)) {
                    $status = (int)$match[1];
                }
                \fclose($fileHandle);
            }
        }
        if ($skipStatusCheck === false && !($status >= 200 && $status < 300)) {
            $data = '';
        }

        return $state ? $status : $data;
    }

    /**
     * @since 5.0.0
     */
    public static function isAjaxRequest(): bool
    {
        return isset($_REQUEST['isAjax'])
            || (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
                && \mb_convert_case($_SERVER['HTTP_X_REQUESTED_WITH'], \MB_CASE_LOWER) === 'xmlhttprequest');
    }

    /**
     * Affiliate trennen
     * @former extFremdeParameter()
     * @since 5.0.0
     */
    public static function extractExternalParams(string $seo): string
    {
        $seoData = \preg_split('/[' . \EXT_PARAMS_SEPERATORS_REGEX . ']+/', $seo);
        if (\is_array($seoData) && \count($seoData) > 1) {
            $seo = $seoData[0];
            $cnt = \count($seoData);
            for ($i = 1; $i < $cnt; $i++) {
                $keyValue = \explode('=', $seoData[$i]);
                if (\count($keyValue) > 1) {
                    [$name, $value] = $keyValue;

                    $_SESSION['FremdParameter'][$name] = $value;
                }
            }
        }

        return $seo;
    }

    public static function urlHasEqualRequestParameter(string $url, string $parameterToCheck): bool
    {
        $urlParts = \parse_url($url);
        if (empty($urlParts['query'])) {
            $urlParts['query'] = [];
        } else {
            \parse_str($urlParts['query'], $urlParts['query']);
        }

        return self::verifyGPDataString($parameterToCheck) === ''
            || (isset($urlParts['query'][$parameterToCheck])
                && self::verifyGPDataString($parameterToCheck) === $urlParts['query'][$parameterToCheck]
            );
    }

    /**
     * @return string[]
     */
    public static function getReservedQueryParams(): array
    {
        return [
            \QUERY_PARAM_PRODUCT,
            \QUERY_PARAM_CATEGORY,
            \QUERY_PARAM_PAGE,
            \QUERY_PARAM_MANUFACTURER,
            \QUERY_PARAM_SEARCH_QUERY_ID,
            \QUERY_PARAM_CHARACTERISTIC_VALUE,
            \QUERY_PARAM_MANUFACTURER_FILTER,
            \QUERY_PARAM_CATEGORY_FILTER,
            \QUERY_PARAM_SEARCH_SPECIAL_FILTER,
            \QUERY_PARAM_SHOW,
            \QUERY_PARAM_SEARCH,
        ];
    }
}
