<?php

declare(strict_types=1);

namespace JTL\OPC\Portlets\Row;

use JTL\OPC\InputType;
use JTL\OPC\Portlet;
use JTL\OPC\PortletInstance;

/**
 * Class Row
 * @package JTL\OPC\Portlets
 */
class Row extends Portlet
{
    public function getButtonHtml(): string
    {
        return $this->getFontAwesomeButtonHtml('fas fa-columns');
    }

    /**
     * @inheritdoc
     */
    public function getPropertyDesc(): array
    {
        return [
            'layout' => [
                'type'    => InputType::ROW_LAYOUT,
                'label'   => \__('Layout'),
                'presets' => [
                    [
                        'name'   => '2 ' . \__('Columns'),
                        'layout' => ['12+12', '6+6', '6+6', '6+6'],
                    ],
                    [
                        'name'   => '3 ' . \__('Columns'),
                        'layout' => ['12+12+12', '6+6+12', '4+4+4', '4+4+4'],
                    ],
                    [
                        'name'   => '4 ' . \__('Columns'),
                        'layout' => ['12+12+12+12', '6+6+6+6', '3+3+3+3', '3+3+3+3'],
                    ],
                    [
                        'name'   => '6 ' . \__('Columns'),
                        'layout' => ['6+6+6+6+6+6', '4+4+4+4+4+4', '4+4+4+4+4+4', '2+2+2+2+2+2'],
                    ],
                    [
                        'name'   => '2 ' . \__('Columns') . ' (2:1)',
                        'layout' => ['12+12', '6+6', '8+4', '8+4'],
                    ],
                    [
                        'name'   => '2 ' . \__('Columns') . ' (1:2)',
                        'layout' => ['12+12', '6+6', '4+8', '4+8'],
                    ],
                ],
                'default' => [
                    'preset' => 0,
                    'xs'     => '12+12',
                    'sm'     => '6+6',
                    'md'     => '6+6',
                    'lg'     => '6+6',
                ],
                'desc'    => \__('rowLayoutDesc'),
            ],
            'hint'   => [
                'type'  => InputType::HINT,
                'class' => 'warning',
                'text'  => \__('rowLayoutWarning'),
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

    /**
     * @param PortletInstance $instance
     * @return array<array{xs: string|int, sm: string|int, md: string|int, lg: string|int,
     *      divider: array{xs: bool, sm: bool, md: bool, lg: bool}}>
     */
    public function getLayouts(PortletInstance $instance): array
    {
        /** @var array{xs: string, sm: string, md: string, lg: string} $layouts */
        $layouts  = $instance->getProperty('layout');
        $layoutXS = \explode('+', $layouts['xs']);
        $layoutSM = \explode('+', $layouts['sm']);
        $layoutMD = \explode('+', $layouts['md']);
        $layoutLG = \explode('+', $layouts['lg']);
        $colCount = \max(\count($layoutXS), \count($layoutSM), \count($layoutMD), \count($layoutLG));

        $layoutXS = \array_map('\intval', $layoutXS);
        $layoutSM = \array_map('\intval', $layoutSM);
        $layoutMD = \array_map('\intval', $layoutMD);
        $layoutLG = \array_map('\intval', $layoutLG);

        $colLayouts = \array_fill(0, $colCount, '');

        foreach ($colLayouts as $i => &$colLayout) {
            $sumXS = 0;
            $sumSM = 0;
            $sumMD = 0;
            $sumLG = 0;
            for ($x = 0; $x <= $i; ++$x) {
                $sumXS = !empty($layoutXS[$x]) ? ($sumXS + $layoutXS[$x]) : $sumXS;
                $sumSM = !empty($layoutSM[$x]) ? ($sumSM + $layoutSM[$x]) : $sumSM;
                $sumMD = !empty($layoutMD[$x]) ? ($sumMD + $layoutMD[$x]) : $sumMD;
                $sumLG = !empty($layoutLG[$x]) ? ($sumLG + $layoutLG[$x]) : $sumLG;
            }

            $colLayout = [
                'xs'      => $layoutXS[$i] ?? '',
                'sm'      => $layoutSM[$i] ?? '',
                'md'      => $layoutMD[$i] ?? '',
                'lg'      => $layoutLG[$i] ?? '',
                'divider' => [
                    'xs' => $sumXS > 0 && $sumXS % 12 === 0,
                    'sm' => $sumSM > 0 && $sumSM % 12 === 0,
                    'md' => $sumMD > 0 && $sumMD % 12 === 0,
                    'lg' => $sumLG > 0 && $sumLG % 12 === 0,
                ],
            ];
        }

        return $colLayouts;
    }
}
