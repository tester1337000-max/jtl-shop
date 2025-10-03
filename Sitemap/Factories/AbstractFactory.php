<?php

declare(strict_types=1);

namespace JTL\Sitemap\Factories;

use JTL\DB\DbInterface;

/**
 * Class AbstractFactory
 * @package JTL\Sitemap\Factories
 */
abstract class AbstractFactory implements FactoryInterface
{
    /**
     * @param array<string, string[]> $config
     */
    public function __construct(
        protected DbInterface $db,
        protected array $config,
        protected string $baseURL,
        protected string $baseImageURL
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $res           = \get_object_vars($this);
        $res['db']     = '*truncated*';
        $res['config'] = '*truncated*';

        return $res;
    }
}
