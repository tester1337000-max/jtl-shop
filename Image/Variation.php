<?php

declare(strict_types=1);

namespace JTL\Media\Image;

use Generator;
use JTL\Helpers\URL;
use JTL\Media\Image;
use JTL\Media\MediaImageRequest;
use stdClass;

/**
 * Class Variation
 * @package JTL\Media\Image
 */
class Variation extends AbstractImage
{
    public const TYPE = Image::TYPE_VARIATION;

    public const REGEX = '/^media\/image'
    . '\/(?P<type>variation)'
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
            'stmt' => 'SELECT kEigenschaftWert, 0 AS number 
                           FROM teigenschaftwertpict 
                           WHERE kEigenschaftWert = :vid',
            'bind' => ['vid' => $id]
        ];
    }

    /**
     * @inheritdoc
     */
    public function getImageNames(MediaImageRequest $req): array
    {
        return $this->db->getCollection(
            'SELECT p.kEigenschaftWert, p.kEigenschaftWertPict, p.cPfad AS path, t.cName
                FROM teigenschaftwertpict p
                JOIN teigenschaftwert t
                    ON p.kEigenschaftWert = t.kEigenschaftWert
                WHERE p.kEigenschaftWert = :vid',
            ['vid' => $req->getID()]
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
        if (!empty($mixed->currentImagePath)) {
            $result = \pathinfo($mixed->currentImagePath, \PATHINFO_FILENAME);
        } elseif (isset($mixed->cPfad)) {
            $result = \pathinfo($mixed->cPfad, \PATHINFO_FILENAME);
        } elseif (isset($mixed->path)) {
            $result = \pathinfo($mixed->path, \PATHINFO_FILENAME);
        } else {
            $result = $mixed->cName;
        }

        return empty($result) ? 'image' : Image::getCleanFilename($result);
    }

    /**
     * @inheritdoc
     */
    public function getPathByID(int|string $id, ?int $number = null): ?string
    {
        return $this->db->getSingleObject(
            'SELECT cPfad AS path
                FROM teigenschaftwertpict
                WHERE kEigenschaftWert = :vid
                LIMIT 1',
            ['vid' => $id]
        )->path ?? null;
    }

    /**
     * @inheritdoc
     */
    public static function getStoragePath(): string
    {
        return \STORAGE_VARIATIONS;
    }

    /**
     * @inheritdoc
     */
    public function getAllImages(?int $offset = null, ?int $limit = null): Generator
    {
        $images = $this->db->getPDOStatement(
            'SELECT p.kEigenschaftWert AS id, p.kEigenschaftWertPict, p.cPfad AS path, t.cName
                FROM teigenschaftwertpict p
                JOIN teigenschaftwert t
                    ON p.kEigenschaftWert = t.kEigenschaftWert' . self::getLimitStatement($offset, $limit)
        );
        while (($image = $images->fetchObject()) !== false) {
            $image->id                   = (int)$image->id;
            $image->kEigenschaftWertPict = (int)$image->kEigenschaftWertPict;
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
            'SELECT COUNT(kEigenschaftWertPict) AS cnt
                FROM teigenschaftwertpict
                WHERE cPfad IS NOT NULL
                    AND cPfad != \'\'',
            'cnt'
        );
    }

    /**
     * @inheritdoc
     */
    public function getCorruptedImage(MediaImageRequest $request): stdClass
    {
        return (object)[
            'image'      => $request->getRaw(),
            'id'         => $request->getID(),
            'url'        => '#',
            'identifier' => $request->getID(),
            'type'       => self::TYPE,
        ];
    }
}
