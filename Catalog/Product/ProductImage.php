<?php

declare(strict_types=1);

namespace JTL\Catalog\Product;

use Exception;
use JTL\Media\Image;
use JTL\Media\Image\Product;
use stdClass;

/**
 * @phpstan-type ImgObj object{src: string, size: object{width: int, height: int}&stdClass,
 *     type: int, alt: string}&stdClass
 */
class ProductImage
{
    public string $cPfadMini;
    public string $cPfadKlein;
    public string $cPfadNormal;
    public string $cPfadGross;

    public string $cURLMini;
    public string $cURLKlein;
    public string $cURLNormal;
    public string $cURLGross;

    public string $cAltAttribut;

    public int $nNr;

    /**
     * @var object{
     * 'xs': ImgObj|null,
     * 'sm': ImgObj|null,
     * 'md': ImgObj|null,
     * 'lg': ImgObj|null}&stdClass
     */
    public stdClass $imageSizes;

    public string $galleryJSON;

    public function __construct(private readonly string $baseURL)
    {
    }

    /**
     * @param bool $json
     * @return ($json is true ? string|false : stdClass)
     */
    public function prepareImageDetails(bool $json = false): false|string|stdClass
    {
        $this->imageSizes = (object)[
            Image::SIZE_XS => $this->getProductImageSize(Image::SIZE_XS),
            Image::SIZE_SM => $this->getProductImageSize(Image::SIZE_SM),
            Image::SIZE_MD => $this->getProductImageSize(Image::SIZE_MD),
            Image::SIZE_LG => $this->getProductImageSize(Image::SIZE_LG),
        ];

        $this->galleryJSON = \json_encode($this->imageSizes, \JSON_THROW_ON_ERROR | \JSON_FORCE_OBJECT);

        return $json === true
            ? \json_encode($this->imageSizes, \JSON_THROW_ON_ERROR | \JSON_FORCE_OBJECT)
            : $this->imageSizes;
    }

    /**
     * @param 'xs'|'sm'|'md'|'lg' $size
     * @return stdClass|null
     * @phpstan-return ImgObj|null
     */
    private function getProductImageSize(string $size): ?stdClass
    {
        $imagePath = match ($size) {
            Image::SIZE_XS => $this->cPfadMini,
            Image::SIZE_SM => $this->cPfadKlein,
            Image::SIZE_MD => $this->cPfadNormal,
            default        => $this->cPfadGross,
        };

        if (\file_exists(\PFAD_ROOT . $imagePath)) {
            [$width, $height, $type] = \getimagesize(\PFAD_ROOT . $imagePath) ?: [0, 0, \IMAGETYPE_UNKNOWN];
        } else {
            try {
                [$width, $height, $type] = $this->getFromImagePath($imagePath);
            } catch (Exception) {
                return null;
            }
        }

        return (object)[
            'src'  => $this->baseURL . $imagePath,
            'size' => (object)[
                'width'  => $width,
                'height' => $height
            ],
            'type' => $type,
            'alt'  => \str_replace('"', '', $this->cAltAttribut)
        ];
    }

    /**
     * @return array{int, int, int}
     * @throws Exception
     */
    private function getFromImagePath(string $imagePath): array
    {
        $req      = Product::toRequest($imagePath);
        $settings = Image::getSettings();
        $sizeType = $req->getSizeType();
        $width    = 0;
        $height   = 0;
        $type     = \IMAGETYPE_UNKNOWN;
        if ($sizeType === null || !isset($settings['size'][$sizeType])) {
            throw new Exception('Invalid image size');
        }
        $size = $settings['size'][$sizeType];
        if ($settings['container'] === true) {
            $width  = $size['width'];
            $height = $size['height'];
            $type   = $settings['format'] === 'png' ? \IMAGETYPE_PNG : \IMAGETYPE_JPEG;
        } elseif (($raw = $req->getRaw()) !== null) {
            [$width, $height, $type] = $this->getFromRaw($raw, $size['width'], $size['height']);
        }

        return [$width, $height, $type];
    }

    /**
     * @return array{int, int, int}
     */
    private function getFromRaw(string $raw, int $confWidth, int $confHeight): array
    {
        [$oldWidth, $oldHeight, $type] = \getimagesize($raw) ?: [0, 0, \IMAGETYPE_UNKNOWN];

        if ($oldWidth > 0 && $oldHeight > 0) {
            $scale  = \min($confWidth / $oldWidth, $confHeight / $oldHeight);
            $width  = (int)\ceil($scale * $oldWidth);
            $height = (int)\ceil($scale * $oldHeight);
        } else {
            $width  = $confWidth;
            $height = $confHeight;
        }

        return [$width, $height, $type];
    }
}
