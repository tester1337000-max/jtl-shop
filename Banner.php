<?php

declare(strict_types=1);

namespace JTL\OPC\Portlets\Banner;

use JTL\Catalog\Product\Artikel;
use JTL\OPC\InputType;
use JTL\OPC\Portlet;

/**
 * Class Banner
 * @package JTL\OPC\Portlets
 */
class Banner extends Portlet
{
    public function getProduct(int $productID): ?Artikel
    {
        return (new Artikel())->fuelleArtikel($productID, Artikel::getDefaultOptions());
    }

    public function getPlaceholderImgUrl(): string
    {
        return $this->getBaseUrl() . 'preview.banner.jpg';
    }

    /**
     * @inheritdoc
     */
    public function getPropertyDesc(): array
    {
        return [
            'src'   => [
                'type'  => InputType::IMAGE,
                'label' => \__('Image'),
                'thumb' => true,
            ],
            'alt'   => [
                'label' => \__('alternativeText'),
                'desc'  => \__('altTextDesc'),
                'width' => 50,
            ],
            'title' => [
                'label' => \__('attributeTitle'),
                'desc'  => \__('attributeTitleDesc'),
                'width' => 50,
            ],
            'zones' => [
                'type'    => InputType::ZONES,
                'label'   => \__('bannerAreas'),
                'srcProp' => 'src',
                'default' => [],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function getPropertyTabs(): array
    {
        return [
            \__('Styles')    => 'styles',
            \__('Animation') => 'animations',
        ];
    }
}
