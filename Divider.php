<?php

declare(strict_types=1);

namespace JTL\OPC\Portlets\Divider;

use JTL\OPC\Portlet;

/**
 * Class Divider
 * @package JTL\OPC\Portlets
 */
class Divider extends Portlet
{
    /**
     * @inheritdoc
     */
    public function getPropertyDesc(): array
    {
        return [
            'title'     => [
                'label'   => \__('dividerTitle'),
                'default' => \__('Divider'),
                'width'   => 50,
            ],
            'id'        => [
                'label' => \__('dividerElmID'),
                'desc'  => \__('dividerIdDesc'),
                'width' => 50,
            ],
            'moreLink'  => [
                'label' => \__('dividerMoreLink'),
                'width' => 50,
            ],
            'moreTitle' => [
                'label' => \__('dividerMoreTitle'),
                'width' => 50,
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
