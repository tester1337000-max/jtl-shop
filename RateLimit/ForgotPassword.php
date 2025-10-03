<?php

declare(strict_types=1);

namespace JTL\RateLimit;

/**
 * class ForgotPassword
 * @package JTL\RateLimit
 */
class ForgotPassword extends AbstractRateLimiter
{
    protected string $type = 'forgotpassword';

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
}
