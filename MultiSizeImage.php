<?php

declare(strict_types=1);

namespace JTL\Media;

use Exception;
use JTL\Shop;

/**
 * Trait MultiSizeImageable
 * @package JTL\Media
 */
trait MultiSizeImage
{
    protected string $imageType;

    /**
     * @var array<int, array<string, string>>
     */
    protected array $images = [];

    /**
     * @var array<int, array<string, array{width: int, height: int}>>
     */
    protected array $imageDimensions = [];

    protected string|int|null $iid = null;

    public ?string $currentImagePath = null;

    /**
     * @param int|string $id
     */
    public function setID($id): void
    {
        $this->iid = $id;
    }

    /**
     * @return int|string|null
     */
    public function getID(): int|string|null
    {
        return $this->iid;
    }

    public function getImageType(): string
    {
        return $this->imageType;
    }

    public function setImageType(string $type): void
    {
        $this->imageType = $type;
    }

    public function getImage(string $size = Image::SIZE_MD, int $number = 1): ?string
    {
        return $this->images[$number][$size] ?? null;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function getImages(): array
    {
        return $this->images;
    }

    /**
     * @param array<int, array<string, string>> $images
     */
    public function setImages(array $images): void
    {
        $this->images = $images;
    }

    public function generateImagePath(string $size, int $number = 1, ?string $source = null): string
    {
        $class = Media::getClass($this->getImageType());
        if ($source === null) {
            /** @var IMedia $instance */
            $instance = new $class();
            $source   = $instance->getPathByID($this->getID(), $number);
            if (empty($source)) {
                $source = null;
            }
        }
        $this->currentImagePath = $source;

        return $class::getThumb($this->getImageType(), $this->getID(), $this, $size, $number, $source);
    }

    public function generateImage(string $size, int $number = 1, ?string $source = null): string
    {
        $class = Media::getClass($this->getImageType());
        if ($source === null) {
            /** @var IMedia $instance */
            $instance = new $class();
            $source   = $instance->getPathByID($this->getID(), $number);
        }
        $this->currentImagePath = $source;
        $req                    = $class::getRequest(
            $this->getImageType(),
            $this->getID(),
            $this,
            $size,
            $number,
            $source
        );
        try {
            Image::render($req);
        } catch (Exception $e) {
            Shop::Container()->getLogService()->warning('Could not generate image: ' . $e->getMessage());
        }

        return $class::getThumbByRequest($req);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function generateAllImageSizes(bool $full = true, int $number = 1, ?string $source = null): array
    {
        $prefix = $full ? Shop::getImageBaseURL() : '';
        foreach (Image::getAllSizes() as $i => $size) {
            $path = $this->generateImagePath($size, $number, $source);
            if ($i === 0 && $path === \BILD_KEIN_ARTIKELBILD_VORHANDEN) {
                // skip the expensive image path generation if the first size has no regular image anyways
                foreach (Image::getAllSizes() as $innerSize) {
                    $this->images[$number][$innerSize] = $prefix . $path;
                }
                break;
            }
            $this->images[$number][$size] = $prefix . $path;
        }

        return $this->images;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function generateAllImages(bool $full = true, int $number = 1, ?string $source = null): array
    {
        $prefix = $full ? Shop::getImageBaseURL() : '';
        foreach (Image::getAllSizes() as $size) {
            $this->images[$number][$size] = $prefix . $this->generateImage($size, $number, $source);
        }

        return $this->images;
    }

    /**
     * @return array<string, array{width: int, height: int}>
     */
    public function generateAllImageDimensions(int $number = 1, ?string $source = null): array
    {
        if (!empty($this->imageDimensions[$number])) {
            return $this->imageDimensions[$number];
        }

        $this->imageDimensions[$number] = [];
        $settings                       = Image::getSettings();

        foreach (Image::getAllSizes() as $size) {
            $path = $this->generateImagePath($size, $number, $source);
            if (!\file_exists(\PFAD_ROOT . $path)) {
                $this->generateImage($size, $number, $source);
            }
            if ($size !== Image::SIZE_XL && !\file_exists(\PFAD_ROOT . $path)) {
                $this->imageDimensions[$number][$size] = $settings[$this->getImageType()][$size];
            } else {
                [$width, $height] = \getimagesize(\PFAD_ROOT . $path) ?: [0, 0];
                if (empty($width) || empty($height)) {
                    // image might be orphaned/corrupted (SHOP-7372) => try regeneration
                    $this->generateImage($size, $number, $source);
                    [$width, $height] = \getimagesize(\PFAD_ROOT . $path) ?: [0, 0];
                    if (empty($width) || empty($height)) {
                        // fallback if we could not generate it
                        $width  = 0;
                        $height = 0;
                    }
                }
                $this->imageDimensions[$number][$size] = ['width' => $width, 'height' => $height];
            }
        }

        return $this->imageDimensions[$number];
    }

    /**
     * @param string&Image::SIZE_* $size
     * @param int                  $number
     * @return int
     */
    public function getImageWidth(string $size, int $number = 1): int
    {
        $this->generateAllImageDimensions($number);

        return $this->imageDimensions[$number][$size]['width'] ?? 0;
    }

    /**
     * @param string&Image::SIZE_* $size
     * @param int                  $number
     * @return int
     */
    public function getImageHeight(string $size, int $number = 1): int
    {
        $this->generateAllImageDimensions($number);

        return $this->imageDimensions[$number][$size]['height'] ?? 0;
    }

    /**
     * @return array<int, array<string, array{width: int, height: int}>>
     */
    public function getDimensions(): array
    {
        return $this->imageDimensions;
    }
}
