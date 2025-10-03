<?php

declare(strict_types=1);

namespace JTL\Media\Image;

use Generator;
use JTL\DB\DbInterface;
use JTL\Helpers\URL;
use JTL\Media\Image;
use JTL\Media\MediaImageRequest;
use JTL\Shop;
use stdClass;

/**
 * Class Product
 * @package JTL\Media\Image
 */
class Product extends AbstractImage
{
    public const TYPE = Image::TYPE_PRODUCT;

    public const REGEX = '/^media\/image'
    . '\/(?P<type>product)'
    . '\/(?P<id>\d+)'
    . '\/(?P<size>xs|sm|md|lg|xl|os)'
    . '\/(?P<name>[' . self::REGEX_ALLOWED_CHARS . ']+)'
    . '(?:(?:~(?P<number>\d+))?)\.(?P<ext>jpg|jpeg|png|gif|webp|avif)$/';

    /**
     * @inheritdoc
     */
    public function getImageNames(MediaImageRequest $req): array
    {
        return $this->db->getCollection(
            'SELECT A.kArtikel, A.cName, A.cSeo, A.cSeo AS originalSeo, A.cArtNr, A.cBarcode, B.cWert AS customImgName
                FROM tartikel A
                LEFT JOIN tartikelattribut B 
                    ON A.kArtikel = B.kArtikel
                    AND B.cName = :atr
                WHERE A.kArtikel = :pid',
            ['pid' => $req->getID(), 'atr' => 'bildname']
        )->map(static fn(stdClass $item): string => self::getCustomName($item))->toArray();
    }

    /**
     * @inheritdoc
     */
    public function getTotalImageCount(): int
    {
        return $this->db->getSingleInt(
            'SELECT COUNT(tartikelpict.kArtikel) AS cnt
                FROM tartikelpict
                INNER JOIN tartikel
                    ON tartikelpict.kArtikel = tartikel.kArtikel',
            'cnt'
        );
    }

    /**
     * @inheritdoc
     */
    public function getAllImages(?int $offset = null, ?int $limit = null): Generator
    {
        $cols = '';
        switch (Image::getSettings()['naming'][Image::TYPE_PRODUCT]) {
            case 1:
                $cols = ', tartikel.cArtNr';
                break;
            case 2:
                $cols = ', tartikel.cSeo, tartikel.cSeo AS originalSeo, tartikel.cName';
                break;
            case 3:
                $cols = ', tartikel.cArtNr, tartikel.cSeo, tartikel.cSeo AS originalSeo, tartikel.cName';
                break;
            case 4:
                $cols = ', tartikel.cBarcode';
                break;
            case 0:
            default:
                break;
        }
        $images = $this->db->getPDOStatement(
            'SELECT B.cWert AS customImgName, P.cPfad AS path, P.nNr AS number, P.kArtikel ' . $cols . '
                FROM tartikelpict P
                INNER JOIN tartikel
                    ON P.kArtikel = tartikel.kArtikel
                LEFT JOIN tartikelattribut B 
                    ON tartikel.kArtikel = B.kArtikel
                    AND B.cName = \'bildname\''
            . self::getLimitStatement($offset, $limit)
        );
        while (($image = $images->fetchObject()) !== false) {
            $image->kArtikel = (int)$image->kArtikel;
            $image->number   = (int)$image->number;
            yield MediaImageRequest::create([
                'id'         => $image->kArtikel,
                'type'       => self::TYPE,
                'name'       => self::getCustomName($image),
                'number'     => $image->number,
                'path'       => $image->path,
                'sourcePath' => $image->path,
                'ext'        => static::getFileExtension($image->path)
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public static function getCustomName(mixed $mixed): string
    {
        if (!empty($mixed->customImgName)) { // set by FKT_ATTRIBUT_BILDNAME
            return Image::getCleanFilename($mixed->customImgName);
        }
        $result = match (Image::getSettings()['naming'][Image::TYPE_PRODUCT]) {
            0       => (string)$mixed->kArtikel,
            1       => $mixed->cArtNr,
            2       => $mixed->originalSeo ?? $mixed->cSeo ?? $mixed->cName,
            3       => \sprintf('%s_%s', $mixed->cArtNr, empty($mixed->cSeo) ? $mixed->cName : $mixed->cSeo),
            4       => $mixed->cBarcode,
            default => 'image',
        };

        return empty($result) ? 'image' : Image::getCleanFilename($result);
    }

    /**
     * @inheritdoc
     */
    public function getPathByID(int|string $id, ?int $number = null): ?string
    {
        return $this->db->getSingleObject(
            'SELECT cPfad AS path
                FROM tartikelpict
                WHERE kArtikel = :pid
                    AND nNr = :no
                ORDER BY nNr
                LIMIT 1',
            ['pid' => $id, 'no' => $number]
        )->path ?? null;
    }

    public static function getPrimaryNumber(int $id, ?DbInterface $db = null): ?int
    {
        $prepared = self::getImageStmt(Image::TYPE_PRODUCT, $id);
        if ($prepared !== null) {
            $db      = $db ?? Shop::Container()->getDB();
            $primary = $db->getSingleObject(
                $prepared->stmt,
                $prepared->bind
            );
            if ($primary !== null) {
                return \max(1, (int)$primary->number);
            }
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public static function getImageStmt(string $type, int $id): ?stdClass
    {
        return (object)[
            'stmt' => 'SELECT kArtikel, nNr AS number
                FROM tartikelpict 
                WHERE kArtikel = :kArtikel 
                GROUP BY cPfad 
                ORDER BY nNr ASC',
            'bind' => ['kArtikel' => $id]
        ];
    }

    /**
     * @inheritdoc
     */
    public function imageIsUsed(string $path): bool
    {
        return $this->db->select('tartikelpict', 'cPfad', $path) !== null;
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
            'tartikel',
            'kArtikel',
            $request->getID()
        );
        if ($data === null) {
            return $corruptedImage;
        }
        $data->cURLFull             = URL::buildURL($data, \URLART_ARTIKEL, true);
        $corruptedImage->id         = $data->kArtikel;
        $corruptedImage->identifier = $data->cArtNr;
        $corruptedImage->url        = $data->cURLFull;
        $corruptedImage->image      = $request->getRaw();

        return $corruptedImage;
    }
}
