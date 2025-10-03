<?php

declare(strict_types=1);

namespace JTL\Media\Image;

use Generator;
use JTL\Media\Image;
use JTL\Media\MediaImageRequest;
use stdClass;

/**
 * Class ConfigGroup
 * @package JTL\Media\Image
 */
class ConfigGroup extends AbstractImage
{
    public const TYPE = Image::TYPE_CONFIGGROUP;

    public const REGEX = '/^media\/image'
    . '\/(?P<type>configgroup)'
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
                           FROM tkonfiggruppe 
                           WHERE kKonfiggruppe = :cid 
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
            'SELECT a.kKonfiggruppe, t.cName, cBildPfad AS path
                FROM tkonfiggruppe a
                JOIN tkonfiggruppesprache t 
                    ON a.kKonfiggruppe = t.kKonfiggruppe
                JOIN tsprache
                    ON tsprache.kSprache = t.kSprache
                WHERE a.kKonfiggruppe = :cid
                AND tsprache.cShopStandard = \'Y\'',
            ['cid' => $req->getID()]
        )->map(fn(stdClass $item): string => self::getCustomName($item))->toArray();
    }

    /**
     * @inheritdoc
     */
    public static function getCustomName(mixed $mixed): string
    {
        $result = '';
        if (isset($mixed->cName)) {
            $result = $mixed->cName;
        } elseif (\method_exists($mixed, 'getSprache')) {
            $result = $mixed->getSprache()->getName();
        } elseif (isset($mixed->path)) {
            $result = \pathinfo($mixed->path, \PATHINFO_FILENAME);
        } elseif (isset($mixed->cBildpfad)) {
            $result = \pathinfo($mixed->cBildpfad, \PATHINFO_FILENAME);
        }

        return empty($result) ? 'image' : Image::getCleanFilename($result);
    }

    /**
     * @inheritdoc
     */
    public static function getStoragePath(): string
    {
        return \STORAGE_CONFIGGROUPS;
    }

    /**
     * @inheritdoc
     */
    public function getPathByID(int|string $id, ?int $number = null): ?string
    {
        return $this->db->getSingleObject(
            'SELECT cBildpfad AS path 
                FROM tkonfiggruppe 
                WHERE kKonfiggruppe = :cid LIMIT 1',
            ['cid' => $id]
        )->path ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getAllImages(?int $offset = null, ?int $limit = null): Generator
    {
        $images = $this->db->getPDOStatement(
            'SELECT a.kKonfiggruppe AS id, t.cName, cBildPfad AS path
                FROM tkonfiggruppe a
                JOIN tkonfiggruppesprache t 
                    ON a.kKonfiggruppe = t.kKonfiggruppe
                JOIN tsprache
                    ON tsprache.kSprache = t.kSprache
                WHERE tsprache.cShopStandard = \'Y\'
                  AND cBildPfad IS NOT NULL
                  AND cBildPfad != \'\'' . self::getLimitStatement($offset, $limit)
        );
        while (($image = $images->fetchObject()) !== false) {
            $image->id = (int)$image->id;
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
}
