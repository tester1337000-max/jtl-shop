<?php

declare(strict_types=1);

namespace JTL\Contracts;

/**
 * Interface RoutableInterface
 * @package JTL\Contracts
 */
interface RoutableInterface
{
    /**
     * @return string
     */
    public function getRouteType(): string;

    /**
     * @param string $routeType
     */
    public function setRouteType(string $routeType): void;

    /**
     * @param int|null    $languageID
     * @param string|null $locale
     */
    public function initLanguageID(?int $languageID = null, ?string $locale = null): void;

    /**
     * @param int|null $fallbackID
     */
    public function createBySlug(?int $fallbackID = null): void;

    /**
     * @return array<int, string>
     */
    public function getURLs(): array;

    /**
     * @param array<int, string> $urls
     */
    public function setURLs(array $urls): void;

    /**
     * @param int|null $idx
     * @return string|null
     */
    public function getURL(?int $idx = null): ?string;

    /**
     * @param string   $url
     * @param int|null $idx
     */
    public function setURL(string $url, ?int $idx = null): void;

    /**
     * @return array<int, string>
     */
    public function getURLPaths(): array;

    /**
     * @param array<int, string> $paths
     */
    public function setURLPaths(array $paths): void;

    /**
     * @param int|null $idx
     * @return string|null
     */
    public function getURLPath(?int $idx = null): ?string;

    /**
     * @param string   $path
     * @param int|null $idx
     */
    public function setURLPath(string $path, ?int $idx = null): void;

    /**
     * @return string[]
     */
    public function getSlugs(): array;

    /**
     * @param int|null $idx
     * @return string
     */
    public function getSlug(?int $idx = null): string;

    /**
     * @param string   $seo
     * @param int|null $idx
     */
    public function setSlug(string $seo, ?int $idx = null): void;
}
