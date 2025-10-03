<?php

declare(strict_types=1);

namespace JTL\TwoFA;

use JTL\DB\DbInterface;

/**
 * Class UserData
 * @package JTL\TwoFA
 */
abstract class UserData
{
    public function __construct(
        private int $id = 0,
        private string $name = '',
        private string $secret = '',
        private bool $use2FA = false
    ) {
    }

    public function getID(): int
    {
        return $this->id;
    }

    public function setID(int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function setSecret(string $secret): void
    {
        $this->secret = $secret;
    }

    public function use2FA(): bool
    {
        return $this->use2FA;
    }

    public function setUse2FA(bool $use2FA): void
    {
        $this->use2FA = $use2FA;
    }

    abstract public function getTableName(): string;

    abstract public function getEmergencyCodeTableName(): string;

    abstract public function getKeyName(): string;

    abstract public static function getByName(string $userName, DbInterface $db): self;

    abstract public static function getByID(int $id, DbInterface $db): self;
}
