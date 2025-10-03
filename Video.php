<?php

declare(strict_types=1);

namespace JTL\Media;

use JTL\Language\LanguageHelper;
use JTL\Shop;

/**
 * Class Video
 * @package JTL\Media
 */
class Video
{
    /**
     * Video types
     */
    public const TYPE_INVALID = -1;
    public const TYPE_FILE    = 0;
    public const TYPE_YOUTUBE = 1;
    public const TYPE_VIMEO   = 2;

    protected int $type = self::TYPE_INVALID;

    protected string $url = '';

    protected string $id = '';

    protected bool $loop = false;

    protected bool $showCaptions = true;

    protected bool $showTranscriptBtn = true;

    protected bool $autoplay = false;

    protected bool $mute = false;

    protected bool $showControls = true;

    protected string $captions = '';

    protected string $transcript = '';

    protected bool $transcriptAsPopUp = false;

    protected ?int $width = null;

    protected ?int $height = null;

    protected ?int $startSec = null;

    protected ?int $endSec = null;

    protected bool $related = false;

    protected bool $allowFullscreen = true;

    protected string $fileFormat = '';

    /**
     * @var array<string, mixed>
     */
    protected array $extraGetArgs = [];

    public static function fromUrl(string $url): self
    {
        return new self($url);
    }

    public static function fromMediaFile(\stdClass $mediaFile): ?self
    {
        if ($mediaFile->nMedienTyp === 3) {
            $url   = Shop::getURL() . '/' . \PFAD_MEDIAFILES . $mediaFile->cPfad;
            $video = new self($url);
            $video->setFileFormat($mediaFile->videoType);
        } elseif (!empty($mediaFile->cURL)) {
            $url   = $mediaFile->cURL;
            $video = new self($url);

            if ($video->getType() === self::TYPE_FILE) {
                // not a real video file when nMedienTyp !== 3
                return null;
            }
        } else {
            return null;
        }

        if ($video->getType() === self::TYPE_INVALID) {
            return null;
        }

        foreach ($mediaFile->oMedienDateiAttribut_arr as $attrib) {
            if ($attrib->cName === 'related') {
                $video->setRelated($attrib->cWert === '1');
            } elseif ($attrib->cName === 'width' && \is_numeric($attrib->cWert)) {
                $video->setWidth((int)$attrib->cWert);
            } elseif ($attrib->cName === 'height' && \is_numeric($attrib->cWert)) {
                $video->setHeight((int)$attrib->cWert);
            } elseif ($attrib->cName === 'fullscreen' && ($attrib->cWert === '0' || $attrib->cWert === 'false')) {
                $video->setAllowFullscreen(false);
            }
        }

        return $video;
    }

    public function __construct(string $url)
    {
        if (\str_starts_with($url, Shop::getURL())) {
            $this->type = self::TYPE_FILE;
            $this->url  = $url;
        }
        $parsedUrl = \parse_url($url);
        if ($parsedUrl === false || empty($parsedUrl['host'])) {
            $this->type = self::TYPE_INVALID;

            return;
        }
        if (\str_contains($parsedUrl['host'], 'youtube')) {
            if (empty($parsedUrl['query'])) {
                $this->type = self::TYPE_INVALID;

                return;
            }
            \parse_str($parsedUrl['query'], $query);
            if (empty($query['v'])) {
                $this->type = self::TYPE_INVALID;

                return;
            }
            $this->type = self::TYPE_YOUTUBE;
            $this->id   = (string)$query['v'];
            $this->url  = 'https://www.youtube-nocookie.com/embed/' . $this->id;
        } elseif (\str_contains($parsedUrl['host'], 'youtu.be')) {
            if (empty($parsedUrl['path'])) {
                $this->type = self::TYPE_INVALID;

                return;
            }
            $this->type = self::TYPE_YOUTUBE;
            $this->id   = \trim($parsedUrl['path'], '/');
            $this->url  = 'https://www.youtube-nocookie.com/embed/' . $this->id;
        } elseif (\str_contains($parsedUrl['host'], 'vimeo.com')) {
            if (empty($parsedUrl['path'])) {
                $this->type = self::TYPE_INVALID;

                return;
            }
            $videoId = \trim($parsedUrl['path'], '/');
            if (\str_starts_with($videoId, 'video/')) {
                $videoId = \substr($videoId, 6);
            }
            $this->type = self::TYPE_VIMEO;
            $this->id   = $videoId;
            $this->url  = 'https://player.vimeo.com/video/' . $this->id;
        }
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getLocale(): string
    {
        $locale    = 'de';
        $langID    = Shop::getLanguageID();
        $languages = LanguageHelper::getAllLanguages();
        foreach ($languages as $language) {
            if ($language->getId() === $langID) {
                $locale = $language->getIso639();
            }
        }

        return $locale;
    }

    public function getLanguageName(): string
    {
        $result    = 'Deutsch';
        $langID    = Shop::getLanguageID();
        $languages = LanguageHelper::getAllLanguages();
        foreach ($languages as $language) {
            if ($language->getId() === $langID) {
                $result = $language->getLocalizedName();
            }
        }

        return $result;
    }

    public function getCaptionsURL(): string
    {
        if ($this->captions === '') {
            return '';
        }

        return \file_exists(\PFAD_ROOT . \PFAD_MEDIA_DESCRIPTIVE . $this->captions)
            ? Shop::getURL() . '/' . \PFAD_MEDIA_DESCRIPTIVE . $this->captions
            : '';
    }

    public function showTranscriptInPopup(): bool
    {
        return $this->transcriptAsPopUp;
    }

    public function setTranscriptInPopup(bool $transcriptAsPopUp): bool
    {
        return $this->transcriptAsPopUp = $transcriptAsPopUp;
    }

    public function getEmbedUrl(): string
    {
        if ($this->type === self::TYPE_YOUTUBE) {
            $embedUrl  = $this->url;
            $arguments = [];
            if ($this->loop) {
                $arguments['playlist'] = $this->id;
                $arguments['loop']     = 1;
            }
            if ($this->startSec) {
                $arguments['start'] = $this->startSec;
            }
            if ($this->endSec) {
                $arguments['end'] = $this->endSec;
            }
            if ($this->showCaptions) {
                $locale                      = $this->getLocale();
                $arguments['cc_load_policy'] = 1;
                $arguments['cc_lang_pref']   = $locale;
            }
            $arguments['rel']            = $this->related ? '1' : '0';
            $arguments['iv_load_policy'] = 3;
            $arguments                   = \array_merge($arguments, $this->extraGetArgs);
            if (!empty($arguments)) {
                $embedUrl .= '?' . \http_build_query($arguments);
            }

            return $embedUrl;
        }

        if ($this->type === self::TYPE_VIMEO) {
            $embedUrl  = $this->url;
            $arguments = [];
            if ($this->loop) {
                $arguments['loop'] = 1;
            }
            if ($this->showCaptions) {
                $arguments['texttrack'] = $this->getLocale();
            }
            $arguments['transcript'] = (int)$this->showTranscriptBtn;
            $arguments               = \array_merge($arguments, $this->extraGetArgs);
            if (!empty($arguments)) {
                $embedUrl .= '?' . \http_build_query($arguments);
            }

            return $embedUrl;
        }

        return $this->url;
    }

    public function isLoop(): bool
    {
        return $this->loop;
    }

    public function setLoop(bool $loop): self
    {
        $this->loop = $loop;

        return $this;
    }

    public function isShowControls(): bool
    {
        return $this->showControls;
    }

    public function setShowCaptions(bool $showCaptions): void
    {
        $this->showCaptions = $showCaptions;
    }

    public function showCaptions(): bool
    {
        return $this->showCaptions;
    }

    public function setShowTranscriptBtn(bool $showTranscriptBtn): void
    {
        $this->showTranscriptBtn = $showTranscriptBtn;
    }

    public function showTranscriptBtn(): bool
    {
        return $this->showTranscriptBtn;
    }

    public function isAutoplay(): bool
    {
        return $this->autoplay;
    }

    public function setAutoplay(bool $autoplay): void
    {
        $this->autoplay = $autoplay;
    }

    public function isMuted(): bool
    {
        return $this->mute;
    }

    public function setMute(bool $mute): void
    {
        $this->mute = $mute;
    }

    public function setShowControls(bool $showControls): void
    {
        $this->showControls = $showControls;
    }

    public function setCaptions(string $captionsVTT): void
    {
        $this->captions = $captionsVTT;
    }

    public function getCaptions(): string
    {
        return $this->captions;
    }

    public function setTranscript(string $transcript): void
    {
        $this->transcript = $transcript;
    }

    public function getTranscript(): string
    {
        return $this->transcript;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(int $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(int $height): self
    {
        $this->height = $height;

        return $this;
    }

    public function isRelated(): bool
    {
        return $this->related;
    }

    public function setRelated(bool $related): self
    {
        $this->related = $related;

        return $this;
    }

    public function isAllowFullscreen(): bool
    {
        return $this->allowFullscreen;
    }

    public function setAllowFullscreen(bool $allowFullscreen): self
    {
        $this->allowFullscreen = $allowFullscreen;

        return $this;
    }

    public function getFileFormat(): string
    {
        return $this->fileFormat;
    }

    public function setFileFormat(string $fileFormat): void
    {
        $this->fileFormat = $fileFormat;
    }

    public function getStartSec(): ?int
    {
        return $this->startSec;
    }

    public function setStartSec(?int $startSec): void
    {
        $this->startSec = $startSec;
    }

    public function getEndSec(): ?int
    {
        return $this->endSec;
    }

    public function setEndSec(?int $endSec): void
    {
        $this->endSec = $endSec;
    }

    public function setExtraGetArg(string $name, mixed $value): self
    {
        $this->extraGetArgs[$name] = $value;

        return $this;
    }

    protected function getYouTubePreviewImageUrl(): ?string
    {
        if (!empty($this->id)) {
            return 'https://img.youtube.com/vi/' . $this->id . '/mqdefault.jpg';
        }

        return null;
    }

    public function getPreviewImageUrl(): ?string
    {
        if ($this->type === self::TYPE_YOUTUBE) {
            $srcURL = 'https://img.youtube.com/vi/' . $this->id . '/mqdefault.jpg';
        } elseif ($this->type === self::TYPE_VIMEO) {
            try {
                /** @var array<\stdClass> $videoXML */
                $videoXML = \json_decode(
                    \file_get_contents('https://vimeo.com/api/v2/video/' . $this->id . '.json') ?: '',
                    false,
                    512,
                    \JSON_THROW_ON_ERROR
                );
                $srcURL   = $videoXML[0]->thumbnail_large ?? null;
            } catch (\JsonException) {
                $srcURL = null;
            }
        } else {
            return null;
        }

        $localPath = \PFAD_ROOT . \STORAGE_VIDEO_THUMBS . $this->id . '.jpg';
        $localUrl  = Shop::getURL() . '/' . \STORAGE_VIDEO_THUMBS . $this->id . '.jpg';

        if (!empty($srcURL) && !\is_file($localPath)) {
            if (!\is_writable(\PFAD_ROOT . \STORAGE_VIDEO_THUMBS)) {
                return null;
            }

            \file_put_contents($localPath, \file_get_contents($srcURL));
        }

        return $localUrl;
    }
}
