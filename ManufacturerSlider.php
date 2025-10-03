<?php

declare(strict_types=1);

namespace JTL\OPC\Portlets\ManufacturerSlider;

use JTL\Catalog\Hersteller;
use JTL\OPC\InputType;
use JTL\OPC\Portlet;
use JTL\OPC\PortletInstance;

/**
 * Class ManufacturerSlider
 * @package JTL\OPC\Portlets
 */
class ManufacturerSlider extends Portlet
{
    /**
     * @inheritdoc
     */
    public function getPropertyDesc(): array
    {
        $displayCountBase = [
            'type'  => InputType::NUMBER,
            'width' => 25,
        ];

        return [
            'presentation'   => [
                'type'    => InputType::SELECT,
                'label'   => \__('presentation'),
                'width'   => 50,
                'options' => [
                    'justImages'      => \__('justImages'),
                    'imagesWithNames' => \__('imagesWithNames'),
                ],
                'default' => 'imagesWithNames',
            ],
            'source'         => [
                'type'     => InputType::SELECT,
                'label'    => \__('manufacturerSource'),
                'width'    => 50,
                'options'  => [
                    'all'      => \__('manufacturerSourceAll'),
                    'explicit' => \__('manufacturerSourceExplicit'),
                ],
                'default'  => 'filter',
                'required' => true,
            ],
            'displayCountSM' => [
                ... $displayCountBase,
                'label'   => \__('displayCountSM'),
                'default' => 2,
                'desc'    => \__('displayCountDesc'),
            ],
            'displayCountMD' => [
                ... $displayCountBase,
                'label'   => \__('displayCountMD'),
                'default' => 3,
                'desc'    => \__('displayCountDesc'),
            ],
            'displayCountLG' => [
                ... $displayCountBase,
                'label'   => \__('displayCountLG'),
                'default' => 5,
                'desc'    => \__('displayCountDesc'),
            ],
            'displayCountXL' => [
                ... $displayCountBase,
                'label'   => \__('displayCountXL'),
                'default' => 7,
                'desc'    => \__('displayCountDesc'),
            ],
            'searchExplicit' => [
                'type'             => InputType::SEARCH,
                'label'            => '',
                'placeholder'      => \__('labelSearchManufacturer'),
                'width'            => 100,
                'constraintProp'   => 'source',
                'constraintValues' => ['explicit'],
            ],
            'itemIds'        => [
                'type'             => InputType::SEARCHPICKER,
                'searcher'         => 'searchExplicit',
                'dataIoFuncName'   => 'getManufacturers',
                'keyName'          => 'kHersteller',
                'constraintProp'   => 'source',
                'constraintValues' => ['explicit'],
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

    /**
     * @param PortletInstance $instance
     * @return Hersteller[]
     */
    public function getFilteredItems(PortletInstance $instance): array
    {
        if ($instance->getProperty('source') === 'explicit') {
            $itemIds = $instance->getProperty('itemIds');
            if (empty($itemIds)) {
                return [];
            }

            return Hersteller::getByIds(\explode(';', $itemIds));
        }

        return Hersteller::getAll(false);
    }
}
