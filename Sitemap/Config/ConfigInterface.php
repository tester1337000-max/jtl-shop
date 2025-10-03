<?php

declare(strict_types=1);

namespace JTL\Sitemap\Config;

use JTL\Sitemap\Factories\FactoryInterface;

/**
 * Interface ConfigInterface
 * @package JTL\Sitemap\Config
 */
interface ConfigInterface
{
    /**
     * @return FactoryInterface[]
     */
    public function getFactories(): array;
}
