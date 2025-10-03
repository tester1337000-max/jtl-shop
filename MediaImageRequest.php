<?php

declare(strict_types=1);

namespace JTL\Media;

use JTL\Shop;

/**
 * Class MediaImageRequest
 * @package JTL\Media
 */
class MediaImageRequest
{
    /**
     * @phpstan-var Image::TYPE_*
     */
    public string $type = Image::TYPE_PRODUCT;

    public ?int $id = null;

    public ?string $name = null;

    public ?string $size = null;

    public int $number = 1;

    public int $ratio = 1;

    public ?string $path = null;

    public ?string $ext = null;

    public ?string $sourcePath = null;

    /**
     * @var array<string, string>
     */
    protected static array $cache = [];

    public static function create(mixed $mixed): MediaImageRequest
    {
        $new = new self();

        return $new->copy($mixed, $new);
    }

    public function copy(mixed &$mixed, MediaImageRequest $new): MediaImageRequest
    {
        $mixed = (object)$mixed;
        foreach ($mixed as $property => &$value) {
            $new->$property = &$value;
            unset($mixed->$property);
        }
        unset($value);
        if (empty($new->number)) {
            $new->number = 1;
        }
        $mixed = null;

        return $new;
    }

    public function getID(): int
    {
        return (int)($this->id ?? '0');
    }

    public function setID(int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        if (empty($this->name)) {
            $this->name = 'image';
        }

        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return Image::TYPE_*
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @phpstan-param Image::TYPE_* $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getSize(): MediaImageSize
    {
        return new MediaImageSize($this->size, $this->type);
    }

    public function getSizeType(): ?string
    {
        return $this->size;
    }

    public function setSizeType(string $size): void
    {
        $this->size = $size;
    }

    public function getNumber(): int
    {
        return \max($this->number, 1);
    }

    public function setNumber(int $number): void
    {
        $this->number = $number;
    }

    public function getRatio(): int
    {
        return \max($this->ratio, 1);
    }

    public function setRatio(int $ratio): void
    {
        $this->ratio = $ratio;
    }

    public function getPath(): ?string
    {
        if ($this->path === null) {
            $this->path = $this->getPathByID();
        }

        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getSourcePath(): ?string
    {
        if ($this->sourcePath === null) {
            $this->sourcePath = $this->getPathByID();
        }

        return $this->sourcePath;
    }

    public function setSourcePath(string $sourcePath): void
    {
        $this->sourcePath = $sourcePath;
    }

    public function getExt(): string
    {
        if (empty($this->ext) && $this->getSourcePath() !== null) {
            $this->ext = \pathinfo($this->getSourcePath(), \PATHINFO_EXTENSION);
        }

        return $this->ext ?? '';
    }

    public function setExt(string $ext): void
    {
        $this->ext = $ext;
    }

    public function getRaw(bool $absolute = true): ?string
    {
        $path = $this->getSourcePath();
        $path = empty($path) ? null : \sprintf('%s%s', $this->getRealStoragePath(), $path);

        return $path !== null && $absolute === true
            ? \PFAD_ROOT . $path
            : $path;
    }

    public function getThumb(MediaImageSize|string|null $size = null, bool $absolute = false): string
    {
        $size     = $size ?? $this->getSize()->getSize();
        $number   = $this->getNumber() > 1
            ? '~' . $this->getNumber()
            : '';
        $settings = Image::getSettings();
        $ext      = $this->ext ?: $settings['format'];
        $id       = $this->getID();
        switch ($ext) {
            case 'auto_webp':
                $ext = 'webp';
                break;
            case 'auto_avif':
                $ext = 'avif';
                break;
            case 'auto':
                $ext = 'jpg';
                break;
            default:
        }


        if ($id > 0) {
            $thumb = \sprintf(
                '%s/%d/%s/%s%s.%s',
                self::getCachePath($this->getType()),
                $id,
                $size,
                $this->getName(),
                $number,
                $ext
            );
        } else {
            $thumb = \sprintf(
                '%s/%s/%s%s.%s',
                self::getCachePath($this->getType()),
                $size,
                $this->getName(),
                $number,
                $ext
            );
        }

        if ($ext === 'svg') {
            return $absolute === true
                ? \PFAD_ROOT . $this->getRealStoragePath() . $this->path
                : $this->getRealStoragePath() . $this->path;
        }

        return $absolute === true
            ? \PFAD_ROOT . $thumb
            : $thumb;
    }

    public function getThumbUrl(MediaImageSize|string|null $size = null): string
    {
        return Shop::getImageBaseURL() . $this->getThumb($size);
    }

    public function getPathByID(): ?string
    {
        if (($path = $this->cachedPath()) !== null) {
            return $path;
        }
        $class = Media::getClass($this->getType());
        /** @var IMedia $instance */
        $instance = new $class(Shop::Container()->getDB());
        $path     = $instance->getPathByID($this->getID(), $this->getNumber());
        $this->cachedPath($path);

        return $path;
    }

    protected function cachedPath(?string $path = null): ?string
    {
        $hash = \sprintf('%s-%s-%s', $this->getID(), $this->getNumber(), $this->getType());
        if ($path === null) {
            return static::$cache[$hash] ?? null;
        }
        static::$cache[$hash] = $path;

        return $path;
    }

    public function getRealStoragePath(): string
    {
        $instance = Media::getClass($this->getType());

        return $instance::getStoragePath();
    }

    public static function getStoragePath(): string
    {
        return \PFAD_MEDIA_IMAGE_STORAGE;
    }

    public static function getCachePath(string $type): string
    {
        return \PFAD_MEDIA_IMAGE . $type;
    }
}
