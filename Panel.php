<?php

declare(strict_types=1);

namespace JTL\OPC\Portlets\Panel;

use JTL\OPC\InputType;
use JTL\OPC\Portlet;

/**
 * Class Panel
 * @package JTL\OPC\Portlets
 */
class Panel extends Portlet
{
    public function getButtonHtml(): string
    {
        return $this->getFontAwesomeButtonHtml('far fa-square');
    }

    /**
     * @inheritdoc
     */
    public function getPropertyDesc(): array
    {
        return [
            'panel-state' => [
                'label'   => \__('panelType'),
                'type'    => InputType::SELECT,
                'width'   => 30,
                'options' => [
                    'default' => \__('standard'),
                    'primary' => \__('stylePrimary'),
                    'success' => \__('styleSuccess'),
                    'info'    => \__('styleInfo'),
                    'warning' => \__('styleWarning'),
                    'danger'  => \__('styleDanger'),
                ],
            ],
            'title-flag'  => [
                'label' => \__('showHeader'),
                'type'  => InputType::CHECKBOX,
                'width' => 30,
            ],
            'footer-flag' => [
                'label' => \__('showFooter'),
                'type'  => InputType::CHECKBOX,
                'width' => 30,
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
