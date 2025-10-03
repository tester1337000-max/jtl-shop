<?php

declare(strict_types=1);

namespace JTL\OPC;

/**
 * Trait PortletAnimations
 * @package JTL\OPC
 */
trait PortletAnimations
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function getAnimationsPropertyDesc(): array
    {
        return [
            'animation-style' => [
                'label'   => \__('Animation style'),
                'type'    => InputType::SELECT,
                'default' => '',
                'width'   => 34,
                'options' => [
                    ''           => 'none',
                    'attention'  => [
                        'label'   => 'Attention Seekers',
                        'options' => [
                            'bounce'     => 'bounce',
                            'flash'      => 'flash',
                            'pulse'      => 'pulse',
                            'rubberBand' => 'rubberBand',
                            'shake'      => 'shake',
                            'swing'      => 'swing',
                            'tada'       => 'tada',
                            'wobble'     => 'wobble',
                            'jello'      => 'jello',
                        ],
                    ],
                    'bouncing'   => [
                        'label'   => 'Bouncing Entrances',
                        'options' => [
                            'bounceIn'      => 'bounceIn',
                            'bounceInDown'  => 'bounceInDown',
                            'bounceInLeft'  => 'bounceInLeft',
                            'bounceInRight' => 'bounceInRight',
                            'bounceInUp'    => 'bounceInUp',
                        ],
                    ],
                    'fading'     => [
                        'label'   => 'Fading Entrances',
                        'options' => [
                            'fadeIn'        => 'fadeIn',
                            'fadeInDown'    => 'fadeInDown',
                            'fadeInDownBig' => 'fadeInDownBig',
                            'fadeInLeft'    => 'fadeInLeft',
                            'fadeInLeftBig' => 'fadeInLeftBig',
                        ],
                    ],
                    'flippers'   => [
                        'label'   => 'Flippers',
                        'options' => [
                            'flip'    => 'flip',
                            'flipInX' => 'flipInX',
                            'flipInY' => 'flipInY',
                        ],
                    ],
                    'lightspeed' => [
                        'label'   => 'lightspeed',
                        'options' => [
                            'lightSpeedIn' => 'lightSpeedIn',
                        ],
                    ],
                    'rotating'   => [
                        'label'   => 'Rotating Entrances',
                        'options' => [
                            'rotateIn'          => 'rotateIn',
                            'rotateInDownLeft'  => 'rotateInDownLeft',
                            'rotateInDownRight' => 'rotateInDownRight',
                            'rotateInUpLeft'    => 'rotateInUpLeft',
                            'rotateInUpRight'   => 'rotateInUpRight',
                        ],
                    ],
                    'sliding'    => [
                        'label'   => 'Sliding Entrances',
                        'options' => [
                            'slideInUp'    => 'slideInUp',
                            'slideInDown'  => 'slideInDown',
                            'slideInLeft'  => 'slideInLeft',
                            'slideInRight' => 'slideInRight',
                        ],
                    ],
                    'zoom'       => [
                        'label'   => 'Zoom Entrances',
                        'options' => [
                            'zoomIn'      => 'zoomIn',
                            'zoomInDown'  => 'zoomInDown',
                            'zoomInLeft'  => 'zoomInLeft',
                            'zoomInRight' => 'zoomInRight',
                            'zoomInUp'    => 'zoomInUp',
                        ],
                    ],
                    'specials'   => [
                        'label'   => 'Specials',
                        'options' => [
                            'hinge'  => 'hinge',
                            'rollIn' => 'rollIn',
                        ],
                    ],
                ],
            ],
            'wow-duration'    => [
                'label'       => \__('Duration'),
                'help'        => \__('Change the animation duration (e.g. 2s)'),
                'placeholder' => '1s',
                'width'       => 34,
            ],
            'wow-delay'       => [
                'label' => \__('Delay'),
                'help'  => \__('Delay before the animation starts'),
                'width' => 34,
            ],
            'wow-offset'      => [
                'label'       => \__('Offset (px)'),
                'type'        => InputType::NUMBER,
                'placeholder' => 200,
                'help'        => \__('Distance to start the animation (related to the browser bottom)'),
                'width'       => 50,
            ],
            'wow-iteration'   => [
                'label' => \__('Iteration'),
                'type'  => InputType::NUMBER,
                'help'  => \__('Number of times the animation is repeated'),
                'width' => 50,
            ],
        ];
    }
}
