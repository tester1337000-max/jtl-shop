<?php

declare(strict_types=1);

namespace JTL\Services\JTL;

use JTL\xtea\XTEA;

/**
 * Class CryptoService
 * @package JTL\Services\JTL
 */
class CryptoService implements CryptoServiceInterface
{
    /**
     * @inheritdoc
     */
    public function randomBytes(int $bytesAmount): string
    {
        return \random_bytes($bytesAmount);
    }

    /**
     * @inheritdoc
     */
    public function randomString(int $bytesAmount): string
    {
        return \bin2hex($this->randomBytes($bytesAmount));
    }

    /**
     * @inheritdoc
     */
    public function randomInt(int $min, int $max): int
    {
        return \random_int($min, $max);
    }

    /**
     * @inheritdoc
     */
    public function stableStringEquals(string $string1, string $string2): bool
    {
        return \hash_equals($string1, $string2);
    }

    /**
     * @inheritdoc
     */
    public function encryptXTEA(string $text): string
    {
        return \mb_strlen($text) > 0
            ? (new XTEA(\BLOWFISH_KEY))->encrypt($text)
            : $text;
    }

    /**
     * @inheritdoc
     */
    public function decryptXTEA(string $text): string
    {
        return \mb_strlen($text) > 0
            ? (new XTEA(\BLOWFISH_KEY))->decrypt($text)
            : $text;
    }
}
