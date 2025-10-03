<?php

declare(strict_types=1);

namespace JTL\OPC\Portlets\Text;

use JTL\OPC\InputType;
use JTL\OPC\Portlet;

/**
 * Class Text
 * @package JTL\OPC\Portlets
 */
class Text extends Portlet
{
    public function getButtonHtml(): string
    {
        return $this->getFontAwesomeButtonHtml('fas fa-font');
    }

    /**
     * @inheritdoc
     */
    public function getPropertyDesc(): array
    {
        return [
            'text' => [
                'label'   => \__('text'),
                'type'    => InputType::RICHTEXT,
                'default' => \__('exampleRichText'),
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
