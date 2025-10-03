<?php

declare(strict_types=1);

namespace JTL\OPC\Portlets\Container;

use JTL\OPC\InputType;
use JTL\OPC\Portlet;

/**
 * Class Container
 * @package JTL\OPC\Portlets
 */
class Container extends Portlet
{
    public function getButtonHtml(): string
    {
        return $this->getFontAwesomeButtonHtml('far fa-object-group');
    }

    /**
     * @inheritdoc
     */
    public function getPropertyDesc(): array
    {
        return [
            'min-height'      => [
                'type'    => InputType::NUMBER,
                'label'   => \__('minHeightPX'),
                'default' => 300,
                'width'   => 50,
            ],
            'boxed'           => [
                'type'    => InputType::CHECKBOX,
                'default' => false,
                'label'   => \__('boxedContainer'),
                'width'   => 50,
                'desc'    => \__('boxedContainerDesc')
            ],
            'background-flag' => [
                'type'        => InputType::RADIO,
                'label'       => \__('background'),
                'options'     => [
                    'still' => \__('image'),
                    'image' => \__('imageParallax'),
                    'video' => \__('backgroundVideo'),
                    'false' => \__('noBackground'),
                ],
                'default'     => 'false',
                'width'       => 50,
                'childrenFor' => [
                    'still' => [
                        'still-src' => [
                            'label' => \__('backgroundImage'),
                            'type'  => InputType::IMAGE,
                        ],
                    ],
                    'image' => [
                        'src'           => [
                            'label' => \__('backgroundImage'),
                            'type'  => InputType::IMAGE,
                        ],
                        'parallax-hint' => [
                            'type'  => InputType::HINT,
                            'class' => 'danger',
                            'text'  => \__('parallaxNote'),
                        ]
                    ],
                    'video' => [
                        'video-src'    => [
                            'type'  => InputType::VIDEO,
                            'label' => \__('video'),
                            'width' => 50,
                        ],
                        'video-poster' => [
                            'type'  => InputType::IMAGE,
                            'label' => \__('placeholderImage'),
                            'width' => 50,
                        ],
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
            \__('Styles')    => 'styles',
            \__('Animation') => 'animations',
        ];
    }
}
