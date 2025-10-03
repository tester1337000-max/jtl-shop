<?php

declare(strict_types=1);

namespace JTL\DB\Services;

/**
 * Interface GcServiceInterface
 * @package JTL\DB\Services
 */
interface GcServiceInterface
{
    /**
     * @return $this
     */
    public function run(): GcServiceInterface;
}
