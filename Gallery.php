<?php

declare(strict_types=1);

namespace JTL\OPC\Portlets\Gallery;

use JTL\OPC\InputType;
use JTL\OPC\Portlet;

/**
 * Class Gallery
 * @package JTL\OPC\Portlets
 */
class Gallery extends Portlet
{
    /**
     * @inheritdoc
     */
    public function getPropertyDesc(): array
    {
        return [
            'galleryStyle' => [
                'type'  => InputType::GALLERY_LAYOUT,
                'label' => 'Layout',
            ],
            'images'       => [
                'type'        => InputType::IMAGE_SET,
                'label'       => \__('imageList'),
                'default'     => [],
                'useLinks'    => true,
                'useLightbox' => true,
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
