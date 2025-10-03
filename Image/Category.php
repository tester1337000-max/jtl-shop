<?php

declare(strict_types=1);

namespace JTL\Media\Image;

use Generator;
use JTL\Helpers\URL;
use JTL\Media\Image;
use JTL\Media\MediaImageRequest;
use stdClass;

/**
 * Class Category
 * @package JTL\Media\Image
 */
class Category extends AbstractImage
{
    public const TYPE = Image::TYPE_CATEGORY;

    public const REGEX = '/^media\/image'
    . '\/(?P<type>category)'
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
            'stmt' => 'SELECT kKategorie, 0 AS number 
                          FROM tkategoriepict 
                          WHERE kKategorie = :kKategorie',
            'bind' => ['kKategorie' => $id]
        ];
    }

    /**
     * @inheritdoc
     */
    public function getImageNames(MediaImageRequest $req): array
    {
        return $this->db->getCollection(
            'SELECT pic.cPfad AS path, pic.kKategorie, pic.kKategorie AS id, cat.cName,
                atr.cWert AS customImgName, cat.cSeo AS seoPath
                FROM tkategorie cat
                JOIN tkategoriepict pic
                    ON cat.kKategorie = pic.kKategorie
                LEFT JOIN tkategorieattribut atr
                    ON cat.kKategorie = atr.kKategorie
                    AND atr.cName = :atr
                WHERE pic.kKategorie = :cid',
            ['cid' => $req->getID(), 'atr' => \KAT_ATTRIBUT_BILDNAME]
        )->map(fn(stdClass $item): string => self::getCustomName($item))->toArray();
    }

    /**
     * @inheritdoc
     */
    public static function getCustomName(mixed $mixed): string
    {
        if (\is_string($mixed)) {
            $result = \pathinfo($mixed, \PATHINFO_FILENAME);
        } elseif (isset($mixed->customImgName)) {
            $result = $mixed->customImgName;
        } elseif (isset($mixed->currentImagePath)) {
            $result = \pathinfo($mixed->currentImagePath, \PATHINFO_FILENAME);
        } else {
            switch (Image::getSettings()['naming'][Image::TYPE_CATEGORY]) {
                case 2:
                    /** @var string|null $result */
                    $result = $mixed->path ?? $mixed->cBildpfad ?? null;
                    if ($result !== null) {
                        $result = \pathinfo($result, \PATHINFO_FILENAME);
                    }
                    break;
                case 1:
                    $result = \method_exists($mixed, 'getURL')
                        ? $mixed->getURL()
                        : ($mixed->originalSeo ?? $mixed->seoPath ?? $mixed->cName ?? null);
                    break;
                case 0:
                default:
                    $result = \method_exists($mixed, 'getID')
                        ? $mixed->getID()
                        : ($mixed->id ?? $mixed->kKategorie ?? null);
                    break;
            }
        }

        return empty($result) ? 'image' : Image::getCleanFilename((string)$result);
    }

    /**
     * @inheritdoc
     */
    public function getPathByID(int|string $id, ?int $number = null): ?string
    {
        return $this->db->getSingleObject(
            'SELECT cPfad AS path
                FROM tkategoriepict
                WHERE kKategorie = :cid LIMIT 1',
            ['cid' => $id]
        )->path ?? null;
    }

    /**
     * @inheritdoc
     */
    public static function getStoragePath(): string
    {
        return \STORAGE_CATEGORIES;
    }

    /**
     * @inheritdoc
     */
    public function getAllImages(?int $offset = null, ?int $limit = null): Generator
    {
        $images = $this->db->getPDOStatement(
            'SELECT pic.cPfad AS path, pic.kKategorie, pic.kKategorie AS id, cat.cName, 
                atr.cWert AS customImgName, cat.cSeo AS seoPath
                FROM tkategorie cat
                JOIN tkategoriepict pic
                    ON cat.kKategorie = pic.kKategorie
                LEFT JOIN tkategorieattribut atr
                    ON cat.kKategorie = atr.kKategorie
                    AND atr.cName = :atr'
            . self::getLimitStatement($offset, $limit),
            ['atr' => \KAT_ATTRIBUT_BILDNAME]
        );
        while (($image = $images->fetchObject()) !== false) {
            $image->id         = (int)$image->id;
            $image->kKategorie = (int)$image->kKategorie;
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
            'SELECT COUNT(tkategoriepict.kKategorie) AS cnt
                FROM tkategoriepict
                INNER JOIN tkategorie
                    ON tkategorie.kKategorie = tkategoriepict.kKategorie',
            'cnt'
        );
    }

    /**
     * @inheritdoc
     */
    public function imageIsUsed(string $path): bool
    {
        return $this->db->select('tkategoriepict', 'cPfad', $path) !== null;
    }

    public function getCorruptedImage(MediaImageRequest $request): stdClass
    {
        $corruptedImage = (object)[
            'image'      => null,
            'id'         => null,
            'url'        => null,
            'identifier' => null,
            'type'       => self::TYPE,
        ];
        $data           = $this->db->select(
            'tkategorie',
            'kKategorie',
            $request->getID()
        );
        if ($data === null) {
            return $corruptedImage;
        }
        $data->cURLFull             = URL::buildURL($data, \URLART_KATEGORIE, true);
        $corruptedImage->id         = $data->kKategorie;
        $corruptedImage->identifier = $data->kKategorie;
        $corruptedImage->url        = $data->cURLFull;
        $corruptedImage->image      = $request->getRaw();

        return $corruptedImage;
    }
}
