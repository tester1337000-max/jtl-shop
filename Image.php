<?php

declare(strict_types=1);

namespace JTL\OPC\Portlets\Image;

use JTL\OPC\InputType;
use JTL\OPC\Portlet;
use JTL\OPC\PortletInstance;

/**
 * Class Image
 * @package JTL\OPC\Portlets
 */
class Image extends Portlet
{
    public function getRoundedProp(PortletInstance $instance): bool|string
    {
        return match ($instance->getProperty('shape')) {
            'rounded' => true,
            'circle'  => 'circle',
            default   => false,
        };
    }

    public function getThumbnailProp(PortletInstance $instance): bool
    {
        return $instance->getProperty('shape') === 'thumbnail';
    }

    public function getButtonHtml(): string
    {
        return $this->getFontAwesomeButtonHtml('far fa-image');
    }

    /**
     * @inheritdoc
     */
    public function getPropertyDesc(): array
    {
        return [
            'src'     => [
                'label'   => \__('Image'),
                'type'    => InputType::IMAGE,
                'default' => '',
            ],
            'shape'   => [
                'label'   => \__('shape'),
                'type'    => InputType::SELECT,
                'options' => [
                    'normal'    => \__('shapeNormal'),
                    'rounded'   => \__('shapeRoundedCorners'),
                    'circle'    => \__('shapeCircle'),
                    'thumbnail' => \__('shapeThumbnail'),
                ],
                'width'   => 25,
            ],
            'align'   => [
                'type'    => InputType::SELECT,
                'label'   => \__('alignment'),
                'options' => [
                    'center' => \__('centered'),
                    'left'   => \__('left'),
                    'right'  => \__('right'),
                ],
                'default' => 'center',
                'width'   => 25,
                'desc'    => \__('alignmentDesc')
            ],
            'alt'     => [
                'label' => \__('alternativeText'),
                'desc'  => \__('altTextDesc'),
                'width' => 50,
            ],
            'title'   => [
                'label' => \__('attributeTitle'),
                'desc'  => \__('attributeTitleDesc'),
                'width' => 50,
            ],
            'is-link' => [
                'type'     => InputType::CHECKBOX,
                'label'    => \__('isLink'),
                'children' => [
                    'url'        => [
                        'type'  => InputType::TEXT,
                        'label' => \__('url'),
                        'width' => 50,
                        'desc'  => \__('imgUrlDesc')
                    ],
                    'link-title' => [
                        'label' => \__('linkTitle'),
                        'width' => 50,
                    ],
                    'new-tab'    => [
                        'type'  => InputType::CHECKBOX,
                        'label' => \__('openInNewTab'),
                        'width' => 50,
                        'desc'  => \__('openInNewTabDesc')
                    ],
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function getPropertyTabs(): array
    {
        return [
            \__('Styles') => 'styles',
        ];
    }
}
