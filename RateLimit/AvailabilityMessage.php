<?php

declare(strict_types=1);

namespace JTL\RateLimit;

/**
 * class AvailabilityMessage
 * @package JTL\RateLimit
 */
class AvailabilityMessage extends AbstractRateLimiter
{
    protected string $type = 'availabilityMessage';

    protected int $floodMinutes = 2;

    protected int $cleanupMinutes = 3;

    protected int $entryLimit = 1;

    /**
     * @inheritdoc
     */
    public function getCleanupMinutes(): int
    {
        return $this->cleanupMinutes;
    }

    /**
     * @inheritdoc
     */
    public function setCleanupMinutes(int $minutes): void
    {
        $this->cleanupMinutes = $minutes;
    }

    /**
     * @inheritdoc
     */
    public function getFloodMinutes(): int
    {
        return $this->floodMinutes;
    }

    /**
     * @inheritdoc
     */
    public function setFloodMinutes(int $minutes): void
    {
        $this->floodMinutes = $minutes;
    }

    /**
     * @inheritdoc
     */
    public function getLimit(): int
    {
        return $this->entryLimit;
    }

    /**
     * @inheritdoc
     */
    public function setLimit(int $limit): void
    {
        $this->entryLimit = $limit;
    }
}
