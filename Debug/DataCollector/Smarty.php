<?php

declare(strict_types=1);

namespace JTL\Debug\DataCollector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use JTL\Smarty\JTLSmarty;

/**
 * Class Smarty
 * @package JTL\Debug\DataCollector
 */
class Smarty extends DataCollector implements Renderable
{
    public function __construct(protected JTLSmarty $smarty)
    {
    }

    /**
     * @return array<string, string>
     */
    public function collect(): array
    {
        $data      = [];
        $vars      = $this->smarty->getTemplateVars();
        $formatter = $this->getDataFormatter();
        foreach ($vars as $idx => $var) {
            $data[$idx] = $formatter->formatVar($var);
        }

        return $data;
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'smarty';
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getWidgets(): array
    {
        return [
            'smarty' => [
                'icon'    => 'tags',
                'widget'  => 'PhpDebugBar.Widgets.VariableListWidget',
                'map'     => 'smarty',
                'default' => '{}'
            ]
        ];
    }
}
