<?php

declare(strict_types=1);

namespace JTL\OPC\Portlets\Flipcard;

use JTL\OPC\InputType;
use JTL\OPC\Portlet;

/**
 * Class Flipcard
 * @package JTL\OPC\Portlets
 */
class Flipcard extends Portlet
{
    /**
     * @inheritdoc
     */
    public function getPropertyDesc(): array
    {
        return [
            'flip-dir'     => [
                'type'    => InputType::RADIO,
                'label'   => \__('flipcardOrientation'),
                'width'   => 50,
                'options' => [
                    'v' => \__('vertical'),
                    'h' => \__('horizontal'),
                ],
                'default' => 'v',
                'desc'    => \__('flipDirDesc'),
            ],
            'flip-trigger' => [
                'type'    => InputType::RADIO,
                'label'   => \__('flipTrigger'),
                'width'   => 50,
                'options' => [
                    'click' => \__('onClick'),
                    'hover' => \__('onHover'),
                ],
                'default' => 'click',
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
