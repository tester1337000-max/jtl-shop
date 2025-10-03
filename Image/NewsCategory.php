<?php

declare(strict_types=1);

namespace JTL\Media\Image;

use FilesystemIterator;
use Generator;
use JTL\Media\Image;
use JTL\Media\MediaImageRequest;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use stdClass;

/**
 * Class NewsCategory
 * @package JTL\Media\Image
 */
class NewsCategory extends AbstractImage
{
    public const TYPE = Image::TYPE_NEWSCATEGORY;

    public const REGEX = '/^media\/image'
    . '\/(?P<type>newscategory)'
    . '\/(?P<id>\d+)'
    . '\/(?P<size>xs|sm|md|lg|xl)'
    . '\/(?P<name>[' . self::REGEX_ALLOWED_CHARS . ']+)'
    . '(?:(?:~(?P<number>\d+))?)\.(?P<ext>jpg|jpeg|png|gif|webp|avif)$/';

    /**
     * @inheritdoc
     */
    public static function getImageStmt(string $type, int $id): ?stdClass
    {
        return (object)[
            'stmt' => 'SELECT kNewsKategorie, 0 AS number  
                           FROM tnewskategorie 
                           WHERE kNewsKategorie = :cid',
            'bind' => ['cid' => $id]
        ];
    }

    /**
     * @inheritdoc
     */
    public function getImageNames(MediaImageRequest $req): array
    {
        return $this->db->getCollection(
            'SELECT a.kNewsKategorie, a.cPreviewImage AS path, t.name AS title
                FROM tnewskategorie AS a
                LEFT JOIN tnewskategoriesprache t
                    ON a.kNewsKategorie = t.kNewsKategorie
                WHERE a.kNewsKategorie = :nid',
            ['nid' => $req->getID()]
        )->each(static function (stdClass $item, int $key) use ($req): void {
            if ($key === 0 && !empty($item->path)) {
                $req->setSourcePath(\str_replace(\PFAD_NEWSKATEGORIEBILDER, '', $item->path));
            }
            $item->imageName = self::getCustomName($item);
        })->pluck('imageName')->toArray();
    }

    /**
     * @inheritdoc
     */
    public static function getCustomName(mixed $mixed): string
    {
        $result = \method_exists($mixed, 'getName') ? $mixed->getName() : $mixed->title;

        return empty($result) ? 'image' : Image::getCleanFilename($result);
    }

    /**
     * @inheritdoc
     */
    public function getPathByID(int|string $id, ?int $number = null): ?string
    {
        $path = $this->db->getSingleObject(
            'SELECT cPreviewImage AS path
                FROM tnewskategorie
                WHERE kNewsKategorie = :cid LIMIT 1',
            ['cid' => $id]
        )->path ?? null;

        return empty($path)
            ? null
            : \str_replace(\PFAD_NEWSKATEGORIEBILDER, '', $path);
    }

    /**
     * @inheritdoc
     */
    public static function getStoragePath(): string
    {
        return \PFAD_NEWSKATEGORIEBILDER;
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
            if ($fileinfo->isFile() && \in_array($fileinfo->getExtension(), self::$imageExtensions, true)) {
                if ($offset !== null && $offset > $index++) {
                    continue;
                }
                ++$yielded;
                if ($limit !== null && $yielded > $limit) {
                    return;
                }
                $path = \str_replace($base, '', $fileinfo->getPathname());
                yield MediaImageRequest::create([
                    'id'         => 1,
                    'type'       => self::TYPE,
                    'name'       => $fileinfo->getFilename(),
                    'number'     => 1,
                    'path'       => $path,
                    'sourcePath' => $path,
                    'ext'        => static::getFileExtension($path)
                ]);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function getTotalImageCount(): int
    {
        $rdi = new RecursiveDirectoryIterator(
            \PFAD_ROOT . self::getStoragePath(),
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS
        );
        $cnt = 0;
        /** @var SplFileInfo $fileinfo */
        foreach (new RecursiveIteratorIterator($rdi, RecursiveIteratorIterator::CHILD_FIRST) as $fileinfo) {
            if ($fileinfo->isFile() && \in_array($fileinfo->getExtension(), self::$imageExtensions, true)) {
                ++$cnt;
            }
        }

        return $cnt;
    }
}
