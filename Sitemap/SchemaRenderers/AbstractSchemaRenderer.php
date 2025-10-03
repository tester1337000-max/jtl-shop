<?php

declare(strict_types=1);

namespace JTL\Sitemap\SchemaRenderers;

/**
 * Class AbstractSchemaRenderer
 * @package JTL\Sitemap\SchemaRenderers
 */
abstract class AbstractSchemaRenderer implements SchemaRendererInterface
{
    /**
     * @var array<string, string[]>
     */
    protected array $config;

    protected string $xmlHeader = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

    /**
     * @return array<string, string[]>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param array<string, string[]> $config
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getXmlHeader(): string
    {
        return $this->xmlHeader;
    }

    public function setXmlHeader(string $xmlHeader): void
    {
        $this->xmlHeader = $xmlHeader;
    }
}
