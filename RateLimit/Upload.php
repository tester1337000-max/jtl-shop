<?php

declare(strict_types=1);

namespace JTL\RateLimit;

/**
 * class Upload
 * @package JTL\RateLimit
 */
class Upload extends AbstractRateLimiter
{
    protected string $type = 'upload';

    protected const FLOOD_MINUTES = 60;

    private int $limit = 10;

    /**
     * @inheritdoc
     */
    public function check(?array $args = null): bool
    {
        $items = $this->db->getSingleInt(
            'SELECT COUNT(*) AS cnt
                FROM tfloodprotect
                WHERE cIP = :ip
                    AND cTyp = :tpe
                    AND TIMESTAMPDIFF(MINUTE, dErstellt, NOW()) < :td',
            'cnt',
            [
                'tpe' => $this->type,
                'ip'  => $this->ip,
                'td'  => $this->getFloodMinutes()
            ]
        );

        return $items <= $this->getLimit();
    }

    /**
     * @inheritdoc
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @inheritdoc
     */
    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }
}
