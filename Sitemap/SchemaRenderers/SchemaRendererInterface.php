<?php

declare(strict_types=1);

namespace JTL\Sitemap\SchemaRenderers;

/**
 * Interface SchemaRendererInterface
 * @package JTL\Sitemap\SchemaRenderers
 */
interface SchemaRendererInterface
{
    /**
     * @return array<string, string[]>
     */
    public function getConfig(): array;

    /**
     * @param array<string, string[]> $config
     */
    public function setConfig(array $config): void;

    /**
     * @param string[] $sitemapFiles
     * @return string
     */
    public function buildIndex(array $sitemapFiles): string;

    /**
     * @return string
     */
    public function buildHeader(): string;

    /**
     * @return string
     */
    public function buildFooter(): string;
}
