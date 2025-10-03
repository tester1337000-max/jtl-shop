<?php

declare(strict_types=1);

namespace JTL\OPC\Portlets\Button;

use JTL\OPC\InputType;
use JTL\OPC\Portlet;

/**
 * Class Button
 * @package JTL\OPC\Portlets
 */
class Button extends Portlet
{
    /**
     * @inheritdoc
     */
    public function getPropertyDesc(): array
    {
        return [
            'label'      => [
                'label'   => \__('label'),
                'default' => \__('defaultText'),
                'width'   => 50,
            ],
            'url'        => [
                'label' => \__('url'),
                'width' => 50,
            ],
            'style'      => [
                'type'    => InputType::SELECT,
                'label'   => \__('style'),
                'default' => 'primary',
                'options' => [
                    'primary' => \__('stylePrimary'),
                    'success' => \__('styleSuccess'),
                    'info'    => \__('styleInfo'),
                    'warning' => \__('styleWarning'),
                    'danger'  => \__('styleDanger'),
                ],
                'width'   => 50,
            ],
            'new-tab'    => [
                'type'  => InputType::CHECKBOX,
                'label' => \__('openInNewTab'),
                'width' => 50,
                'desc'  => \__('openInNewTabDesc')
            ],
            'size'       => [
                'type'    => InputType::SELECT,
                'label'   => \__('size'),
                'default' => 'md',
                'options' => [
                    'sm' => 'S',
                    'md' => 'M',
                    'lg' => 'L',
                ],
                'width'   => 50,
            ],
            'link-title' => [
                'label' => \__('linkTitle'),
                'width' => 50,
            ],
            'align'      => [
                'type'    => InputType::SELECT,
                'label'   => \__('alignment'),
                'options' => [
                    'block'  => \__('useFullWidth'),
                    'left'   => \__('left'),
                    'right'  => \__('right'),
                    'center' => \__('centered'),
                ],
                'default' => 'left',
                'width'   => 50,
                'desc'    => \__('alignmentDesc')
            ],
            'outline'    => [
                'type'  => InputType::CHECKBOX,
                'label' => \__('styleOutline'),
                'width' => 50,
                'desc'  => \__('styleOutlineDesc')
            ],
            'use-icon'   => [
                'type'     => InputType::CHECKBOX,
                'label'    => \__('iconForButton'),
                'children' => [
                    'icon-align' => [
                        'type'    => InputType::SELECT,
                        'label'   => \__('iconAlignment'),
                        'options' => [
                            'left'  => \__('left'),
                            'right' => \__('right')
                        ],
                    ],
                    'icon'       => [
                        'type'  => InputType::ICON,
                        'label' => \__('Icon'),
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
            'Icon'           => [
                'use-icon',
            ],
            \__('Styles')    => 'styles',
            \__('Animation') => 'animations',
        ];
    }
}
