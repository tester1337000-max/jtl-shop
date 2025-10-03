<?php

declare(strict_types=1);

namespace JTL\Sitemap\ItemRenderers;

use JTL\Sitemap\Items\ItemInterface;

/**
 * Class DefaultRenderer
 * @package JTL\Sitemap\ItemRenderers
 */
final class DefaultRenderer extends AbstractItemRenderer
{
    /**
     * @inheritdoc
     */
    public function renderItem(ItemInterface $item): string
    {
        $xml = "<url>\n" .
            '    <loc>' . $item->getLocation() . "</loc>\n";
        if (!empty($item->getImage())) {
            $xml .=
                "    <image:image>\n" .
                '        <image:loc>' . $item->getImage() . "</image:loc>\n" .
                "    </image:image>\n";
        }
        if ($this->config['sitemap']['sitemap_insert_lastmod'] === 'Y' && !empty($item->getLastModificationTime())) {
            $xml .= '    <lastmod>' . $item->getLastModificationTime() . "</lastmod>\n";
        }
        if ($this->config['sitemap']['sitemap_insert_changefreq'] === 'Y' && !empty($item->getChangeFreq())) {
            $xml .= '    <changefreq>' . $item->getChangeFreq() . "</changefreq>\n";
        }
        if ($this->config['sitemap']['sitemap_insert_priority'] === 'Y' && !empty($item->getPriority())) {
            $xml .= '    <priority>' . $item->getPriority() . "</priority>\n";
        }

        return $xml . "  </url>\n";
    }
}
