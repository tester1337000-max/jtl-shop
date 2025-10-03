<?php

declare(strict_types=1);

namespace JTL\Services\JTL;

/**
 * Interface ConsentServiceInterface
 * @package JTL\Services\JTL
 */
interface ConsentServiceInterface
{
    public function register(): void;

    /**
     * @return bool
     */
    public function hasConsent(): bool;
}
