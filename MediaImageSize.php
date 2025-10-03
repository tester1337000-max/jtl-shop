<?php

declare(strict_types=1);

namespace JTL\Media;

/**
 * Class MediaImageSize
 * @package JTL\Media
 */
class MediaImageSize
{
    private ?int $width = null;

    private ?int $height = null;

    public function __construct(private readonly string $size, private readonly string $imageType = Image::TYPE_PRODUCT)
    {
    }

    public function getWidth(): int
    {
        if ($this->width === null) {
            $this->width = $this->getConfiguredSize('width');
        }

        return $this->width;
    }

    public function getHeight(): int
    {
        if ($this->height === null) {
            $this->height = $this->getConfiguredSize('height');
        }

        return $this->height;
    }

    public function getImageType(): string
    {
        return $this->imageType;
    }

    public function getSize(): string
    {
        return $this->size;
    }

    public function getConfiguredSize(string $dimension): int
    {
        $settings = Image::getSettings();

        return (int)($settings[$this->imageType][$this->size][$dimension] ?? -1);
    }

    public function __toString(): string
    {
        return \sprintf('%s', $this->getSize());
    }
}
