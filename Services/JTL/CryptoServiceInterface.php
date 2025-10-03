<?php

declare(strict_types=1);

namespace JTL\Services\JTL;

/**
 * Interface CryptoServiceInterface
 *
 * @package JTL\Services\JTL
 */
interface CryptoServiceInterface
{
    /**
     * @param int<1, max> $bytesAmount
     * @return string
     * @throws \Exception
     */
    public function randomBytes(int $bytesAmount): string;

    /**
     * @param int<1, max> $bytesAmount
     * @return string
     * @throws \Exception
     */
    public function randomString(int $bytesAmount): string;

    /**
     * @param int $min
     * @param int $max
     * @return int
     * @throws \Exception
     */
    public function randomInt(int $min, int $max): int;

    /**
     * @param string $string1
     * @param string $string2
     * @return bool
     */
    public function stableStringEquals(string $string1, string $string2): bool;

    /**
     * @param string $text
     * @return string
     */
    public function encryptXTEA(string $text): string;

    /**
     * @param string $text
     * @return string
     */
    public function decryptXTEA(string $text): string;
}
