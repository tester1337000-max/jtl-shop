<?php

declare(strict_types=1);

namespace JTL\OPC\Portlets\Tabs;

use JTL\OPC\InputType;
use JTL\OPC\Portlet;

/**
 * Class Tabs
 * @package JTL\OPC\Portlets
 */
class Tabs extends Portlet
{
    /**
     * @inheritdoc
     */
    public function getPropertyDesc(): array
    {
        return [
            'tabs' => [
                'label'   => \__('Tabs'),
                'type'    => InputType::TEXT_LIST,
                'default' => [\__('tabOne'), \__('tabTwo'), \__('tabThree')],
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
