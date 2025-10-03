<?php

declare(strict_types=1);

namespace JTL\OPC\Portlets\Video;

use JTL\OPC\InputType;
use JTL\OPC\Portlet;
use JTL\OPC\PortletInstance;
use JTL\Shop;

/**
 * Class Video
 * @package JTL\OPC\Portlets
 */
class Video extends Portlet
{
    public function initInstance(PortletInstance $instance): void
    {
        if ($instance->getProperty('video-vendor') === 'youtube') {
            /** @var string $id */
            $id              = $instance->getProperty('video-yt-id');
            $instance->video = new \JTL\Media\Video('https://www.youtube.com/?v=' . $id);
            /** @var int|string $start */
            $start = $instance->getProperty('video-yt-start');
            /** @var int|string $end */
            $end = $instance->getProperty('video-yt-end');
            /** @var string $playlist */
            $playlist = $instance->getProperty('video-yt-playlist');
            /** @var string $rel */
            $rel = $instance->getProperty('video-yt-rel');
            if ((int)$start > 0) {
                $instance->video->setStartSec((int)$start);
            }
            if ((int)$end > 0) {
                $instance->video->setEndSec((int)$end);
            }
            if (!empty($playlist)) {
                $instance->video->setExtraGetArg('playlist', $playlist);
            }
            $instance->video->setShowCaptions((bool)$instance->getProperty('video-yt-captions'));
            $instance->video->setRelated($rel === '1');
            $instance->video->setExtraGetArg('color', $instance->getProperty('video-yt-color'));
            $instance->video->setExtraGetArg('controls', $instance->getProperty('video-yt-controls'));
        } elseif ($instance->getProperty('video-vendor') === 'vimeo') {
            /** @var string $id */
            $id = $instance->getProperty('video-vim-id');
            /** @var int|numeric-string $loop */
            $loop            = $instance->getProperty('video-vim-loop');
            $instance->video = new \JTL\Media\Video('https://vimeo.com/' . $id);
            $instance->video->setLoop((bool)$loop === true);
            $instance->video->setShowCaptions((bool)$instance->getProperty('video-vim-captions'));
            $instance->video->setShowTranscriptBtn((bool)$instance->getProperty('video-vim-transcript'));
            $instance->video->setExtraGetArg(
                'color',
                $this->convertRGBtoHex(
                    $instance->getProperty('video-vim-color')
                )
            );
            $instance->video->setExtraGetArg(
                'byline',
                $instance->getProperty('video-vim-byline')
            );
            $instance->video->setExtraGetArg(
                'title',
                $instance->getProperty('video-vim-title')
            );
            $instance->video->setExtraGetArg(
                'portrait',
                $instance->getProperty('video-vim-img')
            );
        } else {
            $instance->video = new \JTL\Media\Video($instance->getProperty('video-local-url'));
            $instance->video->setLoop((bool)$instance->getProperty('video-local-loop'));
            $instance->video->setAutoplay((bool)$instance->getProperty('video-local-autoplay'));
            $instance->video->setMute((bool)$instance->getProperty('video-local-mute'));
            $instance->video->setShowControls((bool)$instance->getProperty('video-local-controls'));
            $instance->video->setCaptions($instance->getProperty('video-local-captions'));
            $instance->video->setTranscript($instance->getProperty('video-local-transcript'));
            $instance->video->setTranscriptInPopup((bool)$instance->getProperty('video-local-transcript-popup'));
        }
        /** @var int|string $width */
        $width = $instance->getProperty('video-width');
        /** @var int|string $heigth */
        $heigth = $instance->getProperty('video-height');
        $instance->video->setWidth((int)$width);
        $instance->video->setHeight((int)$heigth);
    }

    public function getPreviewImageUrl(PortletInstance $instance): ?string
    {
        return $instance->video->getPreviewImageUrl();
    }

    public function getPreviewOverlayUrl(): string
    {
        return Shop::getURL() . '/' . \PFAD_INCLUDES . 'src/OPC/Portlets/Video/preview.svg';
    }

    public function getButtonHtml(): string
    {
        return $this->getFontAwesomeButtonHtml('fas fa-film');
    }

    /**
     * @inheritdoc
     */
    public function getPropertyDesc(): array
    {
        return [
            // general
            'video-title'      => [
                'label' => \__('title'),
                'width' => 100,
            ],
            'video-width'      => [
                'type'    => InputType::NUMBER,
                'label'   => \__('widthPx'),
                'default' => 600,
                'width'   => 33,
            ],
            'video-height'     => [
                'type'    => InputType::NUMBER,
                'label'   => \__('heightPx'),
                'default' => 338,
                'width'   => 33,
            ],
            'video-responsive' => [
                'type'    => InputType::RADIO,
                'label'   => \__('embedResponsive'),
                'default' => true,
                'options' => [
                    true  => \__('yes'),
                    false => \__('no'),
                ],
                'width'   => 33,
            ],
            'video-vendor'     => [
                'label'       => \__('source'),
                'type'        => InputType::SELECT,
                'default'     => 'youtube',
                'options'     => [
                    'youtube' => 'YouTube',
                    'vimeo'   => 'Vimeo',
                    'local'   => \__('localVideo'),
                ],
                'childrenFor' => [
                    'youtube' => [
                        'video-yt-hint'     => [
                            'label' => \__('note'),
                            'type'  => InputType::HINT,
                            'class' => 'danger',
                            'text'  => \__('youtubeNote'),
                        ],
                        'video-yt-id'       => [
                            'label'   => \__('videoID'),
                            'default' => 'xITQHgJ3RRo',
                            'desc'    => \__('videoIDHelpYoutube'),
                        ],
                        'video-yt-start'    => [
                            'label' => \__('startSec'),
                            'type'  => InputType::NUMBER,
                            'width' => 50,
                        ],
                        'video-yt-end'      => [
                            'label' => \__('endSec'),
                            'type'  => InputType::NUMBER,
                            'width' => 50,
                        ],
                        'video-yt-controls' => [
                            'label'   => \__('showControls'),
                            'type'    => InputType::RADIO,
                            'inline'  => true,
                            'options' => [
                                '1' => \__('yes'),
                                '0' => \__('no'),
                            ],
                            'default' => '1',
                            'width'   => 50,
                        ],
                        'video-yt-captions' => [
                            'label'   => \__('showCaptions'),
                            'type'    => InputType::RADIO,
                            'inline'  => true,
                            'options' => [
                                '1' => \__('yes'),
                                '0' => \__('no'),
                            ],
                            'default' => '1',
                            'width'   => 50,
                            'desc'    => \__('showCaptionsYtDesc'),
                        ],
                        'video-yt-rel'      => [
                            'label'   => \__('showSimilarVideos'),
                            'type'    => InputType::RADIO,
                            'inline'  => true,
                            'options' => [
                                '1' => \__('yes'),
                                '0' => \__('no'),
                            ],
                            'default' => '0',
                            'width'   => 50,
                        ],
                        'video-yt-color'    => [
                            'label'        => \__('colorYtDesc'),
                            'type'         => InputType::RADIO,
                            'inline'       => true,
                            'options'      => [
                                'white' => \__('white'),
                                'red'   => \__('red'),
                            ],
                            'default'      => 'white',
                            'width'        => 50,
                            'color-format' => '#',
                        ],
                        'video-yt-playlist' => [
                            'label' => \__('playlist'),
                            'desc'  => \__('playlistHelp'),
                        ],
                    ],
                    'vimeo'   => [
                        'video-vim-id'         => [
                            'label'    => \__('videoID'),
                            'default'  => '141374353',
                            'nonempty' => true,
                            'desc'     => \__('videoIDHelpVimeo'),
                        ],
                        'video-vim-loop'       => [
                            'label'   => \__('repeatVideo'),
                            'type'    => InputType::RADIO,
                            'inline'  => true,
                            'options' => [
                                '1' => \__('yes'),
                                '0' => \__('no'),
                            ],
                            'default' => '0',
                            'width'   => 50,
                        ],
                        'video-vim-img'        => [
                            'label'   => \__('showImage'),
                            'type'    => InputType::RADIO,
                            'inline'  => true,
                            'options' => [
                                '1' => \__('yes'),
                                '0' => \__('no'),
                            ],
                            'default' => '0',
                            'width'   => 50,
                            'desc'    => \__('showImageDesc'),
                        ],
                        'video-vim-title'      => [
                            'label'   => \__('showTitle'),
                            'type'    => InputType::RADIO,
                            'inline'  => true,
                            'options' => [
                                '1' => \__('yes'),
                                '0' => \__('no'),
                            ],
                            'default' => '1',
                            'width'   => 50,
                            'desc'    => \__('showTitleDesc'),
                        ],
                        'video-vim-byline'     => [
                            'label'   => \__('showAuthorInformation'),
                            'type'    => InputType::RADIO,
                            'inline'  => true,
                            'options' => [
                                '1' => \__('yes'),
                                '0' => \__('no'),
                            ],
                            'default' => '0',
                            'width'   => 50,
                            'desc'    => \__('showAuthorInformationDesc'),
                        ],
                        'video-vim-captions'   => [
                            'label'   => \__('showCaptions'),
                            'type'    => InputType::RADIO,
                            'inline'  => true,
                            'options' => [
                                '1' => \__('yes'),
                                '0' => \__('no'),
                            ],
                            'default' => '1',
                            'width'   => 50,
                        ],
                        'video-vim-transcript' => [
                            'label'   => \__('showTranscriptControls'),
                            'type'    => InputType::RADIO,
                            'inline'  => true,
                            'options' => [
                                '1' => \__('yes'),
                                '0' => \__('no'),
                            ],
                            'default' => '1',
                            'width'   => 50,
                            'desc'    => \__('showTranscriptControlsDesc'),
                        ],
                        'video-vim-color'      => [
                            'label'   => \__('colorYtDesc'),
                            'type'    => InputType::COLOR,
                            'default' => '#ffffff',
                            'width'   => 50,
                        ],
                    ],
                    'local'   => [
                        'video-local-url'              => [
                            'label' => \__('videoURL'),
                            'type'  => InputType::VIDEO,
                            'width' => 50,
                        ],
                        'video-local-loop'             => [
                            'label' => \__('repeatVideo'),
                            'type'  => InputType::CHECKBOX,
                            'width' => 25,
                        ],
                        'video-local-captions'         => [
                            'label'    => \__('videoCaptions'),
                            'type'     => InputType::FILE,
                            'filetype' => 'descriptive',
                            'width'    => 25,
                            'desc'     => \__('videoCaptionsDesc'),
                        ],
                        'video-local-autoplay'         => [
                            'label' => \__('autoplayVideo'),
                            'type'  => InputType::CHECKBOX,
                            'width' => 33,
                            'desc'  => \__('videoAutoplayDesc'),
                        ],
                        'video-local-mute'             => [
                            'label' => \__('muteVideo'),
                            'type'  => InputType::CHECKBOX,
                            'width' => 33,
                        ],
                        'video-local-controls'         => [
                            'label'   => \__('showControls'),
                            'type'    => InputType::CHECKBOX,
                            'width'   => 33,
                            'default' => '1',
                        ],
                        'video-local-transcript'       => [
                            'label'    => \__('videoTranscript'),
                            'type'     => InputType::RICHTEXT,
                            'filetype' => 'descriptive',
                            'width'    => 100,
                        ],
                        'video-local-transcript-popup' => [
                            'label'   => \__('videoTranscriptAsPopUp'),
                            'type'    => InputType::CHECKBOX,
                            'width'   => 100,
                            'default' => '0',
                        ],
                    ]
                ]
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

    private function convertRGBtoHex(mixed $rgbColor): string
    {
        if (\is_string($rgbColor) === false) {
            return '';
        }
        \preg_match('/(\d+), ?(\d+), ?(\d+)/', $rgbColor, $matches);
        $red   = $matches[1] ?? '';
        $green = $matches[2] ?? '';
        $blue  = $matches[3] ?? '';
        if (
            $red === ''
            || $green === ''
            || $blue === ''
        ) {
            return '';
        }

        return \sprintf('%02x%02x%02x', $red, $green, $blue);
    }
}
