<?php

declare(strict_types=1);

namespace JTL\Media\Image;

use Generator;
use JTL\Helpers\URL;
use JTL\Media\Image;
use JTL\Media\MediaImageRequest;
use stdClass;

/**
 * Class Manufacturer
 * @package JTL\Media
 */
class Manufacturer extends AbstractImage
{
    public const TYPE = Image::TYPE_MANUFACTURER;

    public const REGEX = '/^media\/image'
    . '\/(?P<type>manufacturer)'
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
                           FROM thersteller 
                           WHERE kHersteller = :kHersteller',
            'bind' => ['kHersteller' => $id]
        ];
    }

    /**
     * @inheritdoc
     */
    public function getImageNames(MediaImageRequest $req): array
    {
        return $this->db->getCollection(
            'SELECT kHersteller, cName, cSeo AS seoPath, cSeo AS originalSeo, cBildpfad AS path
                FROM thersteller
                WHERE kHersteller = :mid',
            ['mid' => $req->getID()]
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
        switch (Image::getSettings()['naming'][Image::TYPE_MANUFACTURER]) {
            case 2:
                /** @var string|null $result */
                $result = $mixed->path ?? $mixed->cBildpfad ?? null;
                if ($result !== null) {
                    $result = \pathinfo($result, \PATHINFO_FILENAME);
                }
                break;
            case 1:
                $result = \method_exists($mixed, 'getOriginalSeo')
                    ? $mixed->getOriginalSeo()
                    : ($mixed->seoPath ?? $mixed->cName ?? null);
                break;
            case 0:
            default:
                $result = $mixed->id ?? $mixed->kHersteller ?? null;
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
                FROM thersteller
                WHERE kHersteller = :mid LIMIT 1',
            ['mid' => $id]
        )->path ?? null;
    }

    /**
     * @inheritdoc
     */
    public static function getStoragePath(): string
    {
        return \STORAGE_MANUFACTURERS;
    }

    /**
     * @inheritdoc
     */
    public function getAllImages(?int $offset = null, ?int $limit = null): Generator
    {
        $images = $this->db->getPDOStatement(
            'SELECT kHersteller AS id, cName, cSeo AS seoPath, cBildpfad AS path
                FROM thersteller
                WHERE nAktiv = 1
                  AND cBildpfad IS NOT NULL
                  AND cBildpfad != \'\'' . self::getLimitStatement($offset, $limit)
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

    /**
     * @inheritdoc
     */
    public function getTotalImageCount(): int
    {
        return $this->db->getSingleInt(
            'SELECT COUNT(kHersteller) AS cnt
                FROM thersteller
                WHERE cBildpfad IS NOT NULL AND cBildpfad != \'\' AND nAktiv = 1',
            'cnt'
        );
    }

    /**
     * @inheritdoc
     */
    public function imageIsUsed(string $path): bool
    {
        return $this->db->select('thersteller', 'cBildpfad', $path) !== null;
    }

    /**
     * @inheritdoc
     */
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
            'thersteller',
            'kHersteller',
            $request->getID()
        );
        if ($data === null) {
            return $corruptedImage;
        }
        $data->cURLFull             = URL::buildURL($data, \URLART_HERSTELLER, true);
        $corruptedImage->id         = $data->kHersteller;
        $corruptedImage->identifier = $data->cName;
        $corruptedImage->url        = $data->cURLFull;
        $corruptedImage->image      = $request->getRaw();

        return $corruptedImage;
    }
}
