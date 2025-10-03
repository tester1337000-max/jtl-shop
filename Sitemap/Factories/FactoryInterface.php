<?php

declare(strict_types=1);

namespace JTL\Sitemap\Factories;

use Generator;
use JTL\Language\LanguageModel;

/**
 * Interface FactoryInterface
 * @package JTL\Sitemap\Factories
 */
interface FactoryInterface
{
    /**
     * @param LanguageModel[] $languages
     * @param int[]           $customerGroups
     * @return Generator
     */
    public function getCollection(array $languages, array $customerGroups): Generator;
}
