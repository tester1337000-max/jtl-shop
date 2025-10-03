<?php

declare(strict_types=1);

namespace JTL\Optin;

/**
 * Class OptinFactory
 * @package JTL\Optin
 */
abstract class OptinFactory
{
    /**
     * @param class-string<OptinInterface> $optinClass
     * @param array<mixed>                 $inheritData
     * @return OptinInterface|null
     */
    public static function getInstance(string $optinClass, ...$inheritData): ?OptinInterface
    {
        return \class_exists($optinClass) ? new $optinClass($inheritData) : null;
    }
}
