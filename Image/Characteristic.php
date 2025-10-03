<?php

declare(strict_types=1);

namespace JTL\Media\Image;

use Generator;
use JTL\Media\Image;
use JTL\Media\MediaImageRequest;
use stdClass;

/**
 * Class Characteristic
 * @package JTL\Media\Image
 */
class Characteristic extends AbstractImage
{
    public const TYPE = Image::TYPE_CHARACTERISTIC;

    public const REGEX = '/^media\/image'
    . '\/(?P<type>characteristic)'
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
            'stmt' => 'SELECT cBildpfad, 0 AS number 
                           FROM tmerkmal 
                           WHERE kMerkmal = :cid 
                           ORDER BY nSort ASC',
            'bind' => ['cid' => $id]
        ];
    }

    /**
     * @inheritdoc
     */
    public function getImageNames(MediaImageRequest $req): array
    {
        return $this->db->getCollection(
            'SELECT a.kMerkmal, a.cBildpfad AS path, t.cName
                FROM tmerkmal AS a
                JOIN tmerkmalsprache t
                    ON a.kMerkmal = t.kMerkmal
                JOIN tsprache
                    ON tsprache.kSprache = t.kSprache
                WHERE a.kMerkmal = :cid
                    AND tsprache.cShopStandard = \'Y\'',
            ['cid' => $req->getID()]
        )->each(static function (stdClass $item, int $key) use ($req): void {
            if ($key === 0 && !empty($item->path)) {
                $req->setSourcePath($item->path);
            }
            $item->imageName = self::getCustomName($item);
        })->pluck('imageName')->toArray();
    }

    /**
     * @inheritdoc
     */
    public static function getCustomName(mixed $mixed): string
    {
        switch (Image::getSettings()['naming'][Image::TYPE_CHARACTERISTIC]) {
            case 2:
                /** @var string|null $result */
                $result = \method_exists($mixed, 'getImagePath')
                    ? $mixed->getImagePath()
                    : ($mixed->path ?? $mixed->cBildpfad ?? null);
                if ($result !== null) {
                    $result = \pathinfo($result, \PATHINFO_FILENAME);
                }
                break;
            case 1:
                $result = \method_exists($mixed, 'getName') ? $mixed->getName() : ($mixed->cName ?? null);
                break;
            case 0:
            default:
                $result = \method_exists($mixed, 'getID') ? $mixed->getID() : ($mixed->id ?? $mixed->kMerkmal ?? null);
                break;
        }
        if ($result === null && $mixed->currentImagePath !== null) {
            $result = \pathinfo($mixed->currentImagePath, \PATHINFO_FILENAME);
        }

        return empty($result) ? 'image' : Image::getCleanFilename((string)$result);
    }

    /**
     * @inheritdoc
     */
    public function getPathByID(int|string $id, ?int $number = null): ?string
    {
        return $this->db->getSingleObject(
            'SELECT cBildpfad AS path
                FROM tmerkmal
                WHERE kMerkmal = :cid LIMIT 1',
            ['cid' => $id]
        )->path ?? null;
    }

    /**
     * @inheritdoc
     */
    public static function getStoragePath(): string
    {
        return \STORAGE_CHARACTERISTICS;
    }

    /**
     * @inheritdoc
     */
    public function getAllImages(?int $offset = null, ?int $limit = null): Generator
    {
        $images = $this->db->getPDOStatement(
            'SELECT cBildpfad AS path, kMerkmal, kMerkmal AS id, cName
                FROM tmerkmal
                WHERE cBildpfad IS NOT NULL
                    AND cBildpfad != \'\'' . self::getLimitStatement($offset, $limit)
        );
        while (($image = $images->fetchObject()) !== false) {
            $image->id       = (int)$image->id;
            $image->kMerkmal = (int)$image->kMerkmal;
            yield MediaImageRequest::create([
                'id'         => $image->id,
                'type'       => self::TYPE,
                'name'       => self::getCustomName($image),
                'number'     => 1,
                'path'       => $image->path,
                'sourcePath' => $image->path,
                'ext'        => static::getFileExtension($image->path)
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function getTotalImageCount(): int
    {
        return $this->db->getSingleInt(
            'SELECT COUNT(kMerkmal) AS cnt
                FROM tmerkmal
                WHERE cBildpfad IS NOT NULL
                    AND cBildpfad != \'\'',
            'cnt'
        );
    }
}
