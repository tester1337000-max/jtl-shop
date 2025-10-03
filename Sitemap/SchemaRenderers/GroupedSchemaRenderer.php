<?php

declare(strict_types=1);

namespace JTL\Sitemap\SchemaRenderers;

/**
 * Class GroupedSchemaRenderer
 * @package JTL\Sitemap\SchemaRenderers
 */
final class GroupedSchemaRenderer extends AbstractSchemaRenderer
{
    /**
     * @param string[] $sitemapFiles
     */
    public function buildIndex(array $sitemapFiles): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($sitemapFiles as $url) {
            $xml .= "<sitemap>\n<loc>" . $url . "</loc>\n";
            if ($this->config['sitemap']['sitemap_insert_lastmod'] === 'Y') {
                $xml .= '<lastmod>' . \date('Y-m-d') . '</lastmod>' . "\n";
            }
            $xml .= '</sitemap>' . "\n";
        }

        return $xml;
    }

    public function buildHeader(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';

        if (
            $this->config['sitemap']['sitemap_googleimage_anzeigen'] === 'Y' ||
            $this->config['sitemap']['sitemap_images_categories'] === 'Y' ||
            $this->config['sitemap']['sitemap_images_manufacturers'] === 'Y' ||
            $this->config['sitemap']['sitemap_images_newscategory_items'] === 'Y' ||
            $this->config['sitemap']['sitemap_images_news_items'] === 'Y' ||
            $this->config['sitemap']['sitemap_images_attributes'] === 'Y'
        ) {
            $xml .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
        }

        $xml .= ' xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        return $xml;
    }

    public function buildFooter(): string
    {
        return '</urlset>';
    }
}
