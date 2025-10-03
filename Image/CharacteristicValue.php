<?php

declare(strict_types=1);

namespace JTL\Media\Image;

use Generator;
use JTL\Media\Image;
use JTL\Media\MediaImageRequest;
use stdClass;

/**
 * Class CharacteristicValue
 * @package JTL\Media
 */
class CharacteristicValue extends AbstractImage
{
    public const TYPE = Image::TYPE_CHARACTERISTIC_VALUE;

    public const REGEX = '/^media\/image'
    . '\/(?P<type>characteristicvalue)'
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
                           FROM tmerkmalwert 
                           WHERE kMerkmalWert = :kMerkmalWert 
                           ORDER BY nSort ASC',
            'bind' => ['kMerkmalWert' => $id]
        ];
    }

    /**
     * @inheritdoc
     */
    public function getImageNames(MediaImageRequest $req): array
    {
        return $this->db->getCollection(
            'SELECT a.kMerkmalWert, a.kMerkmalWert AS id, a.cBildpfad AS path, t.cWert AS val, t.cSeo AS seoPath
                FROM tmerkmalwert AS a
                JOIN tmerkmalwertsprache t
                    ON a.kMerkmalWert = t.kMerkmalWert
                JOIN tsprache
                    ON tsprache.kSprache = t.kSprache
                WHERE a.kMerkmalWert = :cid
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
        switch (Image::getSettings()['naming'][Image::TYPE_CHARACTERISTIC_VALUE]) {
            case 2:
                /** @var string|null $result */
                $result = $mixed->path ?? $mixed->cBildpfad ?? $mixed->currentImagePath ?? null;
                if ($result !== null) {
                    $result = \pathinfo($result, \PATHINFO_FILENAME);
                }
                break;
            case 1:
                $result = $mixed->seoPath ?? $mixed->val ?? null;
                if ($result === null && !empty($mixed->currentImagePath)) {
                    $result = \pathinfo($mixed->currentImagePath, \PATHINFO_FILENAME);
                }
                break;
            case 0:
            default:
                $result = $mixed->id ?? $mixed->kMerkmalWert ?? null;
                break;
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
                FROM tmerkmalwert
                WHERE kMerkmalWert = :cid LIMIT 1',
            ['cid' => $id]
        )->path ?? null;
    }

    /**
     * @inheritdoc
     */
    public static function getStoragePath(): string
    {
        return \STORAGE_CHARACTERISTIC_VALUES;
    }

    /**
     * @inheritdoc
     */
    public function getAllImages(?int $offset = null, ?int $limit = null): Generator
    {
        $images = $this->db->getPDOStatement(
            'SELECT A.cBildpfad AS path, A.kMerkmalwert, A.kMerkmalwert AS id, B.cWert AS val, B.cSeo AS seoPath
                FROM tmerkmalwert A
                JOIN tmerkmalwertsprache B
                    ON A.kMerkmalWert = B.kMerkmalWert
                JOIN tsprache
                    ON tsprache.kSprache = B.kSprache
                WHERE cBildpfad IS NOT NULL
                    AND cBildpfad != \'\'
                    AND tsprache.cShopStandard = \'Y\'
                GROUP BY path, id' . self::getLimitStatement($offset, $limit)
        );
        while (($image = $images->fetchObject()) !== false) {
            $image->id           = (int)$image->id;
            $image->kMerkmalwert = (int)$image->kMerkmalwert;
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
            'SELECT COUNT(kMerkmalWert) AS cnt
                FROM tmerkmalwert
                WHERE cBildpfad IS NOT NULL
                    AND cBildpfad != \'\'',
            'cnt'
        );
    }
}
