<?php

declare(strict_types=1);

namespace JTL\Network;

use Exception;
use JTL\Helpers\Request;

/**
 * Class Communication
 * @package JTL\Network
 */
final class Communication
{
    /**
     * @param non-empty-string $url
     * @param array<mixed>     $postData
     * @throws Exception
     */
    private static function doCall(string $url, array $postData, bool $isPost = true): string
    {
        if (!\function_exists('curl_init')) {
            throw new Exception('Die PHP Funktion curl_init existiert nicht!');
        }
        $ch = \curl_init();
        if ($ch === false) {
            throw new Exception('Cannot initialize cURL');
        }
        \curl_setopt($ch, \CURLOPT_POST, $isPost);
        \curl_setopt($ch, \CURLOPT_POSTFIELDS, $postData);
        \curl_setopt(
            $ch,
            \CURLOPT_USERAGENT,
            'Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1'
        );
        \curl_setopt($ch, \CURLOPT_URL, $url);
        \curl_setopt($ch, \CURLOPT_ENCODING, 'UTF-8');
        \curl_setopt($ch, \CURLOPT_AUTOREFERER, true);
        \curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, \CURLOPT_CONNECTTIMEOUT, 60);
        \curl_setopt($ch, \CURLOPT_TIMEOUT, 60);
        /** @var string|false $content */
        $content = Request::curl_exec_follow($ch);
        if ($content === false) {
            throw new Exception('Cannot call server');
        }

        \curl_close($ch);

        return $content;
    }

    /**
     * @param non-empty-string $url
     * @throws Exception
     */
    public static function postData(string $url, mixed $data = [], bool $bPost = true): string
    {
        return \is_array($data)
            ? self::doCall($url, $data, $bPost)
            : '';
    }

    /**
     * @param non-empty-string $url
     * @throws Exception
     */
    public static function sendFile(string $url, string $file, bool $deleteFile = false): string
    {
        if (!\file_exists($file)) {
            return '';
        }
        $content = self::doCall($url, ['opt_file' => '@' . $file]);
        if ($deleteFile) {
            @\unlink($file);
        }

        return $content;
    }
}
