<?php

declare(strict_types=1);

namespace JTL\Media\Image;

use DirectoryIterator;
use FilesystemIterator;
use Generator;
use JTL\Media\Image;
use JTL\Media\MediaImageRequest;
use JTL\OPC\PortletInstance;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Class OPC
 * @package JTL\Media\Image
 */
class OPC extends AbstractImage
{
    public const TYPE = Image::TYPE_OPC;

    public const REGEX = '/^media\/image'
    . '\/(?P<type>opc)'
    . '\/(?P<size>xs|sm|md|lg|xl)'
    . '\/(?P<name>[' . self::REGEX_ALLOWED_CHARS . '\/]+)'
    . '(?:(?:~(?P<number>\d+))?)\.(?P<ext>jpg|jpeg|png|gif|webp|avif)$/';

    /**
     * @inheritdoc
     */
    public function getImageNames(MediaImageRequest $req): array
    {
        $name = $req->getName();
        $file = $name . '.' . $req->getExt();
        if (\file_exists(\PFAD_ROOT . \STORAGE_OPC . $file)) {
            $req->setSourcePath($file);
        } else {
            foreach (self::$imageExtensions as $extension) {
                $file = $name . '.' . $extension;
                if (\file_exists(\PFAD_ROOT . \STORAGE_OPC . $file)) {
                    $req->setSourcePath($file);
                    break;
                }
            }
        }

        return [$name];
    }

    /**
     * @inheritdoc
     */
    public static function getCustomName(mixed $mixed): string
    {
        /** @var PortletInstance $mixed */
        $pathInfo = \pathinfo($mixed->currentImagePath ?? '');

        return (!empty($pathInfo['dirname']) && $pathInfo['dirname'] !== '.'
                ? $pathInfo['dirname'] . '/'
                : '') . $pathInfo['filename'];
    }

    /**
     * @inheritdoc
     */
    public function getPathByID(int|string $id, ?int $number = null): ?string
    {
        return (string)$id;
    }

    /**
     * @inheritdoc
     */
    public static function getStoragePath(): string
    {
        return \STORAGE_OPC;
    }

    /**
     * @inheritdoc
     */
    public function getTotalImageCount(): int
    {
        $cnt = 0;
        foreach (new DirectoryIterator(\PFAD_ROOT . self::getStoragePath()) as $fileinfo) {
            if ($fileinfo->isDot() || !$fileinfo->isFile()) {
                continue;
            }
            ++$cnt;
        }

        return $cnt;
    }

    /**
     * @inheritdoc
     */
    public function getAllImages(?int $offset = null, ?int $limit = null): Generator
    {
        $base    = \PFAD_ROOT . self::getStoragePath();
        $rdi     = new RecursiveDirectoryIterator(
            $base,
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS
        );
        $index   = 0;
        $yielded = 0;
        /** @var SplFileInfo $fileinfo */
        foreach (new RecursiveIteratorIterator($rdi, RecursiveIteratorIterator::CHILD_FIRST) as $fileinfo) {
            if (!$fileinfo->isFile() || !\in_array($fileinfo->getExtension(), self::$imageExtensions, true)) {
                continue;
            }
            if (\str_contains($fileinfo->getPath(), '.tmb')) {
                continue;
            }
            if ($offset !== null && $offset > $index++) {
                continue;
            }
            ++$yielded;
            if ($limit !== null && $yielded > $limit) {
                return;
            }
            $ext  = '.' . $fileinfo->getExtension();
            $path = \str_replace($base, '', $fileinfo->getPathname());
            $name = \str_replace($ext, '', $path);
            yield MediaImageRequest::create([
                'id'         => 0,
                'type'       => self::TYPE,
                'name'       => $name,
                'number'     => 1,
                'path'       => $path,
                'sourcePath' => $path,
                'ext'        => static::getFileExtension($path)
            ]);
        }
    }
}
