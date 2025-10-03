<?php

declare(strict_types=1);

namespace JTL\Sitemap\SchemaRenderers;

/**
 * Class DefaultSchemaRenderer
 * @package JTL\Sitemap\SchemaRenderers
 */
final class DefaultSchemaRenderer extends AbstractSchemaRenderer
{
    /**
     * @param string[] $sitemapFiles
     */
    public function buildIndex(array $sitemapFiles): string
    {
        $xml = $this->getXmlHeader();
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($sitemapFiles as $url) {
            $xml .= "<sitemap>\n<loc>" . $url . "</loc>\n";
            if ($this->config['sitemap']['sitemap_insert_lastmod'] === 'Y') {
                $xml .= '<lastmod>' . \date('Y-m-d') . '</lastmod>' . "\n";
            }
            $xml .= '</sitemap>' . "\n";
        }
        $xml .= '</sitemapindex>' . "\n";

        return $xml;
    }

    public function buildHeader(): string
    {
        $xml = $this->getXmlHeader();
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

        $xml .= ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . "\n";
        $xml .= '  xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9' . "\n";
        $xml .= '  http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">' . "\n";

        return $xml;
    }

    public function buildFooter(): string
    {
        return '</urlset>';
    }
}
