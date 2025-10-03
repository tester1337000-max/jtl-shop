<?php

declare(strict_types=1);

namespace JTL\RateLimit;

use JTL\DB\DbInterface;
use JTL\Model\DataModelInterface;

/**
 * Interface RateLimiterInterface
 * @package JTL\RateLimit
 */
interface RateLimiterInterface
{
    /**
     * @param string $ip
     * @param int    $key
     */
    public function init(string $ip, int $key = 0): void;

    /**
     * @param array<string, string>|null $args
     * @return bool
     */
    public function check(?array $args = null): bool;

    /**
     * @return bool
     */
    public function persist(): bool;

    /**
     * @return DataModelInterface
     */
    public function getModel(): DataModelInterface;

    /**
     * @return DataModelInterface
     */
    public function initModel(): DataModelInterface;

    public function cleanup(): void;

    /**
     * @return int - max allowed items per time
     */
    public function getLimit(): int;

    /**
     * @param int $limit
     */
    public function setLimit(int $limit): void;

    /**
     * @return int - min age of items to clean up in minutes
     */
    public function getCleanupMinutes(): int;

    /**
     * @param int $minutes
     */
    public function setCleanupMinutes(int $minutes): void;

    /**
     * @return int - minutes to block further requests
     */
    public function getFloodMinutes(): int;

    /**
     * @param int $minutes
     */
    public function setFloodMinutes(int $minutes): void;

    /**
     * @return DbInterface
     */
    public function getDB(): DbInterface;

    /**
     * @param DbInterface $db
     */
    public function setDB(DbInterface $db): void;

    /**
     * @return string
     */
    public function getIP(): string;

    /**
     * @param string $ip
     */
    public function setIP(string $ip): void;

    /**
     * @return int
     */
    public function getKey(): int;

    /**
     * @param int $key
     */
    public function setKey(int $key): void;

    /**
     * @return string
     */
    public function getType(): string;

    /**
     * @param string $type
     */
    public function setType(string $type): void;
}
