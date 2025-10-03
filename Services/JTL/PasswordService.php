<?php

declare(strict_types=1);

namespace JTL\Services\JTL;

/**
 * Class PasswordService
 * @package JTL\Services\JTL
 */
class PasswordService implements PasswordServiceInterface
{
    /**
     * The lowest allowed ascii character in decimal representation
     */
    public const ASCII_MIN = 33;

    /**
     * The highest allowed ascii character in decimal representation
     */
    public const ASCII_MAX = 126;

    public function __construct(protected CryptoServiceInterface $cryptoService)
    {
    }

    /**
     * @inheritdoc
     */
    public function cryptOldPasswort(#[\SensitiveParameter] string $password, ?string $passwordHash = null): bool|string
    {
        $salt = \sha1(\uniqid((string)\mt_rand(), true));
        $len  = \mb_strlen($salt);
        $len  = \max($len >> 3, ($len >> 2) - \mb_strlen($password));
        $salt = $passwordHash
            ? \mb_substr($passwordHash, \min(\mb_strlen($password), \mb_strlen($passwordHash) - $len), $len)
            : \strrev(\mb_substr($salt, 0, $len));
        $hash = \sha1($password);
        $hash = \sha1(\mb_substr($hash, 0, \mb_strlen($password)) . $salt . \mb_substr($hash, \mb_strlen($password)));
        $hash = \mb_substr($hash, $len);
        $hash = \mb_substr($hash, 0, \mb_strlen($password)) . $salt . \mb_substr($hash, \mb_strlen($password));

        return $passwordHash && $passwordHash !== $hash ? false : $hash;
    }

    /**
     * @inheritdoc
     */
    public function generate(int $length): string
    {
        /**
         * I have chosen to not use random_bytes, because using special characters in passwords is recommended. It is
         * therefore better to generate a password with random_int using a char whitelist.
         * Note: random_int is cryptographically secure
         */
        $result = '';
        for ($x = 0; $x < $length; $x++) {
            $number = $this->cryptoService->randomInt(self::ASCII_MIN, self::ASCII_MAX);
            $result .= \chr($number);
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function hash(#[\SensitiveParameter] string $password): string
    {
        return \password_hash($password, \PASSWORD_DEFAULT);
    }

    /**
     * @inheritdoc
     */
    public function verify(#[\SensitiveParameter] string $password, string $hash): bool
    {
        $length = \mb_strlen($hash);
        if ($length === 32) {
            // very old md5 hashes
            return \md5($password) === $hash;
        }
        if ($length === 40) {
            return $this->cryptOldPasswort($password, $hash) !== false;
        }

        return \password_verify($password, $hash);
    }

    /**
     * @inheritdoc
     */
    public function needsRehash(string $hash): bool
    {
        $length = \mb_strlen($hash);

        return $length === 32 || $length === 40 || \password_needs_rehash($hash, \PASSWORD_DEFAULT);
    }

    /**
     * @inheritdoc
     */
    public function getInfo(string $hash): array
    {
        return \password_get_info($hash);
    }

    /**
     * @inheritdoc
     */
    public function hasOnlyValidCharacters(#[\SensitiveParameter] string $pass, string $validCharRegex = ''): bool
    {
        return !\preg_match(
            $validCharRegex ?: '/[^A-Za-z0-9\!"\#\$%&\'\(\)\*\+,-\.\/:;\=\>\?@\[\\\\\]\^_`\|\}~]/',
            $pass
        );
    }
}
