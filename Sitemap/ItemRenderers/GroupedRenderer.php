<?php

declare(strict_types=1);

namespace JTL\Sitemap\ItemRenderers;

use JTL\Sitemap\Items\ItemInterface;

/**
 * Class GroupedRenderer
 * @package Sitemap\ItemRenderers
 */
final class GroupedRenderer extends AbstractItemRenderer
{
    /**
     * @param ItemInterface[] $alternateItems
     */
    public function actualRender(ItemInterface $item, array $alternateItems): string
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
        foreach ($alternateItems as $alternate) {
            $xml .= '    <xhtml:link rel="alternate" hreflang="' .
                $alternate->getLanguageCode639() . '" href="' .
                $alternate->getLocation() . '" />' . "\n";
        }

        return $xml . "</url>\n";
    }

    /**
     * @inheritdoc
     */
    public function renderItem(ItemInterface $item): string
    {
        $primary = $item->getPrimaryKeyID();
        $id      = \get_class($item) . $primary;
        if ($this->lastID === null) {
            $this->lastID = $id;
        }
        if ($this->lastID !== $id || $primary === 0) {
            $res          = $this->renderGroup($this->queue);
            $this->lastID = $id;
            $this->queue  = [$item];

            return $res;
        }
        $this->queue[] = $item;

        return '';
    }

    /**
     * @param ItemInterface[] $group
     */
    public function renderGroup(array $group): string
    {
        $xml            = '';
        $alternateItems = \count($group) > 1 ? $group : [];
        foreach ($group as $item) {
            $xml .= $this->actualRender($item, $alternateItems);
        }

        return $xml;
    }

    /**
     * @inheritdoc
     */
    public function flush(): string
    {
        $res         = $this->renderGroup($this->queue);
        $this->queue = [];

        return $res;
    }
}
