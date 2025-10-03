<?php

declare(strict_types=1);

namespace JTL\dbeS\Push;

use InvalidArgumentException;
use JTL\Helpers\Request;
use JTL\Media\Image;
use JTL\Media\Media;
use JTL\Media\MediaImageRequest;
use JTL\Shop;

/**
 * Class ImageAPI
 * @package JTL\dbeS\Push
 */
final class ImageAPI extends AbstractPush
{
    private string $imageType;

    private int $imageID = 0;

    /**
     * @inheritdoc
     */
    public function getData(): void
    {
        try {
            $this->getImageType();
        } catch (InvalidArgumentException) {
            return;
        }
        $class    = Media::getClass($this->imageType);
        $instance = new $class($this->db);
        $imageNo  = Request::getInt('n', 1);
        $path     = $instance->getPathByID($this->imageID, $imageNo);
        if ($path === null) {
            return;
        }
        $req   = MediaImageRequest::create([
            'type'       => $this->imageType,
            'id'         => $this->imageID,
            'size'       => $this->getSizeByID(Request::verifyGPCDataInt('s')),
            'number'     => $imageNo,
            'ext'        => \pathinfo($path, \PATHINFO_EXTENSION),
            'sourcePath' => $path
        ]);
        $names = $instance->getImageNames($req);
        if (\count($names) === 0) {
            return;
        }
        $req->setName($names[0]);
        $thumb = $req->getThumb();
        if (!\file_exists(\PFAD_ROOT . $thumb)) {
            $instance->cacheImage($req);
        }
        if (Request::verifyGPCDataInt('url') === 1) {
            echo Shop::getURL() . '/' . $thumb;
        } else {
            $this->displayImage(\PFAD_ROOT . $thumb);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function getImageType(): void
    {
        if (($id = Request::verifyGPCDataInt(\QUERY_PARAM_PRODUCT)) > 0) {
            $this->imageType = Image::TYPE_PRODUCT;
            $this->imageID   = $id;
        } elseif (($id = Request::verifyGPCDataInt(\QUERY_PARAM_CATEGORY)) > 0) {
            $this->imageType = Image::TYPE_CATEGORY;
            $this->imageID   = $id;
        } elseif (($id = Request::verifyGPCDataInt(\QUERY_PARAM_MANUFACTURER)) > 0) {
            $this->imageType = Image::TYPE_MANUFACTURER;
            $this->imageID   = $id;
        } elseif (($id = Request::verifyGPCDataInt(\QUERY_PARAM_NEWS_ITEM)) > 0) {
            $this->imageType = Image::TYPE_NEWS;
            $this->imageID   = $id;
        } elseif (($id = Request::verifyGPCDataInt(\QUERY_PARAM_NEWS_CATEGORY)) > 0) {
            $this->imageType = Image::TYPE_NEWSCATEGORY;
            $this->imageID   = $id;
        } elseif (($id = Request::verifyGPCDataInt(\QUERY_PARAM_CHARACTERISTIC_VALUE)) > 0) {
            $this->imageType = Image::TYPE_CHARACTERISTIC_VALUE;
            $this->imageID   = $id;
        } elseif (($id = Request::verifyGPCDataInt('c')) > 0) {
            $this->imageType = Image::TYPE_CHARACTERISTIC;
            $this->imageID   = $id;
        } else {
            throw new InvalidArgumentException('Invalid image type');
        }
    }

    private function displayImage(string $imagePath): void
    {
        if (($mime = $this->getMimeType($imagePath)) !== null) {
            \header('Content-type: ' . $mime);
            \readfile($imagePath);
        }
    }

    private function getMimeType(string $imagePath): ?string
    {
        return \file_exists($imagePath)
            ? \getimagesize($imagePath)['mime'] ?? null
            : null;
    }

    /**
     * @return Image::SIZE_*
     */
    private function getSizeByID(int $size): string
    {
        return match ($size) {
            1       => Image::SIZE_LG,
            2       => Image::SIZE_MD,
            3       => Image::SIZE_SM,
            4       => Image::SIZE_XS,
            default => Image::SIZE_XL,
        };
    }
}
