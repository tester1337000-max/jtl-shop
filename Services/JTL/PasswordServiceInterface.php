<?php

declare(strict_types=1);

namespace JTL\Services\JTL;

/**
 * Interface PasswordServiceInterface
 * @package JTL\Services\JTL
 */
interface PasswordServiceInterface
{
    /**
     * only use for upgrading from shop 4 --> 5!
     *
     * @param string      $password
     * @param null|string $passwordHash
     * @return bool|string
     */
    public function cryptOldPasswort(string $password, ?string $passwordHash = null): bool|string;

    /**
     * @param int $length
     * @return string
     * @throws \Exception
     */
    public function generate(int $length): string;

    /**
     * @param string $password
     * @return string
     * @throws \Exception
     */
    public function hash(string $password): string;

    /**
     * @param string $password
     * @param string $hash
     * @return bool
     * @throws \Exception
     */
    public function verify(string $password, string $hash): bool;

    /**
     * @param string $hash
     * @return bool
     * @throws \Exception
     */
    public function needsRehash(string $hash): bool;

    /**
     * @param string $hash
     * @return array{algo: int, algoName: string, options: string[]}
     */
    public function getInfo(string $hash): array;

    /**
     * @param string $pass
     * @param string $validCharRegex
     * @return bool
     */
    public function hasOnlyValidCharacters(string $pass, string $validCharRegex = ''): bool;
}
